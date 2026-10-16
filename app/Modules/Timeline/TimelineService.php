<?php

namespace App\Modules\Timeline;

use App\Models\DiagnosticLog;
use App\Models\GeofenceEvent;
use App\Models\GpsLocation;
use App\Models\Message;
use App\Modules\History\LocationHistoryService;

class TimelineService
{
    public function __construct(protected LocationHistoryService $history) {}

    public function events(array $filters): array
    {
        [$from, $to] = $this->history->range($filters);
        $userId = $filters['user_id'] ?? null;

        $gps = GpsLocation::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('user_id', $filters['allowed_user_ids']))
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->limit(300)
            ->get()
            ->map(fn ($location) => [
                'type' => ((float) ($location->speed ?? 0) > 0.5) ? 'movement' : 'stop',
                'title' => ((float) ($location->speed ?? 0) > 0.5) ? 'Movement' : 'Stop / Idle',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'occurred_at' => $location->recorded_at->toIso8601String(),
            ]);

        $geofences = GeofenceEvent::query()
            ->with('geofence:id,name')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('user_id', $filters['allowed_user_ids']))
            ->whereBetween('occurred_at', [$from, $to])
            ->limit(300)
            ->get()
            ->map(fn ($event) => [
                'type' => 'geofence_'.$event->type->value,
                'title' => ucfirst($event->type->value).' '.$event->geofence?->name,
                'latitude' => (float) $event->latitude,
                'longitude' => (float) $event->longitude,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ]);

        $diagnostics = DiagnosticLog::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('user_id', $filters['allowed_user_ids']))
            ->whereBetween('occurred_at', [$from, $to])
            ->limit(300)
            ->get()
            ->map(fn ($event) => [
                'type' => $event->event_type->value,
                'title' => str($event->event_type->value)->headline()->toString(),
                'description' => $event->reason,
                'battery_level' => $event->battery_level,
                'network_type' => $event->network_type,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ]);

        $messages = Message::query()
            ->when($userId, fn ($query) => $query->where('sender_id', $userId))
            ->when(! empty($filters['allowed_user_ids']), fn ($query) => $query->whereIn('sender_id', $filters['allowed_user_ids']))
            ->whereBetween('created_at', [$from, $to])
            ->limit(100)
            ->get()
            ->map(fn ($message) => [
                'type' => 'message',
                'title' => 'Message sent',
                'occurred_at' => $message->created_at->toIso8601String(),
            ]);

        // The mapped closures above return plain arrays, but a `map()` on an
        // Eloquent Collection can still stay an Eloquent Collection — whose
        // merge() assumes Models with getKey(). Rewrap as a base Collection
        // first so merge() does a plain array merge instead.
        return collect($gps->all())
            ->merge($geofences)
            ->merge($diagnostics)
            ->merge($messages)
            ->sortBy('occurred_at')
            ->values()
            ->all();
    }
}
