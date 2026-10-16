<?php

namespace App\Notifications;

use App\Models\DiagnosticLog;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

// Deliberately NOT queued: these alerts are meant to be realtime, and a
// dev/staging box that isn't running a queue worker would otherwise let
// them sit unsent in the jobs table indefinitely.
class DiagnosticAlertNotification extends Notification
{
    public function __construct(
        public readonly DiagnosticLog $log,
        public readonly User $trackedUser,
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
            'kind' => 'diagnostic_alert',
            'category' => 'diagnostic',
            'message' => $this->log->reason
                ? "{$this->trackedUser->name}: {$this->log->event_type->label()} — {$this->log->reason}"
                : "{$this->trackedUser->name}: {$this->log->event_type->label()}",
            'action_url' => route('live-map.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
