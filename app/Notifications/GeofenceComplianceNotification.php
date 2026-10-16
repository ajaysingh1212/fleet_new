<?php

namespace App\Notifications;

use App\Models\Geofence;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GeofenceComplianceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $trackedUser,
        public readonly Geofence $geofence,
        public readonly string $kind,
        public readonly ?string $routeLabel = null,
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
            'kind' => 'geofence_'.$this->kind,
            'category' => 'geofence',
            'message' => $this->message(),
            'action_url' => route('live-map.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    private function message(): string
    {
        return match ($this->kind) {
            'exited' => "{$this->trackedUser->name} left \"{$this->geofence->name}\".",
            'missed' => $this->routeLabel
                ? "{$this->trackedUser->name} didn't pass through \"{$this->geofence->name}\" on the \"{$this->routeLabel}\" route — a different path may have been taken."
                : "{$this->trackedUser->name} didn't reach \"{$this->geofence->name}\" within the expected time.",
            default => "{$this->trackedUser->name}: update for \"{$this->geofence->name}\".",
        };
    }
}
