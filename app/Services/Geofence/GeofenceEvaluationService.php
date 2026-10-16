<?php

namespace App\Services\Geofence;

use App\Enums\DiagnosticEventType;
use App\Enums\GeofenceEventType;
use App\Enums\UserStatus;
use App\Events\GeofenceEventOccurred;
use App\Events\GeofenceOverspeedDetected;
use App\Models\DiagnosticLog;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\GpsLocation;
use App\Models\User;
use App\Notifications\DiagnosticAlertNotification;

class GeofenceEvaluationService
{
    private const OVERSPEED_ALERT_THROTTLE_MINUTES = 5;

    private const OUTSIDE_ALERT_REPEAT_MINUTES = 1;

    public function __construct(
        protected GeofenceGeometryService $geometry,
        protected GeofenceAssignmentComplianceService $compliance,
    ) {}

    public function evaluate(GpsLocation $location): void
    {
        $geofences = Geofence::active()
            ->whereHas('assignments', fn ($query) => $query
                ->active()
                ->where('user_id', $location->user_id))
            ->with('points')
            ->get();

        if ($geofences->isEmpty()) {
            return;
        }

        $latestEvents = GeofenceEvent::query()
            ->where('user_id', $location->user_id)
            ->whereIn('geofence_id', $geofences->pluck('id'))
            ->orderByDesc('occurred_at')
            ->get()
            ->groupBy('geofence_id')
            ->map(fn ($events) => $events->first());

        foreach ($geofences as $geofence) {
            $this->evaluateGeofence($geofence, $location, $latestEvents->get($geofence->id));
        }
    }

    private function evaluateGeofence(Geofence $geofence, GpsLocation $location, ?GeofenceEvent $lastEvent): void
    {
        if ($lastEvent && $lastEvent->occurred_at->gte($location->recorded_at)) {
            return;
        }
        $isInside = $this->geometry->containsPoint($geofence, (float) $location->latitude, (float) $location->longitude);
        $wasInside = $lastEvent?->type === GeofenceEventType::Entered;

        if (! $isInside && $lastEvent?->type === GeofenceEventType::Exited) {
            if ($lastEvent->occurred_at->gt($location->recorded_at->clone()->subMinutes(self::OUTSIDE_ALERT_REPEAT_MINUTES))) {
                return;
            }

            $this->recordEvent($geofence, $location, GeofenceEventType::Exited);

            return;
        }

        if (! $isInside && $lastEvent === null) {
            $this->recordEvent($geofence, $location, GeofenceEventType::Exited);

            return;
        }

        if ($isInside === $wasInside) {
            if ($isInside) {
                $this->checkOverspeed($geofence, $location);
            }

            return;
        }

        $this->recordEvent($geofence, $location, $isInside ? GeofenceEventType::Entered : GeofenceEventType::Exited);

        if ($isInside) {
            $this->checkOverspeed($geofence, $location);
        }
    }

    private function recordEvent(Geofence $geofence, GpsLocation $location, GeofenceEventType $type): void
    {
        $event = GeofenceEvent::create([
            'geofence_id' => $geofence->id,
            'user_id' => $location->user_id,
            'gps_location_id' => $location->exists ? $location->id : null,
            'type' => $type,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'occurred_at' => $location->recorded_at,
        ]);

        broadcast(new GeofenceEventOccurred($event->setRelation('geofence', $geofence)));

        $this->compliance->handleGeofenceEvent($event, $geofence);
    }

    /**
     * Fires once per throttle window (not on every ping) so a sustained
     * overspeed episode doesn't spam a notification per GPS fix.
     */
    private function checkOverspeed(Geofence $geofence, GpsLocation $location): void
    {
        if ($geofence->max_speed_kmh === null || $location->speed === null) {
            return;
        }

        $speedKmh = (float) $location->speed * 3.6;

        if ($speedKmh <= $geofence->max_speed_kmh) {
            return;
        }

        $recentlyAlerted = DiagnosticLog::query()
            ->where('user_id', $location->user_id)
            ->where('event_type', DiagnosticEventType::Overspeed)
            ->where('occurred_at', '>=', $location->recorded_at->clone()->subMinutes(self::OVERSPEED_ALERT_THROTTLE_MINUTES))
            ->exists();

        if ($recentlyAlerted) {
            return;
        }

        $log = DiagnosticLog::create([
            'user_id' => $location->user_id,
            'device_session_id' => $location->device_session_id,
            'event_type' => DiagnosticEventType::Overspeed,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'reason' => sprintf('%s: %.1f km/h (limit %d km/h)', $geofence->name, $speedKmh, $geofence->max_speed_kmh),
            'occurred_at' => $location->recorded_at,
        ]);

        broadcast(new GeofenceOverspeedDetected($geofence, $log, $speedKmh));

        $this->notifyOverspeed($log);
    }

    /**
     * Both sides get a persisted/pushed alert (not just the live-map toast),
     * so it still reaches a tracker whose Live Map tab isn't open.
     */
    private function notifyOverspeed(DiagnosticLog $log): void
    {
        $trackedUser = User::find($log->user_id);

        if (! $trackedUser) {
            return;
        }

        $trackerIds = $trackedUser->trackerRelations()->where('status', UserStatus::Active)->pluck('tracker_user_id');

        User::query()->whereIn('id', $trackerIds)->get()
            ->push($trackedUser)
            ->each(fn (User $recipient) => $recipient->notify(new DiagnosticAlertNotification($log, $trackedUser)));
    }
}
