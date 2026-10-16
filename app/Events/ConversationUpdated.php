<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after any group metadata/membership change (rename, description,
 * add/remove/promote/demote member). Kept deliberately minimal — the
 * frontend re-fetches the full member roster from GET /groups/{uuid} only
 * if that group's info panel is actually open, rather than duplicating
 * roster-shaping logic in the broadcast payload.
 */
class ConversationUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Conversation $conversation,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->conversation->uuid,
            'name' => $this->conversation->name,
            'description' => $this->conversation->description,
            'member_count' => $this->conversation->members()->count(),
        ];
    }
}
