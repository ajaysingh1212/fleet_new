<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MessageReactionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
        public readonly User $reactor,
        public readonly string $emoji,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $conversation = $this->message->conversation;

        return [
            'kind' => 'reaction',
            'category' => 'message',
            'message' => "{$this->reactor->name} reacted {$this->emoji} to your message",
            'action_url' => route('chats.index', ['conversation' => $conversation->uuid]),
            'conversation_uuid' => $conversation->uuid,
            'message_uuid' => $this->message->uuid,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
