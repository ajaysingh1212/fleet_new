<?php

namespace App\Services\Gps;

use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Small current-state cache; SQL remains the durable history, never the live clock. */
final class HardwareLiveState
{
    private function store()
    {
        return Cache::store(config('gps.live_cache_store'));
    }

    private function key(int $id): string
    {
        return 'gps.live.device.'.$id;
    }

    public function forget(int $id): void
    {
        $this->store()->forget($this->key($id));
    }

    public function baseline(GpsDevice $device, HardwareFleet $fleet): array
    {
        $cached = $this->store()->get($this->key($device->id));
        if ($cached && $cached['imei'] === $device->identifier && $cached['vehicle_id'] === $device->vehicle_id
            && ($cached['protocol'] ?? null) === $device->protocol) {
            // Warm path reads administrative authorization, not position/history.
            return [...$cached, 'authorization' => $device->status, 'name' => $device->name,
                'registration' => $device->vehicle?->registration_number,
                'driver_name' => $device->vehicle?->details['driver_name'] ?? null,
                'vehicle_type' => strtolower($device->vehicle?->details['type'] ?? 'vehicle'),
                'vehicle_icon' => config('fleet.icons.'.($device->vehicle?->details['icon'] ?? $device->vehicle?->details['type'] ?? 'car').'.icon', 'fa-car-side')];
        }

        return $fleet->device($device);
    }

    public function overlay(array $base): array
    {
        $cached = $this->store()->get($this->key($base['device_id']));
        if ($cached && $this->sameAssignment($base, $cached)) {
            $base = $this->merge($base, $cached);
        }

        return $this->classify($base);
    }

    public function project(array $base, ProcessHardwarePacket $packet, string $receivedAt): array
    {
        return $this->store()->lock($this->key($packet->deviceId).'.lock', 10)->block(3, function () use ($base, $packet, $receivedAt) {
            $state = $this->overlay($base);
            $incoming = ['last_packet_at' => $receivedAt, 'live_received_at' => $receivedAt];
            foreach ($packet->locations as $location) {
                if (! $location['gps_fix']) {
                    continue;
                }
                $time = CarbonImmutable::parse($location['recorded_at'], 'UTC')->toIso8601String();
                if (! empty($incoming['recorded_at']) && $incoming['recorded_at'] >= $time) {
                    continue;
                }
                $incoming = [...$incoming, 'recorded_at' => $time, 'received_at' => $receivedAt,
                    'trace_id' => $packet->traceId ?? null,
                    'latitude' => $location['latitude'], 'longitude' => $location['longitude'],
                    'speed' => $location['speed'], 'heading' => $location['heading'],
                    'altitude' => $location['altitude'] ?? null, 'satellites' => $location['satellites'] ?? null,
                    'sensors' => HardwareSensorData::forDisplay($location['telemetry'] ?? [])];
                if (isset($location['telemetry']['acc'])) {
                    $incoming = [...$incoming, 'acc_reported' => $location['telemetry']['acc'], 'acc_reported_at' => $time, 'acc_source' => 'location'];
                }
            }
            if ($packet->type === 'heartbeat') {
                $incoming['heartbeat_sensors'] = HardwareSensorData::forDisplay($packet->events);
                $incoming['heartbeat_reported_at'] = $receivedAt;
                // Power is a separate current attribute.  In particular, do
                // not use a stationary/charging tracker as evidence that it
                // is offline, and do not infer external power from battery
                // level when the protocol has not reported it.
                $power = HardwareSensorData::forDisplay($packet->events);
                $incoming['power_sensors'] = $power;
                $incoming['power_reported_at'] = $receivedAt;
                foreach (['charging', 'external_power', 'external_voltage', 'battery_voltage', 'power_level', 'power_description'] as $field) {
                    if (array_key_exists($field, $power)) {
                        $incoming[$field] = $power[$field];
                    }
                }
                if (isset($packet->events['acc'])) {
                    $incoming = [...$incoming, 'acc_reported' => $packet->events['acc'], 'acc_reported_at' => $receivedAt, 'acc_source' => 'heartbeat'];
                }
                if (isset($packet->events['gps_positioned_reported'])) {
                    $incoming = [...$incoming, 'gps_positioned_reported' => $packet->events['gps_positioned_reported'], 'gps_reported_at' => $receivedAt];
                }
            }
            $state = $this->merge($state, $incoming);
            if ($this->time($receivedAt) >= $this->time($state['live_received_at'] ?? null)) {
                $state['projected_at'] = now()->utc()->format('Y-m-d\TH:i:s.u\Z');
                $state['queue_delay_ms'] = max(0, (int) round(((float) now()->format('U.u') - $this->time($receivedAt)) * 1000));
            }
            $state['live_revision'] = ($state['live_revision'] ?? 0) + 1;
            $state = $this->classify($state);
            $this->store()->put($this->key($packet->deviceId), $state, now()->addDay());

            return $state;
        });
    }

