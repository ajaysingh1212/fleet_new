<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GroupInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Conversation $group,
        public readonly User $actor,
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
        return [
            'kind' => 'group_invite',
            'category' => 'message',
            'message' => "{$this->actor->name} added you to \"{$this->group->name}\"",
            'action_url' => route('chats.index', ['conversation' => $this->group->uuid]),
            'conversation_uuid' => $this->group->uuid,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
