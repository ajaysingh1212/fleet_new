<?php

namespace App\Events;

use App\Models\GpsDevice;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareLiveState;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Broadcast the newest cache state on a separate queue; never replay a socket backlog. */
class HardwarePacketLive implements ShouldBeUnique, ShouldBroadcast
{
    use Dispatchable;

    public string $connection;

    public int $uniqueFor = 15;

    public int $tries = 3;

    public int $timeout = 15;

    private ?array $current = null;

    public function __construct(public array $payload)
    {
        $this->connection = config('gps.broadcast_connection');
    }

    public function uniqueId(): string
    {
        return 'gps-live:'.$this->payload['device_id'].':'.($this->payload['vehicle_id'] ?? 'unassigned');
    }

    public function broadcastQueue(): string
    {
        return config('gps.broadcast_queue');
    }

    public function broadcastOn(): array
    {
        $device = GpsDevice::with('vehicle')->find($this->payload['device_id']);
        if (! $device || $device->status !== 'active') {
            return [];
        }
        $live = app(HardwareLiveState::class);
        $this->current = $live->overlay($live->baseline($device, app(HardwareFleet::class)));
        $owner = $device && $device->vehicle_id === ($this->payload['vehicle_id'] ?? null)
            ? $device->vehicle?->owner_user_id : null;

        return [new PrivateChannel('admin.gps.hardware.device.'.(int) $this->payload['device_id']),
            ...($owner ? [new PrivateChannel('dashboard.fleet.'.(int) $owner)] : [])];
    }

    public function broadcastAs(): string
    {
        return 'hardware.location.updated';
    }

    public function broadcastWith(): array
    {
        return $this->current ?? $this->payload;
    }
}
