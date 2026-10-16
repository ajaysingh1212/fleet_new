<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast on the recipient's own user channel (not a conversation.{uuid}
 * channel, which they can't be subscribed to yet — they don't know it
 * exists). Without this, a brand-new conversation someone just started with
 * you wouldn't show up until you refreshed the /chats page.
 */
class ConversationStarted implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly int $recipientId,
        public readonly User $initiator,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->recipientId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $presence = app(UserPresenceService::class);

        return [
            'uuid' => $this->conversation->uuid,
            'type' => $this->conversation->type->value,
            'other_user' => [
                'id' => $this->initiator->id,
                'name' => $this->initiator->name,
                'avatar' => $this->initiator->avatar,
                'avatar_url' => $this->initiator->avatar ? asset('storage/'.$this->initiator->avatar) : null,
                'department' => $this->initiator->department,
                'designation' => $this->initiator->designation,
                'company' => $this->initiator->company,
                'is_online' => $presence->isOnline($this->initiator->id),
                'last_activity_at' => $presence->lastActivityAt($this->initiator->id),
            ],
            'last_message' => null,
            'unread_count' => 0,
            'updated_at' => $this->conversation->updated_at->toIso8601String(),
        ];
    }
}
