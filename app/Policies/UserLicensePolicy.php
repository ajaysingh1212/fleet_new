<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserLicense;

class UserLicensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage user licenses');
    }

    public function view(User $user, UserLicense $userLicense): bool
    {
        return $user->can('manage user licenses') || $user->id === $userLicense->user_id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage user licenses');
    }

    public function update(User $user, UserLicense $userLicense): bool
    {
        return $user->can('manage user licenses');
    }

    public function delete(User $user, UserLicense $userLicense): bool
    {
        return $user->can('manage user licenses');
    }
}
