<?php

namespace App\Modules\Statistics;

use App\Modules\Analytics\MovementAnalyticsService;
use App\Modules\History\LocationHistoryService;

class StatisticsService
{
    public function __construct(
        protected LocationHistoryService $history,
        protected MovementAnalyticsService $analytics,
    ) {}

    public function averages(array $filters): array
    {
        $locations = $this->history->query($filters)->reorder('recorded_at')->get();
        $summary = $this->analytics->summarize($locations);

        return [
            'average_daily_distance_km' => $summary['total_distance_km'],
            'average_monthly_distance_km' => round($summary['total_distance_km'] * 30, 2),
            'average_idle_hours' => round($summary['idle_seconds'] / 3600, 2),
            'average_travel_hours' => round($summary['travel_time_seconds'] / 3600, 2),
            'average_working_hours' => round(($summary['moving_seconds'] + $summary['idle_seconds']) / 3600, 2),
            'average_speed_mps' => $summary['average_speed_mps'],
            'peak_activity_hours' => $locations->groupBy(fn ($location) => $location->recorded_at->format('H'))->map->count()->sortDesc()->take(3)->keys()->values()->all(),
        ];
    }
}
