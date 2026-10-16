<?php

namespace App\Observers;

use App\Models\PasswordHistory;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        PasswordHistory::create([
            'user_id' => $user->id,
            'password' => $user->password,
            'created_at' => now(),
        ]);
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged('password')) {
            return;
        }

        PasswordHistory::create([
            'user_id' => $user->id,
            'password' => $user->password,
            'created_at' => now(),
        ]);
    }
}
