<?php

namespace App\Console\Commands;

use App\Models\FieldTask;
use App\Models\DiagnosticLog;
use App\Models\GpsLocation;
use Illuminate\Console\Command;

class PurgeExpiredFieldTaskData extends Command
{
    protected $signature = 'tasks:purge-expired';
    protected $description = 'Permanently remove field tasks and their task-scoped evidence after the 30-day retention period.';

    public function handle(): int
    {
        $count = FieldTask::withTrashed()
            ->where(fn ($query) => $query->where('completed_at', '<', now()->subDays(30))
                ->orWhere(fn ($query) => $query->whereNotNull('deleted_at')->where('deleted_at', '<', now()->subDays(30))))
            ->forceDelete();
        $diagnostics = DiagnosticLog::where('occurred_at', '<', now()->subDays(30))->delete();
        $locations = GpsLocation::where('recorded_at', '<', now()->subDays(30))->delete();
        $this->info("Purged {$count} tasks, {$locations} GPS points, and {$diagnostics} diagnostic records outside the 30-day window.");
        return self::SUCCESS;
    }
}
