<?php

namespace App\Console\Commands;

use App\Services\Geofence\GeofenceAssignmentComplianceService;
use Illuminate\Console\Command;

class EvaluateGeofenceAssignments extends Command
{
    protected $signature = 'geofence:evaluate-assignments';

    protected $description = 'Finalize geofence assignment runs whose expected time window has ended, flagging missed checkpoints and notifying the tracked user and their trackers.';

    public function handle(GeofenceAssignmentComplianceService $compliance): int
    {
        $compliance->evaluateMissedCheckpoints();

        return self::SUCCESS;
    }
}
