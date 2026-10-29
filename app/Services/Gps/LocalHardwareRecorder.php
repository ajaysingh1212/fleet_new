<?php

namespace App\Services\Gps;

use App\Jobs\DeliverHardwarePacket;
use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Services\Gps\Gateway\Packet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;

/** Explicit local terminal persistence; production Redis gateway remains separate. */
final class LocalHardwareRecorder
{
    public function allows(string $imei, string $profile, string $version): bool
    {
        return GpsDevice::where('identifier', $imei)->where('status', 'active')
            ->where('protocol', $profile)->where('protocol_version', $version)->exists();
    }

    public function record(Packet $packet, string $frame, string $imei, string $profile, string $session, string $connectedAt, string $peer,
        ?string $receivedAt = null, ?string $traceId = null): int
    {
        $receivedAt ??= HardwareTelemetry::stamp();
        $traceId ??= bin2hex(random_bytes(8));
        if (config('gps.live_first')) {
            $device = GpsDevice::where('identifier', $imei)->first();
            if (! $device || $device->status !== 'active' || $device->protocol !== $profile || $device->protocol_version !== $packet->version) {
                throw new InvalidArgumentException('persistence_requires_active_registered_device_and_matching_protocol');
            }
            $connection = (string) config('gps.live_connection');
            $queue = Queue::connection($connection);
            if ($queue->size(config('gps.live_queue')) >= config('gps.max_queue')) {
                throw new InvalidArgumentException('queue_full');
            }
            $job = new ProcessHardwarePacket($device->id, $imei, $session, $connectedAt, $peer, $profile,
                $packet->version, $packet->type, hash('sha256', $frame),
                array_map(fn ($location) => $location->toArray(), $packet->locations), $packet->events,
                CarbonImmutable::parse($receivedAt)->utc()->format('Y-m-d H:i:s'), $device->vehicle_id, false, $receivedAt, $traceId);
            $queue->push(new DeliverHardwarePacket($job, $connection, (bool) config('gps.local_broadcast')), '', config('gps.live_queue'));
            app(HardwareConnectionState::class)->touch($device->id, $session);

            HardwareTelemetry::phase('queue_dispatched', ['trace_id' => $traceId, 'device_id' => $device->id,
                'session_id' => $session, 'type' => $packet->type, 'received_at' => $receivedAt]);

            return $device->id;
        }
        $deviceId = DB::transaction(function () use ($packet, $frame, $imei, $profile, $session, $connectedAt, $peer, $receivedAt, $traceId) {
            $device = GpsDevice::where('identifier', $imei)->lockForUpdate()->first();
            if (! $device || $device->status !== 'active' || $device->protocol !== $profile || $device->protocol_version !== $packet->version) {
                throw new InvalidArgumentException('persistence_requires_active_registered_device_and_matching_protocol');
            }
            (new ProcessHardwarePacket($device->id, $imei, $session, $connectedAt, $peer, $profile,
                $packet->version, $packet->type, hash('sha256', $frame),
                array_map(fn ($location) => $location->toArray(), $packet->locations), $packet->events,
                CarbonImmutable::parse($receivedAt)->utc()->format('Y-m-d H:i:s'), $device->vehicle_id, (bool) config('gps.local_broadcast'), $receivedAt, $traceId))->handle();

            HardwareTelemetry::phase('database_written_inline', ['trace_id' => $traceId, 'device_id' => $device->id,
                'session_id' => $session, 'type' => $packet->type, 'received_at' => $receivedAt]);

            return $device->id;
        });
        app(HardwareConnectionState::class)->touch($deviceId, $session);

        return $deviceId;
    }
}
