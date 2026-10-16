<?php

namespace Database\Seeders;

use App\Enums\ThemeMode;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'employee_id' => 'EMP-00001',
                'name' => 'Super Admin',
                'phone' => '9000000001',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'theme' => ThemeMode::Light,
                'timezone' => 'UTC',
                'email_verified_at' => now(),
            ],
        );
        $superAdmin->syncRoles(['Super Admin']);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'employee_id' => 'EMP-00002',
                'name' => 'Admin User',
                'phone' => '9000000002',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'theme' => ThemeMode::Light,
                'timezone' => 'UTC',
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['Admin']);

        $existingUserEmails = collect(range(1, 10))
            ->map(fn (int $number) => 'user'.$number.'@gmail.com');

        User::query()
            ->role('User')
            ->whereNotIn('email', $existingUserEmails)
            ->where('email', 'like', 'user%@gmail.com')
            ->get()
            ->each
            ->delete();

        foreach (range(1, 10) as $number) {
            $user = User::query()->updateOrCreate(
                ['email' => 'user'.$number.'@gmail.com'],
                [
                    'employee_id' => 'EMP-'.str_pad((string) ($number + 2), 5, '0', STR_PAD_LEFT),
                    'name' => 'User '.$number,
                    'phone' => '91000000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'password' => Hash::make('Password@123'),
                    'status' => UserStatus::Active,
                    'theme' => ThemeMode::Light,
                    'timezone' => 'UTC',
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles(['User']);
        }
    }
}
