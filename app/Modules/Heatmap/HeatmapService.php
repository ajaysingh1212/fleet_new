<?php

namespace App\Modules\Heatmap;

use App\Modules\History\LocationHistoryService;

class HeatmapService
{
    public function __construct(protected LocationHistoryService $history) {}

    public function generate(array $filters): array
    {
        $points = collect($this->history->points($filters))
            ->groupBy(fn ($point) => round($point['latitude'], 3).','.round($point['longitude'], 3))
            ->map(fn ($cluster, $key) => [
                'latitude' => round($cluster->avg('latitude'), 6),
                'longitude' => round($cluster->avg('longitude'), 6),
                'weight' => $cluster->count(),
                'last_seen_at' => $cluster->max('recorded_at'),
            ])
            ->sortByDesc('weight')
            ->values();

        return [
            'points' => $points->take(1000)->values()->all(),
            'summary' => [
                'clusters' => $points->count(),
                'high_activity_zones' => $points->take(10)->values()->all(),
                'low_activity_zones' => $points->reverse()->take(10)->values()->all(),
            ],
        ];
    }
}
