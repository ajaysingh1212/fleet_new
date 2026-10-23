<?php

namespace App\Services\Gps;

use App\Events\HardwareLocationUpdated;
use App\Models\GpsDevice;
use App\Models\Vehicle;

final class HardwareFleet
{
    public function snapshot(): array
    {
        $devices = GpsDevice::with(['vehicle', 'latestLocation', 'latestHeartbeat'])->orderBy('id')->get();
        $fleet = $devices->map(fn ($device) => $this->device($device))->all();
        foreach (Vehicle::whereNotIn('id', $devices->pluck('vehicle_id')->filter())->get() as $vehicle) {
            $fleet[] = ['device_id' => null, 'vehicle_id' => $vehicle->id, 'registration' => $vehicle->registration_number,
                'name' => $vehicle->name, 'vehicle_type' => strtolower($vehicle->details['type'] ?? 'vehicle'),
                'driver_name' => $vehicle->details['driver_name'] ?? null, 'state' => 'unassigned',
                'authorization' => $vehicle->status, 'online' => false];
        }

        return $fleet;
    }

    public function device(GpsDevice $device): array
    {
        $position = $device->latestLocation;
        $heartbeat = $device->latestHeartbeat;
        $telemetry = $heartbeat ? json_decode($heartbeat->telemetry, true) : [];
        $positionAcc = $position?->telemetry['acc'] ?? null;
        // A retransmitted old GPS fix must not overwrite a newer ignition report.
        $heartbeatNewer = $heartbeat && (! $position || $heartbeat->received_at > $position->recorded_at->format('Y-m-d H:i:s'));
        $acc = $heartbeatNewer ? ($telemetry['acc'] ?? $positionAcc) : ($positionAcc ?? $telemetry['acc'] ?? null);
        $accFromHeartbeat = $heartbeatNewer ? isset($telemetry['acc']) : ! isset($positionAcc);
        $accReportedAt = $accFromHeartbeat && $heartbeat
            ? \Carbon\CarbonImmutable::parse($heartbeat->received_at, 'UTC') : $position?->recorded_at;
        $accFresh = $acc !== null && $accReportedAt?->gte(now()->subSeconds(config('gps.idle_seconds')));
        $reportedAcc = $acc;
        // An old ignition report is historical evidence, not current ignition.
        if (! $accFresh) {
            $acc = null;
        }
        $fresh = $position && $position->recorded_at->gte(now()->subSeconds(config('gps.idle_seconds')));
        $heartbeatFresh = $heartbeat && \Carbon\CarbonImmutable::parse($heartbeat->received_at, 'UTC')
            ->gte(now()->subSeconds(config('gps.idle_seconds')));
        $reportedFix = $heartbeatNewer && $heartbeatFresh ? ($telemetry['gps_positioned_reported'] ?? null) : null;
        $state = $device->status !== 'active' ? $device->status : (! $device->is_online ? 'offline' :
            (! $position ? 'awaiting_gps' : ($reportedFix === false ? 'no_gps_fix' : (! $fresh ? 'stale_gps' :
                ($position->speed === null ? 'unknown' : ($position->speed > 1.8 ? 'moving' : ($acc === true ? 'idle' : ($acc === false ? 'parked' : 'stopped'))))))));

        return [...($position ? HardwareLocationUpdated::payload($device, $position) :
            ['latitude' => null, 'longitude' => null, 'recorded_at' => null, 'received_at' => null, 'speed' => null, 'heading' => null]),
            'device_id' => $device->id, 'imei' => $device->identifier, 'name' => $device->name,
            'vehicle_id' => $device->vehicle_id, 'registration' => $device->vehicle?->registration_number,
            'vehicle_type' => strtolower($device->vehicle?->details['type'] ?? 'vehicle'),
            'driver_name' => $device->vehicle?->details['driver_name'] ?? null,
            'authorization' => $device->status, 'online' => $device->is_online, 'state' => $state,
            'last_packet_at' => $device->last_packet_at?->toIso8601String(), 'acc' => $acc,
            'acc_reported' => $reportedAcc, 'acc_reported_at' => $accReportedAt?->toIso8601String(),
            'acc_source' => $acc === null && $reportedAcc === null ? null : ($accFromHeartbeat ? 'heartbeat' : 'location'),
            'gps_fresh' => (bool) $fresh, 'gps_positioned_reported' => $reportedFix, 'idle_seconds' => config('gps.idle_seconds')];
    }
}
