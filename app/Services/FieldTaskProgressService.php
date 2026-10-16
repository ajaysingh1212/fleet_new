<?php

namespace App\Services;

use App\Events\FieldTaskUpdated;
use App\Models\FieldTask;
use App\Models\FieldTaskActivity;
use App\Models\GpsLocation;
use Illuminate\Support\Facades\DB;

class FieldTaskProgressService
{
    public function process(GpsLocation $location): void
    {
        $tasks = FieldTask::query()
            ->where('assignee_id', $location->user_id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->where('starts_at', '<=', $location->recorded_at)
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', $location->recorded_at))
            ->whereHas('trackingRelation', fn ($query) => $query->usableForTracking())
            ->with(['stops' => fn ($query) => $query->where('status', 'pending')->orderBy('sequence')])
            ->get();

        foreach ($tasks as $task) {
            $stop = $task->stops->first();
            if (! $stop) continue;

            $distance = $this->distanceMeters(
                (float) $location->latitude, (float) $location->longitude,
                (float) $stop->latitude, (float) $stop->longitude,
            );

            if ($distance > $stop->radius_meters) continue;

            DB::transaction(function () use ($task, $stop, $location, $distance) {
                $freshStop = $stop->newQuery()->lockForUpdate()->find($stop->id);
                if (! $freshStop || $freshStop->status !== 'pending') return;

                $freshStop->update([
                    'status' => 'completed',
                    'arrived_at' => $location->recorded_at,
                    'arrival_location_id' => $location->id,
                    'arrival_distance_meters' => (int) round($distance),
                ]);

                $remaining = $task->stops()->where('status', 'pending')->exists();
                $task->update([
                    'status' => $remaining ? 'in_progress' : 'completed',
                    'started_at' => $task->started_at ?? $location->recorded_at,
                    'completed_at' => $remaining ? null : $location->recorded_at,
                ]);

                FieldTaskActivity::create([
                    'field_task_id' => $task->id,
                    'field_task_stop_id' => $freshStop->id,
                    'user_id' => $location->user_id,
                    'event_type' => 'stop_completed',
                    'metadata' => [
                        'distance_meters' => (int) round($distance),
                        'speed_mps' => $location->speed !== null ? (float) $location->speed : null,
                        'battery_level' => $location->battery_level,
                        'network_type' => $location->network_type,
                    ],
                    'occurred_at' => $location->recorded_at,
                ]);
            });

            $task->refresh()->load('stops');
            broadcast(new FieldTaskUpdated($task, 'stop_completed'));
        }
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
