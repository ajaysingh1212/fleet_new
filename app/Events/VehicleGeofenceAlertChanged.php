<?php

namespace App\Events;

use App\Models\GpsDevice;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class VehicleGeofenceAlertChanged implements ShouldBroadcast
{
    use Dispatchable;

    public string $connection;

    public function __construct(public array $payload)
    {
        $this->connection = config('gps.broadcast_connection');
    }

    public function broadcastOn(): array
    {
        $device = GpsDevice::with('vehicle')->find($this->payload['device_id']);
        $owner = $device && $device->vehicle_id === ($this->payload['vehicle_id'] ?? null)
            ? $device->vehicle?->owner_user_id : null;

        return [new PrivateChannel('admin.gps.alerts'),
            ...($owner ? [new PrivateChannel('dashboard.fleet.'.(int) $owner)] : [])];
    }

    public function broadcastAs(): string
    {
        return 'vehicle.geofence.alert';
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
