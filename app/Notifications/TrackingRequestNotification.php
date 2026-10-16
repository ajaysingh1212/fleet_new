<?php

namespace App\Notifications;

use App\Enums\UserStatus;
use App\Models\TrackingRelation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TrackingRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TrackingRelation $relation,
        public readonly User $tracker,
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
        $isPending = $this->relation->status === UserStatus::Pending;

        return [
            'kind' => 'tracking_request',
            'category' => 'tracking',
            'message' => $isPending
                ? "{$this->tracker->name} sent you a tracking request"
                : "{$this->tracker->name} added you to their tracking account",
            'action_url' => $isPending ? route('my-tracking.index') : route('live-map.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
