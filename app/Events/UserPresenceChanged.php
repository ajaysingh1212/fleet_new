<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired right after a login or logout so every tracker watching this user's
 * private channel sees the online/offline change immediately, without
 * waiting on (or requiring) a GPS ping.
 */
class UserPresenceChanged implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
        public readonly bool $isOnline,
        public readonly ?string $lastActivityAt,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'presence.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'is_online' => $this->isOnline,
            'last_activity_at' => $this->lastActivityAt,
        ];
    }
}
