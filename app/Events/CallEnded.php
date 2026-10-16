<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

class CallEnded implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly CallSession $callSession,
        public readonly string $reason,
    ) {
        $this->callSession->loadMissing('conversation');
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->callSession->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.ended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->callSession->uuid,
            'reason' => $this->reason,
            'ended_at' => $this->callSession->ended_at?->toIso8601String(),
        ];
    }
}
