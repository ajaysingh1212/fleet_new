<?php

namespace App\Events;

use App\Models\DiagnosticLog;
use App\Models\Geofence;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast on the same per-user channel GpsLocationUpdated/GeofenceEventOccurred
 * use, so both the speeding user (self) and anyone tracking them get the alert
 * live without a separate channel subscription.
 */
class GeofenceOverspeedDetected implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Geofence $geofence,
        public readonly DiagnosticLog $log,
        public readonly float $speedKmh,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->log->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'geofence.overspeed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'geofence' => [
                'uuid' => $this->geofence->uuid,
                'name' => $this->geofence->name,
            ],
            'user_id' => $this->log->user_id,
            'speed_kmh' => round($this->speedKmh, 1),
            'limit_kmh' => $this->geofence->max_speed_kmh,
            'occurred_at' => $this->log->occurred_at->toIso8601String(),
        ];
    }
}
