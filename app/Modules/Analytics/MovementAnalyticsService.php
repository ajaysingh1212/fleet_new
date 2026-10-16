<?php

namespace App\Modules\Analytics;

use App\Models\GpsLocation;
use Illuminate\Support\Collection;

class MovementAnalyticsService
{
    private const IDLE_SPEED_MPS = 0.5;

    public function summarize(Collection $locations): array
    {
        $locations = $locations->sortBy('recorded_at')->values();
        $distance = 0.0;
        $movingSeconds = 0;
        $idleSeconds = 0;

        for ($i = 1; $i < $locations->count(); $i++) {
            $previous = $locations[$i - 1];
            $current = $locations[$i];
            $seconds = max(0, $previous->recorded_at->diffInSeconds($current->recorded_at));
            $distance += $this->distanceMeters($previous, $current);

            if ((float) ($current->speed ?? 0) > self::IDLE_SPEED_MPS) {
                $movingSeconds += $seconds;
            } else {
                $idleSeconds += $seconds;
            }
        }

        $speeds = $locations->pluck('speed')->filter(fn ($speed) => $speed !== null)->map(fn ($speed) => (float) $speed);
        $accuracies = $locations->pluck('accuracy')->filter(fn ($accuracy) => $accuracy !== null)->map(fn ($accuracy) => (float) $accuracy);

        return [
            'point_count' => $locations->count(),
            'total_distance_meters' => (int) round($distance),
            'total_distance_km' => round($distance / 1000, 2),
            'average_speed_mps' => $speeds->isNotEmpty() ? round($speeds->avg(), 2) : null,
            'maximum_speed_mps' => $speeds->isNotEmpty() ? round($speeds->max(), 2) : null,
            'minimum_speed_mps' => $speeds->isNotEmpty() ? round($speeds->min(), 2) : null,
            'moving_seconds' => $movingSeconds,
            'idle_seconds' => $idleSeconds,
            'travel_time_seconds' => $movingSeconds,
            'stopped_time_seconds' => $idleSeconds,
            'average_accuracy' => $accuracies->isNotEmpty() ? round($accuracies->avg(), 2) : null,
            'route_efficiency' => $this->routeEfficiency($locations, $distance),
        ];
    }

    public function distanceMeters(GpsLocation $a, GpsLocation $b): float
    {
        $earth = 6371000;
        $latA = deg2rad((float) $a->latitude);
        $latB = deg2rad((float) $b->latitude);
        $deltaLat = deg2rad((float) $b->latitude - (float) $a->latitude);
        $deltaLng = deg2rad((float) $b->longitude - (float) $a->longitude);

        $haversine = sin($deltaLat / 2) ** 2 + cos($latA) * cos($latB) * sin($deltaLng / 2) ** 2;

        return 2 * $earth * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }

    private function routeEfficiency(Collection $locations, float $distance): ?float
    {
        if ($locations->count() < 2 || $distance <= 0) {
            return null;
        }

        $direct = $this->distanceMeters($locations->first(), $locations->last());

        return round(min(100, ($direct / $distance) * 100), 2);
    }
}
