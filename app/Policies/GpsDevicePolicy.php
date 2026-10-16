<?php

namespace App\Policies;

use App\Models\User;

// This installation has global administration, not company tenancy. Restrict
// hardware tracking to actual administrators, even if another role is delegated
// the existing mobile device-session permission.
class GpsDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin']) && $user->can('manage device sessions');
    }

    public function manage(User $user): bool
    {
        return $this->viewAny($user);
    }
}
