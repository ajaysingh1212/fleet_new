<?php

namespace App\Events;

use App\Models\GpsLocation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * ShouldBroadcastNow (not ShouldBroadcast): this already fires from inside
 * StoreGpsLocationJob, itself queued on the `redis` connection. Broadcasting
 * synchronously here avoids Laravel routing the underlying send through a
 * second, separate queued job on the *default* connection, which nothing
 * would be consuming.
 */
class GpsLocationUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    // Keep in sync with StoreGpsLocationJob::STOP_SPEED_THRESHOLD — same
    // "moving vs. idle" cutoff, just also surfaced to the live map display.
    private const MOVING_SPEED_MPS = 0.5;

    public function __construct(
        public readonly GpsLocation $location,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->location->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'gps.location.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_session_id' => $this->location->device_session_id,
            'tracking_session_id' => $this->location->tracking_session_id,
            'latitude' => (float) $this->location->latitude,
            'longitude' => (float) $this->location->longitude,
            'speed' => $this->location->speed !== null ? (float) $this->location->speed : null,
            'bearing' => $this->location->bearing !== null ? (float) $this->location->bearing : null,
            'heading' => $this->location->heading !== null ? (float) $this->location->heading : null,
            'battery_level' => $this->location->battery_level,
            'network_type' => $this->location->network_type,
            'is_gps_enabled' => true,
            'is_internet_enabled' => true,
            'movement_status' => (float) ($this->location->speed ?? 0) > self::MOVING_SPEED_MPS ? 'moving' : 'idle',
            'recorded_at' => $this->location->recorded_at->toIso8601String(),
        ];
    }
}
