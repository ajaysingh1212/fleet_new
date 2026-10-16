<?php

namespace App\Services;

use App\Events\UserPresenceChanged;
use App\Models\DeviceSession;

/**
 * "Online" means "currently logged in" (an active, non-logged-out device
 * session) — not "has sent a GPS ping". GPS sharing is a separate, opt-in
 * signal layered on top; presence itself just tracks session state.
 */
class UserPresenceService
{
    private const ONLINE_GRACE_SECONDS = 90;

    public function isOnline(int $userId): bool
    {
        return $this->activeSession($userId) !== null;
    }

    public function lastActivityAt(int $userId): ?string
    {
        return $this->activeSession($userId)?->last_activity_at?->toIso8601String();
    }

    /**
     * Recomputes the user's current online state and broadcasts it, so every
     * tracker watching this user's private channel updates live — called
     * right after a login or logout changes that state.
     */
    public function broadcastChange(int $userId): void
    {
        UserPresenceChanged::dispatch($userId, $this->isOnline($userId), $this->lastActivityAt($userId));
    }

    private function activeSession(int $userId): ?DeviceSession
    {
        return DeviceSession::where('user_id', $userId)
            ->where('is_current', true)
            ->whereNull('logged_out_at')
            ->where('last_activity_at', '>=', now()->subSeconds(self::ONLINE_GRACE_SECONDS))
            ->latest('last_activity_at')
            ->first();
    }
}
