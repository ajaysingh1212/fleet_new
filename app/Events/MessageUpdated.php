<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Covers both body edits and pin/unpin toggles — the frontend just
 * re-renders the bubble from the fresh payload rather than needing a
 * separate event per kind of update.
 */
class MessageUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Message $message,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new MessageResource($this->message->loadMissing(['conversation', 'sender', 'replyTo.sender', 'forwardedFrom.sender', 'reactions', 'reads', 'attachments'])))->resolve();
    }
}
