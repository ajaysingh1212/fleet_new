<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Already decoded/projected state; never queries location history. Runs in the live worker. */
class HardwarePacketLive implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.gps.hardware.device.'.(int) $this->payload['device_id'])];
    }

    public function broadcastAs(): string
    {
        return 'hardware.location.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
