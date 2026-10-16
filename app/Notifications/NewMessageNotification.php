<?php

namespace App\Notifications;

use App\Enums\ConversationType;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
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
        $isGroup = $conversation->type === ConversationType::Group;
        $sender = $this->message->sender->name;

        return [
            'kind' => 'new_message',
            'category' => 'message',
            'message' => $isGroup
                ? "{$sender} in {$conversation->name}: {$this->preview()}"
                : "{$sender}: {$this->preview()}",
            'action_url' => route('chats.index', ['conversation' => $conversation->uuid]),
            'conversation_uuid' => $conversation->uuid,
            'message_uuid' => $this->message->uuid,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    private function preview(): string
    {
        if ($this->message->body) {
            return str($this->message->body)->limit(80)->toString();
        }

        $attachment = $this->message->attachments->first();

        return $attachment ? "📎 {$attachment->original_name}" : 'Sent a message';
    }
}
