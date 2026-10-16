<?php

namespace App\Services\Geofence;

use App\Enums\GeofenceScheduleType;
use App\Models\GeofenceAssignment;
use Carbon\CarbonInterface;

class GeofenceAssignmentScheduleService
{
    public function matchesDate(GeofenceAssignment $assignment, CarbonInterface $date): bool
    {
        return match ($assignment->schedule_type) {
            GeofenceScheduleType::Daily => true,
            GeofenceScheduleType::Weekly => in_array($date->isoWeekday(), $assignment->schedule_days ?? [], true),
            GeofenceScheduleType::Monthly => in_array($date->day, $assignment->schedule_days ?? [], true),
            GeofenceScheduleType::Quarterly => in_array($date->month, [1, 4, 7, 10], true)
                && in_array($date->day, $assignment->schedule_days ?? [], true),
            GeofenceScheduleType::Yearly => in_array($date->format('m-d'), $assignment->schedule_days ?? [], true),
            GeofenceScheduleType::CustomDate => $assignment->schedule_date?->isSameDay($date) ?? false,
        };
    }

    public function isWithinWindow(GeofenceAssignment $assignment, CarbonInterface $moment): bool
    {
        if (! $assignment->window_start || ! $assignment->window_end) {
            return true;
        }

        $time = $moment->format('H:i:s');

        return $time >= $assignment->window_start && $time <= $assignment->window_end;
    }

    public function windowHasEnded(GeofenceAssignment $assignment, CarbonInterface $now): bool
    {
        if (! $assignment->window_end) {
            return $now->format('H:i:s') >= '23:59:00';
        }

        return $now->format('H:i:s') >= $assignment->window_end;
    }
}
