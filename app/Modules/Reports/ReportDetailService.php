<?php

namespace App\Modules\Reports;

use App\Enums\DiagnosticEventType;
use App\Models\DiagnosticLog;
use App\Models\GeofenceEvent;
use App\Modules\History\LocationHistoryService;
use Illuminate\Support\Collection;

class ReportDetailService
{
    public function __construct(protected LocationHistoryService $history) {}

    /**
     * Full device diagnostic timeline for the report window, plus paired
     * outage windows (off -> on) with the battery/network snapshot captured
     * at each end, e.g. "internet dropped at 14:02 (battery 41%), back at 14:19".
     */
    public function deviceDiagnostics(array $filters): array
    {
        [$from, $to] = $this->history->range($filters);

        $events = DiagnosticLog::query()
            ->when(! empty($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('user_id', $filters['allowed_user_ids']))
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->limit(500)
            ->get();

        return [
            'events' => $events->map(fn (DiagnosticLog $event) => [
                'type' => $event->event_type->value,
                'label' => $event->event_type->label(),
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'battery_level' => $event->battery_level,
                'network_type' => $event->network_type,
                'reason' => $event->reason,
            ])->values()->all(),
            'internet_outages' => $this->pairEvents($events, DiagnosticEventType::InternetOff, DiagnosticEventType::InternetOn),
            'gps_outages' => $this->pairEvents($events, DiagnosticEventType::GpsDisabled, DiagnosticEventType::GpsEnabled),
        ];
    }

    /**
     * @param  Collection<int, DiagnosticLog>  $events
     * @return array<int, array<string, mixed>>
     */
    private function pairEvents(Collection $events, DiagnosticEventType $offType, DiagnosticEventType $onType): array
    {
        $pairs = [];
        $open = null;

        foreach ($events as $event) {
            if ($event->event_type === $offType) {
                $open = $event;

                continue;
            }

            if ($event->event_type === $onType && $open) {
                $pairs[] = [
                    'off_at' => $open->occurred_at->toIso8601String(),
                    'on_at' => $event->occurred_at->toIso8601String(),
                    'duration_seconds' => abs($event->occurred_at->diffInSeconds($open->occurred_at)),
                    'battery_at_off' => $open->battery_level,
                    'battery_at_on' => $event->battery_level,
                    'network_at_off' => $open->network_type,
                ];
                $open = null;
            }
        }

        if ($open) {
            $pairs[] = [
                'off_at' => $open->occurred_at->toIso8601String(),
                'on_at' => null,
                'duration_seconds' => abs(now()->diffInSeconds($open->occurred_at)),
                'battery_at_off' => $open->battery_level,
                'battery_at_on' => null,
                'network_at_off' => $open->network_type,
            ];
        }

        return $pairs;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function geofenceActivity(array $filters): array
    {
        [$from, $to] = $this->history->range($filters);

        return GeofenceEvent::query()
            ->with('geofence:id,name,category')
            ->when(! empty($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('user_id', $filters['allowed_user_ids']))
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->limit(300)
            ->get()
            ->map(fn (GeofenceEvent $event) => [
                'geofence_name' => $event->geofence?->name,
                'category' => $event->geofence?->category?->value,
                'type' => $event->type->value,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
