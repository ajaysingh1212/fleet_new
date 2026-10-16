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
 * Broadcasts on both the conversation channel AND the callee's own user
 * channel — same reasoning as ConversationStarted: the callee may not have
 * this conversation's channel open at all when the call comes in.
 */
class IncomingCall implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly CallSession $callSession,
        public readonly int $calleeId,
        public readonly bool $isCallWaiting = false,
    ) {
        $this->callSession->loadMissing(['conversation', 'initiator']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->callSession->conversation->uuid),
            new PrivateChannel('App.Models.User.'.$this->calleeId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.incoming';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->callSession->uuid,
            'conversation_uuid' => $this->callSession->conversation->uuid,
            'type' => $this->callSession->type->value,
            'caller' => [
                'id' => $this->callSession->initiator->id,
                'name' => $this->callSession->initiator->name,
                'avatar' => $this->callSession->initiator->avatar,
            ],
            'is_call_waiting' => $this->isCallWaiting,
        ];
    }
}
