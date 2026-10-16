<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A group call participant left while others are still on the call — the
 * call itself keeps going. (A private call, or the last participant in a
 * group call, ends outright and broadcasts CallEnded instead.)
 */
class CallParticipantLeft implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly CallSession $callSession,
        public readonly int $userId,
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
        return 'call.participant.left';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->callSession->uuid,
            'user_id' => $this->userId,
        ];
    }
}
