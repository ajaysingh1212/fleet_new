<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;

trait SeedsRolesAndPermissions
{
    protected function actingAsSuperAdmin(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $this->actingAs($user);

        return $user;
    }

    protected function actingAsPlainUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user);

        return $user;
    }

    protected function seedSettings(): void
    {
        $this->seed(SettingsSeeder::class);
    }
}
