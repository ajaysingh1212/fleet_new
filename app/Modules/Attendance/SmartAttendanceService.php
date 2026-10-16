<?php

namespace App\Modules\Attendance;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Modules\History\LocationHistoryService;
use Carbon\CarbonImmutable;

class SmartAttendanceService
{
    public function __construct(protected LocationHistoryService $history) {}

    public function summarize(array $filters): array
    {
        $locations = $this->history->query($filters)->reorder('recorded_at')->get();
        $userId = $filters['user_id'] ?? null;
        [$from, $to] = $this->history->range($filters);

        $officeIds = Geofence::query()->whereIn('category', ['office', 'branch'])->pluck('id');
        $visits = GeofenceEvent::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('user_id', $filters['allowed_user_ids']))
            ->whereIn('geofence_id', $officeIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get();

        $first = $visits->first(fn ($visit) => $visit->type->value === 'entered')?->occurred_at ?? $locations->first()?->recorded_at;
        $last = $visits->filter(fn ($visit) => $visit->type->value === 'exited')->last()?->occurred_at ?? $locations->last()?->recorded_at;
        $workingSeconds = $first && $last ? max(0, CarbonImmutable::parse($first)->diffInSeconds(CarbonImmutable::parse($last))) : 0;

        return [
            'first_check_in_at' => $first?->toIso8601String(),
            'last_check_out_at' => $last?->toIso8601String(),
            'working_seconds' => $workingSeconds,
            'working_hours' => round($workingSeconds / 3600, 2),
            'status' => $locations->isEmpty() ? 'absent' : ($workingSeconds >= 14400 ? 'present' : 'half_day'),
            'late_arrival' => $first ? CarbonImmutable::parse($first)->format('H:i') > '09:15' : null,
            'early_exit' => $last ? CarbonImmutable::parse($last)->format('H:i') < '18:00' : null,
            'source' => 'office_geofence_gps_presence',
        ];
    }
}
