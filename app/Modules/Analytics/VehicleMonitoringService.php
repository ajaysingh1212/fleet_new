<?php

namespace App\Modules\Analytics;

use App\Models\GpsDeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VehicleMonitoringService
{
    public function visibleVehicles(User $user): Builder
    {
        return Vehicle::query()->with('gpsDevice:id,vehicle_id,status,last_packet_at')
            ->when(! $user->hasAnyRole(['Super Admin', 'Admin']), fn ($q) => $q->whereHas('assignedUsers', fn ($u) => $u->whereKey($user->id)))
            ->orderBy('registration_number');
    }

    public function filters(Request $request): array
    {
        $vehicles = $this->visibleVehicles($request->user())->get();
        if ($request->filled('vehicle_id')) {
            $vehicle = $vehicles->firstWhere('id', (int) $request->input('vehicle_id'));
            abort_unless($vehicle, 403);
            return [$vehicles, $vehicle];
        }
        return [$vehicles, null];
    }

    public function summary(Vehicle $vehicle, Request $request): array
    {
        [$from, $to] = $this->range($request);
        $points = GpsDeviceLocation::query()->where('vehicle_id', $vehicle->id)
            ->where('gps_fix', true)->whereBetween('recorded_at', [$from, $to])->orderBy('recorded_at')->get();
        $distance = 0.0; $moving = 0; $idle = 0;
        for ($i = 1; $i < $points->count(); $i++) {
            $seconds = max(0, $points[$i - 1]->recorded_at->diffInSeconds($points[$i]->recorded_at));
            $distance += $this->distance($points[$i - 1], $points[$i]);
            if (($points[$i]->speed ?? 0) > 0.5) $moving += $seconds; else $idle += $seconds;
        }
        $speeds = $points->pluck('speed')->filter(fn ($v) => $v !== null);
        return ['vehicle_id' => $vehicle->id, 'registration_number' => $vehicle->registration_number,
            'name' => $vehicle->name, 'status' => $vehicle->status, 'point_count' => $points->count(),
            'total_distance_km' => round($distance / 1000, 2), 'moving_seconds' => $moving,
            'idle_seconds' => $idle, 'average_speed_kmh' => $speeds->isNotEmpty() ? round($speeds->avg() * 3.6, 1) : null,
            'maximum_speed_kmh' => $speeds->isNotEmpty() ? round($speeds->max() * 3.6, 1) : null,
            'last_seen' => $points->last()?->recorded_at?->toIso8601String()];
    }

    public function replay(Vehicle $vehicle, Request $request): array
    {
        [$from, $to] = $this->range($request);
        $points = GpsDeviceLocation::query()->where('vehicle_id', $vehicle->id)->where('gps_fix', true)
            ->whereBetween('recorded_at', [$from, $to])->orderBy('recorded_at')->limit(5000)->get();
        return ['vehicle' => ['id' => $vehicle->id, 'name' => $vehicle->name, 'registration_number' => $vehicle->registration_number],
            'statistics' => $this->summary($vehicle, $request), 'points' => $points->map(fn ($p) => [
                'latitude' => (float) $p->latitude, 'longitude' => (float) $p->longitude, 'speed' => $p->speed,
                'bearing' => $p->heading, 'recorded_at' => $p->recorded_at->toIso8601String(), 'is_stop' => ($p->speed ?? 0) <= 0.5,
            ])->all(), 'playback_speeds' => [1, 2, 4, 8, 16, 32]];
    }

    private function range(Request $request): array
    {
        $now = CarbonImmutable::now();
        return match ($request->input('preset', 'today')) {
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_30_days' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'custom' => [CarbonImmutable::parse($request->input('from', $now))->startOfDay(), CarbonImmutable::parse($request->input('to', $now))->endOfDay()],
            default => [$now->startOfDay(), $now->endOfDay()],
        };
    }

    private function distance(object $a, object $b): float
    {
        $lat1 = deg2rad($a->latitude); $lat2 = deg2rad($b->latitude);
        $dlat = $lat2 - $lat1; $dlon = deg2rad($b->longitude - $a->longitude);
        $h = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        return 12742000 * atan2(sqrt($h), sqrt(1 - $h));
    }
}
