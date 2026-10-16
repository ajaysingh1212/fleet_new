<?php

namespace App\Events;

use App\Models\FieldTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

class FieldTaskUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly FieldTask $task, public readonly string $change) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->task->creator_id),
            new PrivateChannel('App.Models.User.'.$this->task->assignee_id),
        ];
    }

    public function broadcastAs(): string { return 'field-task.updated'; }

    public function broadcastWith(): array
    {
        $this->task->loadMissing('stops');

        return [
            'change' => $this->change,
            'task' => [
                'uuid' => $this->task->uuid,
                'status' => $this->task->status,
                'completed_at' => $this->task->completed_at?->toIso8601String(),
                'completed_stops' => $this->task->stops->where('status', 'completed')->count(),
                'total_stops' => $this->task->stops->count(),
                'stops' => $this->task->stops->map(fn ($stop) => [
                    'uuid' => $stop->uuid,
                    'status' => $stop->status,
                    'arrived_at' => $stop->arrived_at?->toIso8601String(),
                    'arrival_distance_meters' => $stop->arrival_distance_meters,
                ])->values()->all(),
            ],
        ];
    }
}
