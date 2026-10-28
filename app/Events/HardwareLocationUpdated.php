<?php

namespace App\Events;

use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareSensorData;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class HardwareLocationUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public string $connection;

    public function __construct(public array $location)
    {
        $this->connection = config('gps.broadcast_connection');
    }

    public function broadcastOn(): array
    {
        $owner = GpsDevice::with('vehicle')->find($this->location['device_id'])?->vehicle?->owner_user_id;

        return [new PrivateChannel('admin.gps.hardware.device.'.(int) $this->location['device_id']),
            ...($owner ? [new PrivateChannel('dashboard.fleet.'.(int) $owner)] : [])];
    }

    public function broadcastAs(): string
    {
        return 'hardware.location.updated';
    }

    public function broadcastWith(): array
    {
        // Read the current state when the worker sends it. Queued/retried old
        // location events must not roll back a newer heartbeat or assignment.
        $device = GpsDevice::with(['vehicle', 'latestLocation', 'latestHeartbeat'])->find($this->location['device_id']);

        return $device ? app(HardwareFleet::class)->device($device) : $this->location;
    }

    public function broadcastQueue(): string
    {
        return config('gps.broadcast_queue');
    }

    public static function payload(GpsDevice $device, GpsDeviceLocation $location): array
    {
        return ['device_id' => $device->id, 'vehicle_id' => $location->vehicle_id,
            'registration' => $device->vehicle?->registration_number,
            'vehicle_type' => strtolower($device->vehicle?->details['type'] ?? 'vehicle'),
            'vehicle_icon' => config('fleet.icons.'.($device->vehicle?->details['icon'] ?? $device->vehicle?->details['type'] ?? 'car').'.icon', 'fa-car-side'),
            'driver_name' => $device->vehicle?->details['driver_name'] ?? null,
            'acc' => $location->telemetry['acc'] ?? null, 'authorization' => $device->status,
            'acc_reported' => $location->telemetry['acc'] ?? null,
            'acc_reported_at' => $location->recorded_at->toIso8601String(), 'acc_source' => 'location',
            'last_packet_at' => $device->last_packet_at?->toIso8601String(), 'idle_seconds' => config('gps.idle_seconds'),
            'latitude' => $location->latitude,
            'altitude' => $location->altitude, 'satellites' => $location->satellites,
            'sensors' => HardwareSensorData::forDisplay($location->telemetry ?? []),
            'longitude' => $location->longitude, 'speed' => $location->speed, 'heading' => $location->heading,
            'recorded_at' => $location->recorded_at->toIso8601String(), 'received_at' => $location->received_at->toIso8601String(),
            'protocol' => $location->protocol, 'model' => $device->model];
    }
}
