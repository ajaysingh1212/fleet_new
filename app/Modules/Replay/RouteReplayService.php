<?php

namespace App\Modules\Replay;

use App\Modules\Analytics\MovementAnalyticsService;
use App\Modules\History\LocationHistoryService;
use Illuminate\Support\Collection;

class RouteReplayService
{
    public function __construct(
        protected LocationHistoryService $history,
        protected MovementAnalyticsService $analytics,
    ) {}

    public function build(array $filters): array
    {
        $locations = $this->history->query($filters)->reorder('recorded_at')->limit(5000)->get();

        return [
            'points' => $this->smooth($locations),
            'statistics' => $this->analytics->summarize($locations),
            'playback_speeds' => [1, 2, 4, 8, 16, 32],
            'options' => ['live_follow' => true, 'auto_zoom' => true, 'manual_zoom' => true],
        ];
    }

    private function smooth(Collection $locations): array
    {
        return $locations->values()->map(function ($location, int $index) use ($locations) {
            $previous = $index > 0 ? $locations[$index - 1] : null;

            return [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'speed' => $location->speed !== null ? round((float) $location->speed, 2) : null,
                'bearing' => $this->smoothedBearing($previous?->bearing, $location->bearing),
                'accuracy' => $location->accuracy !== null ? (float) $location->accuracy : null,
                'recorded_at' => $location->recorded_at->toIso8601String(),
                'is_stop' => (float) ($location->speed ?? 0) <= 0.5,
            ];
        })->all();
    }

    private function smoothedBearing(mixed $previous, mixed $current): ?float
    {
        if ($current === null) {
            return null;
        }

        if ($previous === null) {
            return round((float) $current, 2);
        }

        return round((((float) $previous) * 0.35) + (((float) $current) * 0.65), 2);
    }
}
