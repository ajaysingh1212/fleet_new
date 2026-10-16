<?php

namespace App\Events;

use App\Models\GeofenceEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast on the same per-user channel GpsLocationUpdated uses, so anyone
 * already watching a user's live tracking feed (self, admins/managers,
 * trackers) gets geofence entry/exit for free without new channel auth.
 */
class GeofenceEventOccurred implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly GeofenceEvent $geofenceEvent,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->geofenceEvent->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'geofence.event';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'geofence' => [
                'uuid' => $this->geofenceEvent->geofence->uuid,
                'name' => $this->geofenceEvent->geofence->name,
                'category' => $this->geofenceEvent->geofence->category->value,
                'color' => $this->geofenceEvent->geofence->color,
            ],
            'user_id' => $this->geofenceEvent->user_id,
            'type' => $this->geofenceEvent->type->value,
            'latitude' => (float) $this->geofenceEvent->latitude,
            'longitude' => (float) $this->geofenceEvent->longitude,
            'occurred_at' => $this->geofenceEvent->occurred_at->toIso8601String(),
        ];
    }
}