    private function sameAssignment(array $a, array $b): bool
    {
        return ($a['imei'] ?? null) === ($b['imei'] ?? null) && $a['vehicle_id'] === $b['vehicle_id']
            && ($a['protocol'] ?? null) === ($b['protocol'] ?? null);
    }

    private function time(?string $value): float
    {
        return $value ? (float) CarbonImmutable::parse($value)->format('U.u') : 0;
    }

    private function merge(array $base, array $incoming): array
    {
        foreach ([['last_packet_at', ['last_packet_at']],
            ['recorded_at', ['latitude', 'longitude', 'speed', 'heading', 'recorded_at', 'received_at', 'altitude', 'satellites', 'sensors', 'trace_id']],
            ['heartbeat_reported_at', ['heartbeat_sensors', 'heartbeat_reported_at']],
            ['power_reported_at', ['power_sensors', 'power_reported_at', 'charging', 'external_power', 'external_voltage',
                'battery_voltage', 'power_level', 'power_description']],
            ['acc_reported_at', ['acc_reported', 'acc_reported_at', 'acc_source']],
            ['gps_reported_at', ['gps_positioned_reported', 'gps_reported_at']],
            ['live_received_at', ['live_received_at', 'live_revision', 'projected_at', 'queue_delay_ms']]] as [$clock, $fields]) {
            if (! empty($incoming[$clock]) && $this->time($incoming[$clock]) >= $this->time($base[$clock] ?? null)) {
                foreach ($fields as $field) {
                    if (array_key_exists($field, $incoming)) {
                        $base[$field] = $incoming[$field];
                    }
                }
            }
        }

        return $base;
    }

    private function classify(array $p): array
    {
        $availabilitySeconds = max(30, (int) ($p['idle_seconds'] ?? config('gps.idle_seconds')));
        $cutoff = (float) now()->subSeconds($availabilitySeconds)->format('U.u');
        $p = [...$p, ...app(HardwareConnectionState::class)->snapshot($p['device_id'])];
        if ($this->time($p['connection_last_packet_at'] ?? null) > $this->time($p['last_packet_at'] ?? null)) {
            $p['last_packet_at'] = $p['connection_last_packet_at'];
        }
        $p['reconnect_seconds'] = config('gps.reconnect_seconds', 30);
        $p['idle_seconds'] = $availabilitySeconds;
        $p['online'] = $p['authorization'] === 'active' && $this->time($p['last_packet_at'] ?? null) >= $cutoff;
        $p['acc'] = $this->time($p['acc_reported_at'] ?? null) >= $cutoff ? ($p['acc_reported'] ?? null) : null;
        if (isset($p['gps_reported_at']) && $this->time($p['gps_reported_at']) < $cutoff) {
            $p['gps_positioned_reported'] = null;
        }
        if ($this->time($p['gps_reported_at'] ?? null) < $this->time($p['recorded_at'] ?? null)) {
            $p['gps_positioned_reported'] = null;
        }
        $powerFresh = $this->time($p['power_reported_at'] ?? null) >= $cutoff;
        if (! $powerFresh) {
            // Preserve historical values for diagnostics, but do not present
            // a stale power report as the present charging state.
            $p['charging_current'] = null;
            $p['external_power_current'] = null;
        } else {
            $p['charging_current'] = $p['charging'] ?? null;
            $p['external_power_current'] = $p['external_power'] ?? null;
        }
        $p['gps_fresh_seconds'] = config('gps.fresh_seconds', 15);
        $fresh = ! empty($p['recorded_at']) && $this->time($p['recorded_at']) >= (float) now()->subSeconds($p['gps_fresh_seconds'])->format('U.u');
        $p['gps_state'] = empty($p['recorded_at']) ? 'awaiting_gps' : (($p['gps_positioned_reported'] ?? null) === false ? 'no_gps_fix' : ($fresh ? 'fresh' : 'stale_gps'));
        $p['gps_fresh'] = $p['gps_state'] === 'fresh';
        $p['state'] = $p['authorization'] !== 'active' ? $p['authorization'] : (! $p['online'] ? 'offline' :
            ($p['gps_fresh'] && ($p['speed'] ?? 0) > 1.8
                && ! ($p['acc'] === false && $this->time($p['acc_reported_at'] ?? null) > $this->time($p['recorded_at'] ?? null)) ? 'moving' : ($p['acc'] === false ? 'parked' :
                (! $p['gps_fresh'] ? $p['gps_state'] : (($p['speed'] ?? null) === null ? 'unknown' : ($p['acc'] === true ? 'idle' : 'stopped'))))));
        $p['observed_at'] = now()->utc()->format('Y-m-d\TH:i:s.u\Z');

        return $p;
    }
}
