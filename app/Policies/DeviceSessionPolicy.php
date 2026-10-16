<?php

namespace App\Policies;

use App\Models\DeviceSession;
use App\Models\User;

class DeviceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage device sessions');
    }

    public function view(User $user, DeviceSession $deviceSession): bool
    {
        return $user->can('manage device sessions') || $user->id === $deviceSession->user_id;
    }

    public function revoke(User $user, DeviceSession $deviceSession): bool
    {
        return $user->can('manage device sessions') || $user->id === $deviceSession->user_id;
    }
}
