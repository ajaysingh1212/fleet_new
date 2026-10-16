<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

class MessageDelivered implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<int, string>  $messageUuids
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly int $userId,
        public readonly array $messageUuids,
        public readonly string $deliveredAt,
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
        return 'message.delivered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_uuids' => $this->messageUuids,
            'user_id' => $this->userId,
            'delivered_at' => $this->deliveredAt,
        ];
    }
}
