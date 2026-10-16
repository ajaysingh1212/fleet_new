<?php

namespace App\Services\Geofence;

use App\Enums\GeofenceAssignmentRunStatus;
use App\Enums\GeofenceEventType;
use App\Models\Geofence;
use App\Models\GeofenceAssignment;
use App\Models\GeofenceAssignmentRun;
use App\Models\GeofenceEvent;
use App\Notifications\GeofenceComplianceNotification;
use Carbon\CarbonImmutable;

class GeofenceAssignmentComplianceService
{
    public function __construct(
        protected GeofenceAssignmentScheduleService $schedule,
    ) {}

    public function handleGeofenceEvent(GeofenceEvent $event, Geofence $geofence): void
    {
        $today = CarbonImmutable::parse($event->occurred_at)->startOfDay();

        $assignments = GeofenceAssignment::active()
            ->where('geofence_id', $event->geofence_id)
            ->where('user_id', $event->user_id)
            ->with(['user', 'assignedByUser'])
            ->get();

        foreach ($assignments as $assignment) {
            if (! $this->schedule->matchesDate($assignment, $today)) {
                continue;
            }

            if ($event->type === GeofenceEventType::Entered) {
                $this->markVisited($assignment, $event, $today);
            } elseif ($event->type === GeofenceEventType::Exited && $assignment->alert_on_exit) {
                $this->notifyExit($assignment, $event, $geofence);
            }
        }
    }

    /**
     * Runs on a schedule every few minutes to finalize any assignment whose
     * expected window has ended without the checkpoint being entered — this
     * is the "went a different way / never showed up" bypass detection,
     * which can't be known until the expected time window has elapsed.
     */
    public function evaluateMissedCheckpoints(): void
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();

        GeofenceAssignment::active()
            ->where('alert_on_missed', true)
            ->with(['geofence', 'user', 'assignedByUser'])
            ->chunkById(100, function ($assignments) use ($now, $today) {
                foreach ($assignments as $assignment) {
                    $this->evaluateOneMissedCheckpoint($assignment, $now, $today);
                }
            });
    }

    private function evaluateOneMissedCheckpoint(GeofenceAssignment $assignment, CarbonImmutable $now, CarbonImmutable $today): void
    {
        if (! $this->schedule->matchesDate($assignment, $today) || ! $this->schedule->windowHasEnded($assignment, $now)) {
            return;
        }

        $run = GeofenceAssignmentRun::firstOrCreate(
            ['geofence_assignment_id' => $assignment->id, 'run_date' => $today->toDateString()],
            ['status' => GeofenceAssignmentRunStatus::Pending],
        );

        if ($run->status !== GeofenceAssignmentRunStatus::Pending) {
            return;
        }

        $run->update(['status' => GeofenceAssignmentRunStatus::Missed, 'notified_at' => $now]);

        $this->notifyBoth($assignment, 'missed', $assignment->geofence);
    }

    private function markVisited(GeofenceAssignment $assignment, GeofenceEvent $event, CarbonImmutable $today): void
    {
        $run = GeofenceAssignmentRun::firstOrCreate(
            ['geofence_assignment_id' => $assignment->id, 'run_date' => $today->toDateString()],
            ['status' => GeofenceAssignmentRunStatus::Pending],
        );

        if ($run->status === GeofenceAssignmentRunStatus::Pending) {
            $run->update([
                'status' => GeofenceAssignmentRunStatus::Visited,
                'visited_at' => $event->occurred_at,
                'geofence_event_id' => $event->id,
            ]);
        }
    }

    private function notifyExit(GeofenceAssignment $assignment, GeofenceEvent $event, Geofence $geofence): void
    {
        if (! $this->schedule->matchesDate($assignment, CarbonImmutable::parse($event->occurred_at)->startOfDay())) {
            return;
        }

        $this->notifyBoth($assignment, 'exited', $geofence);
    }

    private function notifyBoth(GeofenceAssignment $assignment, string $kind, Geofence $geofence): void
    {
        $trackedUser = $assignment->user;

        $trackers = $trackedUser->trackerRelations()
            ->where('status', 'active')
            ->with('trackerUser')
            ->get()
            ->pluck('trackerUser')
            ->filter();

        $recipients = collect([$trackedUser, $assignment->assignedByUser])
            ->merge($trackers)
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify(new GeofenceComplianceNotification($trackedUser, $geofence, $kind, $assignment->route_label));
        }
    }
}
