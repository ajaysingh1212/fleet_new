<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('manage users') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('manage users');
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->can('manage users')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        return ! $this->isLastSuperAdmin($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('manage users');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('Super Admin') && $user->id !== $model->id;
    }

    public function updateStatus(User $user, User $model): bool
    {
        if (! $user->can('manage users')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        return ! $this->isLastSuperAdmin($model);
    }

    public function updateRoles(User $user, User $model): bool
    {
        if (! $user->can('manage users')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        return ! $this->isLastSuperAdmin($model);
    }

    protected function isLastSuperAdmin(User $model): bool
    {
        if (! $model->hasRole('Super Admin')) {
            return false;
        }

        return User::role('Super Admin')->count() <= 1;
    }
}
