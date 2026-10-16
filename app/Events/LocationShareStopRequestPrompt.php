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
 * Laravel's Notification `broadcast` channel always routes through the
 * queued BroadcastNotificationCreated event, even when the Notification
 * class itself isn't ShouldQueue — so a tracker's interactive Allow/Deny
 * prompt would silently wait on a queue worker with that mechanism. This
 * event broadcasts straight to ShouldBroadcastNow instead, landing on each
 * tracker's own channel (the same one notifications.js already subscribes
 * to on every page) with no queue involved.
 */
class LocationShareStopRequestPrompt implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly LocationShareStopRequest $stopRequest,
        public readonly int $trackerId,
        public readonly string $requesterName,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->trackerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location-share.stop-request-prompt';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'request_uuid' => $this->stopRequest->uuid,
            'requester_name' => $this->requesterName,
        ];
    }
}
