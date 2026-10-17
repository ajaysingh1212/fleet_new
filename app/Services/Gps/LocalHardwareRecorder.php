<?php

namespace App\Services\Gps;

use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Services\Gps\Gateway\Packet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Explicit local terminal persistence; production Redis gateway remains separate. */
final class LocalHardwareRecorder
{
    public function allows(string $imei, string $profile, string $version): bool
    {
        return GpsDevice::where('identifier', $imei)->where('status', 'active')
            ->where('protocol', $profile)->where('protocol_version', $version)->exists();
    }

    public function record(Packet $packet, string $frame, string $imei, string $profile, string $session, string $connectedAt, string $peer): void
    {
        DB::transaction(function () use ($packet, $frame, $imei, $profile, $session, $connectedAt, $peer) {
            $device = GpsDevice::where('identifier', $imei)->lockForUpdate()->first();
            if (! $device || $device->status !== 'active' || $device->protocol !== $profile || $device->protocol_version !== $packet->version) {
                throw new InvalidArgumentException('persistence_requires_active_registered_device_and_matching_protocol');
            }
            (new ProcessHardwarePacket($device->id, $imei, $session, $connectedAt, $peer, $profile,
                $packet->version, $packet->type, hash('sha256', $frame),
                array_map(fn ($location) => $location->toArray(), $packet->locations), $packet->events,
                now()->utc()->format('Y-m-d H:i:s'), $device->vehicle_id, false))->handle();
        });
    }
}
