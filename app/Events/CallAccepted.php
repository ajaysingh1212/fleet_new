<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

class CallAccepted implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<int, array{id: int, name: string}>  $existingParticipants
     */
    public function __construct(
        public readonly CallSession $callSession,
        public readonly int $userId,
        public readonly array $existingParticipants = [],
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
        return 'call.accepted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->callSession->uuid,
            'user_id' => $this->userId,
            'existing_participants' => $this->existingParticipants,
        ];
    }
}
