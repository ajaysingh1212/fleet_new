<?php

namespace App\Notifications;

use App\Models\CallSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MissedCallNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CallSession $callSession,
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
        // loadMissing() here, not in the constructor: this notification is
        // ShouldQueue, and a real (non-sync) queue connection re-hydrates a
        // fresh, relation-less model on the worker after serialization —
        // any eager-loading done before dispatch wouldn't survive that trip.
        $this->callSession->loadMissing(['conversation', 'initiator']);
        $conversation = $this->callSession->conversation;
        $callTypeLabel = $this->callSession->type->value === 'video' ? 'video call' : 'voice call';

        return [
            'kind' => 'missed_call',
            'category' => 'call',
            'message' => "You missed a {$callTypeLabel} from {$this->callSession->initiator->name}",
            'action_url' => route('chats.index', ['conversation' => $conversation->uuid]),
            'conversation_uuid' => $conversation->uuid,
            'call_uuid' => $this->callSession->uuid,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
