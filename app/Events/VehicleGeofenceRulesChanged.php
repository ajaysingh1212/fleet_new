<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class VehicleGeofenceRulesChanged implements ShouldBroadcast
{
    use Dispatchable;

    public string $connection;

    public function __construct(public array $payload = [])
    {
        $this->connection = config('gps.broadcast_connection');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.gps.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'vehicle.geofence.rules.changed';
    }

    public function broadcastQueue(): string
    {
        return config('gps.broadcast_queue');
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
