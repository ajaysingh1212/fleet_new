<?php

namespace App\Events;

use App\Models\LocationShareStopRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The requester's gps-watcher.js listens for this on their own channel to
 * actually stop sharing once approved (or keep going + show the denial).
 */
class LocationShareStopResolved implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly LocationShareStopRequest $stopRequest,
        public readonly string $resolverName,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->stopRequest->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location-share.stop-resolved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'request_uuid' => $this->stopRequest->uuid,
            'status' => $this->stopRequest->status->value,
            'resolved_by_name' => $this->resolverName,
        ];
    }
}
