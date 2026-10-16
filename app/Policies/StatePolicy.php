<?php

namespace App\Policies;

use App\Models\State;
use App\Models\User;

class StatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage states');
    }

    public function view(User $user, State $state): bool
    {
        return $user->can('manage states');
    }

    public function create(User $user): bool
    {
        return $user->can('manage states');
    }

    public function update(User $user, State $state): bool
    {
        return $user->can('manage states');
    }

    public function delete(User $user, State $state): bool
    {
        return $user->can('manage states') && ! $state->cities()->exists();
    }
}
