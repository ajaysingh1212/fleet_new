<?php

namespace App\Jobs;

use App\Events\HardwareLocationUpdated;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Services\Geofence\VehicleGeofenceMonitor;
use App\Services\Gps\HardwareTelemetry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessHardwarePacket implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public bool $broadcast = true;

    public function backoff(): array
    {
        return [1, 5, 15, 30];
    }

    public function __construct(
        public int $deviceId, public string $identifier, public string $sessionId, public string $connectedAt,
        public string $peer, public string $protocol, public string $version, public string $type,
        public string $hash, public array $locations, public array $events, public string $receivedAt,
        public ?int $vehicleId,
        bool $broadcast = true,
        public ?string $receivedPreciselyAt = null, public ?string $traceId = null,
    ) {
        $this->broadcast = $broadcast;
    }

    public function handle(): void
    {
        $started = hrtime(true);
        HardwareTelemetry::phase('history_job_started', ['trace_id' => $this->traceId ?? null, 'device_id' => $this->deviceId,
            'type' => $this->type, 'received_at' => $this->receivedPreciselyAt ?? $this->receivedAt]);
        $result = DB::transaction(function () {
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
            $eventsInserted = 0;
            $newLocation = false;
            $monitorLocations = [];
            if ($this->events) {
                $eventsInserted = DB::table('gps_device_events')->insertOrIgnore([
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
                $newLocation = $newLocation || $location->wasRecentlyCreated;
                if ($location->wasRecentlyCreated) {
                    $monitorLocations[] = $data;
                }
                if ($location->wasRecentlyCreated && (! $latest || $location->recorded_at->gt($latest->recorded_at))) {
                    $latest = $location;
                    $changes['latest_location_id'] = $location->id;
                }
            }
            // Communication telemetry should not pollute the administrative audit log.
            $device->forceFill($changes)->saveQuietly();
            usort($monitorLocations, fn ($a, $b) => strcmp($a['recorded_at'], $b['recorded_at']));
            foreach ($monitorLocations as $data) {
                app(VehicleGeofenceMonitor::class)->evaluate($device, $data);
            }
            if ($this->broadcast && ($eventsInserted || $newLocation || $device->wasChanged(['last_connected_at', 'last_packet_at', 'latest_location_id', 'remote_ip']))) {
                // Heartbeats/login update connection and ignition even when
                // the tracker is parked and has no new coordinate packet.
                $payload = ['device_id' => $device->id];
                DB::afterCommit(fn () => HardwareLocationUpdated::dispatch($payload));
            }

            return ['new_location' => $newLocation, 'recorded_at' => $latest?->recorded_at?->toIso8601String(),
                'latest_changed' => isset($changes['latest_location_id'])];
        }, 3);
        HardwareTelemetry::phase($result ? 'history_stored' : 'history_rejected', ['trace_id' => $this->traceId ?? null,
            'device_id' => $this->deviceId, 'type' => $this->type, 'received_at' => $this->receivedPreciselyAt ?? $this->receivedAt,
            'duration_ms' => (hrtime(true) - $started) / 1000000, 'new_location' => $result['new_location'] ?? false]);
        if ($result['latest_changed'] ?? false) {
            HardwareTelemetry::phase('latest_stored', [
                'trace_id' => $this->traceId ?? null, 'device_id' => $this->deviceId, 'recorded_at' => $result['recorded_at']]);
        }
    }
}
