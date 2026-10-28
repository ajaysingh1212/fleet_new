<?php

namespace App\Services\Gps;

use App\Events\HardwareLocationUpdated;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class HardwareFleet
{
    public function query(array $filters = []): Builder
    {
        $query = GpsDevice::query();
        if ($term = $filters['search'] ?? null) {
            $query->where(fn ($q) => $q->where('identifier', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')
                ->orWhereHas('vehicle', fn ($v) => $v->where('registration_number', 'like', '%'.$term.'%')));
        }
        if ($protocol = $filters['protocol'] ?? null) {
            $query->where('protocol', $protocol);
        }
        $status = $filters['status'] ?? null;
        if ($status === 'online') {
            $query->where('status', 'active')->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds')));
            $query->where(fn ($q) => $q->whereNotExists(fn ($s) => $s->selectRaw('1')->from('gps_connection_sessions')
                ->whereColumn('gps_device_id', 'gps_devices.id'))
                ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('gps_connection_sessions')
                    ->whereColumn('gps_device_id', 'gps_devices.id')->whereNull('disconnected_at')
                    ->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds')))));
        } elseif ($status === 'offline') {
            $query->where('status', 'active')->where(fn ($q) => $q->whereNull('last_packet_at')
                ->orWhere('last_packet_at', '<', now()->subSeconds(config('gps.idle_seconds')))
                ->orWhere(fn ($q) => $q->whereExists(fn ($s) => $s->selectRaw('1')->from('gps_connection_sessions')
                    ->whereColumn('gps_device_id', 'gps_devices.id'))
                    ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('gps_connection_sessions')
                        ->whereColumn('gps_device_id', 'gps_devices.id')->whereNull('disconnected_at')
                        ->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds'))))));
        } elseif ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function page(array $filters = [], int $after = 0): array
    {
        $limit = (int) config('gps.map_limit', 250);
        $devices = $this->query($filters)->with(['vehicle', 'latestLocation', 'latestHeartbeat'])
            ->where('id', '>', $after)->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $devices->count() > $limit;
        $devices = $devices->take($limit);
        $fleet = $devices->map(fn ($device) => config('gps.live_first')
            ? app(HardwareLiveState::class)->overlay($this->device($device)) : $this->device($device))->all();
        // Use a SQL subquery, never a 100,000-element PHP ID list. Untracked
        // vehicles fill spare room on the first page only; all results are bounded.
        $unassigned = Vehicle::whereNotIn('id', GpsDevice::whereNotNull('vehicle_id')->select('vehicle_id'))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($v) => $v->where('registration_number', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')));
        foreach ((! $hasMore && $after === 0 && empty($filters['protocol']) && empty($filters['status']) && count($fleet) < $limit
            ? $unassigned->orderBy('id')->limit($limit - count($fleet))->get() : []) as $vehicle) {
            $fleet[] = ['device_id' => null, 'vehicle_id' => $vehicle->id, 'registration' => $vehicle->registration_number,
                'name' => $vehicle->name, 'vehicle_type' => strtolower($vehicle->details['type'] ?? 'vehicle'),
                'driver_name' => $vehicle->details['driver_name'] ?? null, 'state' => 'unassigned',
                'authorization' => $vehicle->status, 'online' => false];
        }

        return ['devices' => $fleet, 'meta' => ['limit' => $limit, 'after' => $after, 'has_more' => $hasMore,
            'next_after' => $hasMore ? $devices->last()->id : null]];
    }

    public function snapshot(): array
    {
        return $this->page()['devices'];
    }

    public function device(GpsDevice $device): array
    {
        $presence = app(HardwareConnectionState::class)->snapshot($device->id);
        $lastPacket = $device->last_packet_at;
        if (! empty($presence['connection_last_packet_at'])) {
            $received = CarbonImmutable::parse($presence['connection_last_packet_at']);
            if (! $lastPacket || $received->gt($lastPacket)) {
                $lastPacket = $received;
            }
        }
        $online = $device->status === 'active' && $lastPacket?->gt(now()->subSeconds(config('gps.idle_seconds')))
            && ($presence['connection_connected'] ?? true);
        $position = $device->latestLocation;
        $heartbeat = $device->latestHeartbeat;
        $telemetry = $heartbeat ? json_decode($heartbeat->telemetry, true) : [];
        $positionAcc = $position?->telemetry['acc'] ?? null;
        // A retransmitted old GPS fix must not overwrite a newer ignition report.
        $heartbeatNewer = $heartbeat && (! $position || $heartbeat->received_at > $position->recorded_at->format('Y-m-d H:i:s'));
        $acc = $heartbeatNewer ? ($telemetry['acc'] ?? $positionAcc) : ($positionAcc ?? $telemetry['acc'] ?? null);
        $accFromHeartbeat = $heartbeatNewer ? isset($telemetry['acc']) : ! isset($positionAcc);
        $accReportedAt = $accFromHeartbeat && $heartbeat
            ? CarbonImmutable::parse($heartbeat->received_at, 'UTC') : $position?->recorded_at;
        $accFresh = $acc !== null && $accReportedAt?->gte(now()->subSeconds(config('gps.idle_seconds')));
        $reportedAcc = $acc;
        // An old ignition report is historical evidence, not current ignition.
        if (! $accFresh) {
            $acc = null;
        }
        $fresh = $position && $position->recorded_at->gte(now()->subSeconds(config('gps.fresh_seconds', 15)));
        $heartbeatFresh = $heartbeat && CarbonImmutable::parse($heartbeat->received_at, 'UTC')
            ->gte(now()->subSeconds(config('gps.idle_seconds')));
        $reportedFix = $heartbeatNewer && $heartbeatFresh ? ($telemetry['gps_positioned_reported'] ?? null) : null;
        $gpsState = ! $position ? 'awaiting_gps' : ($reportedFix === false ? 'no_gps_fix' : (! $fresh ? 'stale_gps' : 'fresh'));
        // Parking is an ignition/movement state, independent of the age of the
        // last coordinate. A fresh measured moving fix still takes precedence.
        $state = $device->status !== 'active' ? $device->status : (! $online ? 'offline' :
            ($gpsState === 'fresh' && $position->speed > 1.8 && ! ($acc === false && $heartbeatNewer) ? 'moving' : ($acc === false ? 'parked' :
                ($gpsState !== 'fresh' ? $gpsState : ($position->speed === null ? 'unknown' : ($acc === true ? 'idle' : 'stopped'))))));

        return [...($position ? HardwareLocationUpdated::payload($device, $position) :
            ['latitude' => null, 'longitude' => null, 'recorded_at' => null, 'received_at' => null, 'speed' => null, 'heading' => null]),
            'device_id' => $device->id, 'imei' => $device->identifier, 'name' => $device->name,
            'protocol' => $device->protocol, 'model' => $device->model,
            'vehicle_id' => $device->vehicle_id, 'registration' => $device->vehicle?->registration_number,
            'vehicle_type' => strtolower($device->vehicle?->details['type'] ?? 'vehicle'),
            'driver_name' => $device->vehicle?->details['driver_name'] ?? null,
            'authorization' => $device->status, 'online' => $online, 'state' => $state,
            ...$presence,
            'last_packet_at' => $lastPacket?->toIso8601String(), 'acc' => $acc,
            'acc_reported' => $reportedAcc, 'acc_reported_at' => $accReportedAt?->toIso8601String(),
            'acc_source' => $acc === null && $reportedAcc === null ? null : ($accFromHeartbeat ? 'heartbeat' : 'location'),
            'gps_state' => $gpsState, 'observed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'gps_reported_at' => $heartbeat ? CarbonImmutable::parse($heartbeat->received_at, 'UTC')->toIso8601String() : null,
            'heartbeat_sensors' => HardwareSensorData::forDisplay($telemetry ?? []),
            'heartbeat_reported_at' => $heartbeat ? CarbonImmutable::parse($heartbeat->received_at, 'UTC')->toIso8601String() : null,
            'gps_fresh' => (bool) $fresh && $reportedFix !== false, 'gps_positioned_reported' => $reportedFix,
            'gps_fresh_seconds' => config('gps.fresh_seconds', 15), 'idle_seconds' => config('gps.idle_seconds'),
            'reconnect_seconds' => config('gps.reconnect_seconds', 30)];
    }
}
