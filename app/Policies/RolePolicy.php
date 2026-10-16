<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public const PROTECTED_ROLES = ['Super Admin', 'Admin', 'Manager', 'User'];

    public function viewAny(User $user): bool
    {
        return $user->can('manage roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('manage roles');
    }

    public function create(User $user): bool
    {
        return $user->can('manage roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('manage roles');
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->can('manage roles')) {
            return false;
        }

        return ! in_array($role->name, self::PROTECTED_ROLES, true);
    }
}
