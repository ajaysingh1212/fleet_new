<?php

namespace App\Modules\Dashboard;

use App\Models\GeofenceEvent;
use App\Models\GpsLocation;
use App\Models\User;
use App\Modules\Analytics\MovementAnalyticsService;
use App\Modules\History\LocationHistoryService;

class MonitoringDashboardService
{
    public function __construct(
        protected LocationHistoryService $history,
        protected MovementAnalyticsService $analytics,
    ) {}

    public function overview(array $filters): array
    {
        $locations = $this->history->query($filters)->reorder('recorded_at')->get();
        $summary = $this->analytics->summarize($locations);
        [$from, $to] = $this->history->range($filters);

        return [
            'cards' => [
                'todays_distance_km' => $summary['total_distance_km'],
                'working_hours' => round(($summary['moving_seconds'] + $summary['idle_seconds']) / 3600, 2),
                'idle_hours' => round($summary['idle_seconds'] / 3600, 2),
                'trips' => max(0, (int) ceil($summary['moving_seconds'] / 1800)),
                'geofence_visits' => GeofenceEvent::query()
                    ->whereBetween('occurred_at', [$from, $to])
                    ->where('type', 'entered')
                    ->when(! empty($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
                    ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('user_id', $filters['allowed_user_ids']))
                    ->count(),
                'current_status' => $locations->last() ? 'tracking' : 'offline',
                'battery' => $locations->last()?->battery_level,
                'gps_accuracy' => $summary['average_accuracy'],
                'network_quality' => $locations->last()?->network_type,
            ],
            'charts' => [
                'daily_distance' => $summary['total_distance_km'],
                'movement_trends' => [
                    'moving_seconds' => $summary['moving_seconds'],
                    'idle_seconds' => $summary['idle_seconds'],
                ],
            ],
            'leaderboards' => [
                'top_distance' => $this->topDistance($filters),
                'least_idle_time' => [],
            ],
        ];
    }

    private function topDistance(array $filters): array
    {
        [$from, $to] = $this->history->range($filters);

        return User::query()
            ->select('id', 'name')
            ->when(! empty($filters['user_id']), fn ($query) => $query->where('id', $filters['user_id']))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('id', $filters['allowed_user_ids']))
            ->with(['gpsLocations' => fn ($query) => $query->whereBetween('recorded_at', [$from, $to])->orderBy('recorded_at')])
            ->limit(10)
            ->get()
            ->map(fn (User $user) => [
                'user_id' => $user->id,
                'name' => $user->name,
                'distance_km' => $this->analytics->summarize($user->gpsLocations)['total_distance_km'],
            ])
            ->sortByDesc('distance_km')
            ->values()
            ->all();
    }
}
