<?php

namespace App\Jobs;

use App\Events\HardwareLocationUpdated;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessHardwarePacket implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [1, 5, 15, 30];
    }

    public function __construct(
        public int $deviceId, public string $identifier, public string $sessionId, public string $connectedAt,
        public string $peer, public string $protocol, public string $version, public string $type,
        public string $hash, public array $locations, public array $events, public string $receivedAt,
        public ?int $vehicleId,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $device = GpsDevice::lockForUpdate()->find($this->deviceId);
            if (! $device || $device->status !== 'active' || $device->identifier !== $this->identifier
                || $device->protocol !== $this->protocol || $device->vehicle_id !== $this->vehicleId
                || ($this->version !== 'login' && $device->protocol_version !== $this->version)) {
                return;
            }
            DB::table('gps_connection_sessions')->insertOrIgnore([
                'id' => $this->sessionId, 'gps_device_id' => $device->id, 'protocol' => $this->protocol,
                'remote_ip' => $this->peer, 'connected_at' => $this->connectedAt, 'last_packet_at' => $this->receivedAt,
                'authenticated' => true,
            ]);
            DB::table('gps_connection_sessions')->where('id', $this->sessionId)
                ->where('last_packet_at', '<', $this->receivedAt)->update(['last_packet_at' => $this->receivedAt]);
            $changes = [];
            if ($this->events) {
                DB::table('gps_device_events')->insertOrIgnore([
                    'gps_device_id' => $device->id, 'packet_hash' => $this->hash, 'type' => $this->type,
                    'telemetry' => json_encode($this->events, JSON_THROW_ON_ERROR), 'received_at' => $this->receivedAt,
                ]);
            }
            if (! $device->last_connected_at || $device->last_connected_at->lt($this->connectedAt)) {
                $changes['last_connected_at'] = $this->connectedAt;
            }
            if (! $device->last_packet_at || $device->last_packet_at->lte($this->receivedAt)) {
                $changes['last_packet_at'] = $this->receivedAt;
                $changes['remote_ip'] = $this->peer;
            }
            $latest = $device->latestLocation;
            foreach ($this->locations as $index => $data) {
                if (! $data['gps_fix']) {
                    continue;
                }
                $location = GpsDeviceLocation::firstOrCreate([
                    'gps_device_id' => $device->id, 'packet_hash' => $this->hash, 'record_index' => $index,
                ], [...$data, 'vehicle_id' => $this->vehicleId, 'protocol' => $this->protocol,
                    'protocol_version' => $this->version, 'received_at' => $this->receivedAt]);
                if ($location->wasRecentlyCreated && (! $latest || $location->recorded_at->gt($latest->recorded_at))) {
                    $latest = $location;
                    $changes['latest_location_id'] = $location->id;
                }
            }
            // Communication telemetry should not pollute the administrative audit log.
            $device->forceFill($changes)->saveQuietly();
            if ($latest && $latest->packet_hash === $this->hash && $this->locations) {
                $payload = HardwareLocationUpdated::payload($device, $latest);
                DB::afterCommit(fn () => HardwareLocationUpdated::dispatch($payload));
            }
        }, 3);
    }
}
