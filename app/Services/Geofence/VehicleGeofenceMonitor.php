<?php

namespace App\Services\Geofence;

use App\Events\VehicleGeofenceAlertChanged;
use App\Models\Geofence;
use App\Models\GpsDevice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class VehicleGeofenceMonitor
{
    public function evaluate(GpsDevice $device, array $location): void
    {
        if (! $location['gps_fix']) {
            return;
        }
        $time = CarbonImmutable::parse($location['recorded_at'], 'UTC');
        if ($time->lt(now()->subSeconds(config('gps.idle_seconds')))) {
            return;
        }
        $rules = DB::table('vehicle_geofence_rules')->where(function ($q) use ($device) {
            $q->where('gps_device_id', $device->id);
            if ($device->vehicle_id) {
                $q->orWhere(fn ($v) => $v->whereNull('gps_device_id')->where('vehicle_id', $device->vehicle_id));
            }
        })->get();
        foreach ($rules as $rule) {
            DB::transaction(function () use ($rule, $device, $location, $time) {
                // Serialize evaluation with edits, pauses and vehicle changes.
                $rule = DB::table('vehicle_geofence_rules')->where('id', $rule->id)->lockForUpdate()->first();
                if (! $rule || ($rule->gps_device_id ? (int) $rule->gps_device_id !== (int) $device->id : (! $device->vehicle_id || (int) $rule->vehicle_id !== (int) $device->vehicle_id))) {
                    return;
                }
                $fence = Geofence::with('points')->active()->find($rule->geofence_id);
                if (! $fence || $time->lt($rule->updated_at)) {
                    return;
                }
                DB::table('vehicle_geofence_states')->insertOrIgnore(['rule_id' => $rule->id]);
                $state = DB::table('vehicle_geofence_states')->where('rule_id', $rule->id)->lockForUpdate()->first();
                if ($state->recorded_at && $time->lte($state->recorded_at)) {
                    return;
                }
                $outside = ! app(GeofenceGeometryService::class)->containsPoint($fence, $location['latitude'], $location['longitude']);
                $speed = $location['speed'];
                $speedState = $speed !== null && $fence->max_speed_kmh !== null && $speed > $fence->max_speed_kmh ? 'overspeed'
                    : ($speed !== null && $speed > 0 && $fence->min_speed_kmh !== null && $speed < $fence->min_speed_kmh ? 'underspeed' : null);
                if ($speed === null) {
                    $speedState = $state->speed_state && DB::table('vehicle_geofence_alerts')
                        ->where('rule_id', $rule->id)->where('gps_device_id', $device->id)
                        ->where('type', $state->speed_state)->whereNull('ended_at')->exists() ? $state->speed_state : null;
                }
                $changed = false;
                foreach (['outside' => $outside, 'overspeed' => $speedState === 'overspeed', 'underspeed' => $speedState === 'underspeed'] as $type => $active) {
                    $current = DB::table('vehicle_geofence_alerts')->where('rule_id', $rule->id)->where('type', $type)->whereNull('ended_at');
                    $existing = $current->first();
                    // Reassignment must not carry an old tracker's active episode.
                    if ($existing && (! $active || $existing->gps_device_id !== $device->id)) {
                        $current->update(['ended_at' => $time]);
                        $existing = null;
                        $changed = true;
                    }
                    if ($active && ! $existing) {
                        DB::table('vehicle_geofence_alerts')->insert(['rule_id' => $rule->id, 'vehicle_id' => $device->vehicle_id,
                            'gps_device_id' => $device->id, 'type' => $type, 'started_at' => $time, 'latitude' => $location['latitude'],
                            'longitude' => $location['longitude'], 'speed' => $speed, 'limit' => $type === 'overspeed' ? $fence->max_speed_kmh : ($type === 'underspeed' ? $fence->min_speed_kmh : null)]);
                        $changed = true;
                    }
                }
                DB::table('vehicle_geofence_states')->where('id', $state->id)->update(['recorded_at' => $time, 'outside' => $outside, 'speed_state' => $speedState]);
                if ($changed) {
                    DB::afterCommit(fn () => VehicleGeofenceAlertChanged::dispatch(['device_id' => $device->id,
                        'vehicle_id' => $device->vehicle_id, 'imei' => $device->identifier, 'registration' => $device->vehicle?->registration_number,
                        'active_alerts' => $this->alerts($device->id), 'occurred_at' => $time->toIso8601String(),
                        'observed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z')]));
                }
            });
        }
    }

    public function alerts(int $deviceId): array
    {
        return DB::table('vehicle_geofence_alerts as a')->join('vehicle_geofence_rules as r', 'r.id', '=', 'a.rule_id')
            ->join('geofences as g', 'g.id', '=', 'r.geofence_id')->where('a.gps_device_id', $deviceId)->whereNull('a.ended_at')
            ->whereNull('g.deleted_at')->where('g.status', 'active')->select('a.*', 'g.name as geofence_name')->orderByDesc('a.started_at')->limit(100)->get()->all();
    }
}
