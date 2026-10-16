<?php

namespace App\Notifications;

use App\Models\LocationShareStopRequest;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

// Deliberately NOT queued: this has to reach the tracker immediately (they're
// blocking a stop-sharing request), and a dev/staging box that isn't running
// a queue worker would otherwise leave it stuck unsent in the jobs table.
class LocationShareStopRequestNotification extends Notification
{
    public function __construct(
        public readonly LocationShareStopRequest $stopRequest,
        public readonly User $requester,
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
            'kind' => 'location_share_stop_request',
            'category' => 'tracking',
            'message' => "{$this->requester->name} wants to stop sharing their location.",
            'request_uuid' => $this->stopRequest->uuid,
            'requester_name' => $this->requester->name,
            'action_url' => route('live-map.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
