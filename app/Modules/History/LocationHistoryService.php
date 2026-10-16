<?php

namespace App\Modules\History;

use App\Models\GpsLocation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LocationHistoryService
{
    public function query(array $filters): Builder
    {
        $query = GpsLocation::query()->with('user:id,name')->latest('recorded_at');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['allowed_user_ids'])) {
            $query->whereIn('user_id', $filters['allowed_user_ids']);
        }

        if (! empty($filters['device_session_id'])) {
            $query->where('device_session_id', $filters['device_session_id']);
        }

        [$from, $to] = $this->range($filters);
        $query->whereBetween('recorded_at', [$from, $to]);

        if (isset($filters['min_speed'])) {
            $query->where('speed', '>=', $filters['min_speed']);
        }

        if (isset($filters['max_speed'])) {
            $query->where('speed', '<=', $filters['max_speed']);
        }

        return $query;
    }

    public function paginate(array $filters, int $perPage = 100): LengthAwarePaginator
    {
        return $this->query($filters)->paginate(min($perPage, 500));
    }

    public function points(array $filters): array
    {
        return $this->query($filters)
            ->oldest('recorded_at')
            ->limit(5000)
            ->get()
            ->map(fn (GpsLocation $location) => [
                'id' => $location->id,
                'user_id' => $location->user_id,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'speed' => $location->speed !== null ? (float) $location->speed : null,
                'bearing' => $location->bearing !== null ? (float) $location->bearing : null,
                'accuracy' => $location->accuracy !== null ? (float) $location->accuracy : null,
                'battery_level' => $location->battery_level,
                'recorded_at' => $location->recorded_at->toIso8601String(),
            ])
            ->all();
    }

    public function range(array $filters): array
    {
        $preset = $filters['preset'] ?? 'today';
        $now = CarbonImmutable::now();

        return match ($preset) {
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_30_days' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'custom' => [
                CarbonImmutable::parse($filters['from'] ?? $now)->startOfDay(),
                CarbonImmutable::parse($filters['to'] ?? $now)->endOfDay(),
            ],
            default => [$now->startOfDay(), $now->endOfDay()],
        };
    }
}
