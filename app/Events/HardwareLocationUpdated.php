<?php

namespace App\Events;

use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class HardwareLocationUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public string $connection;

    public function __construct(public array $location)
    {
        $this->connection = config('gps.queue_connection');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.gps.hardware')];
    }

    public function broadcastAs(): string
    {
        return 'hardware.location.updated';
    }

    public function broadcastWith(): array
    {
        return $this->location;
    }

    public function broadcastQueue(): string
    {
        return config('gps.queue');
    }

    public static function payload(GpsDevice $device, GpsDeviceLocation $location): array
    {
        return ['device_id' => $device->id, 'vehicle_id' => $location->vehicle_id,
            'registration' => $device->vehicle?->registration_number,
            'vehicle_type' => strtolower($device->vehicle?->details['type'] ?? 'vehicle'),
            'driver_name' => $device->vehicle?->details['driver_name'] ?? null,
            'acc' => $location->telemetry['acc'] ?? null, 'authorization' => $device->status,
            'acc_reported' => $location->telemetry['acc'] ?? null,
            'acc_reported_at' => $location->recorded_at->toIso8601String(), 'acc_source' => 'location',
            'last_packet_at' => $device->last_packet_at?->toIso8601String(), 'idle_seconds' => config('gps.idle_seconds'),
            'latitude' => $location->latitude,
            'longitude' => $location->longitude, 'speed' => $location->speed, 'heading' => $location->heading,
            'recorded_at' => $location->recorded_at->toIso8601String(), 'received_at' => $location->received_at->toIso8601String(),
            'protocol' => $location->protocol, 'model' => $device->model];
    }
}
