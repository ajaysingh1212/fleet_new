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
 * Broadcasts on the requester's own channel — anyone already authorized to
 * listen there (their active trackers, admins/managers) picks this up for
 * free. Trackers get the actionable Allow/Deny prompt via the accompanying
 * LocationShareStopRequestNotification instead; this event is what lets the
 * requester's own tab reflect "waiting for approval" state.
 */
class LocationShareStopRequested implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly LocationShareStopRequest $stopRequest,
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
        return 'location-share.stop-requested';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'request_uuid' => $this->stopRequest->uuid,
            'user_id' => $this->stopRequest->user_id,
        ];
    }
}
