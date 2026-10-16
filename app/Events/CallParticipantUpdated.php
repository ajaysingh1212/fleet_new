<?php

namespace App\Events;

use App\Models\CallParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

class CallParticipantUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly CallParticipant $participant,
    ) {
        $this->participant->loadMissing('callSession.conversation');
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->participant->callSession->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.participant.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->participant->callSession->uuid,
            'user_id' => $this->participant->user_id,
            'is_muted' => $this->participant->is_muted,
            'is_video_enabled' => $this->participant->is_video_enabled,
            'is_on_hold' => $this->participant->is_on_hold,
        ];
    }
}
