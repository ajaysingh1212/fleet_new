<?php

namespace Database\Factories;

use App\Enums\ThemeMode;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => 'EMP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'department' => null,
            'designation' => null,
            'company' => null,
            'avatar' => null,
            'gender' => null,
            'dob' => null,
            'address' => null,
            'country_id' => null,
            'state_id' => null,
            'city_id' => null,
            'zip_code' => null,
            'language_id' => null,
            'status' => UserStatus::Active,
            'theme' => ThemeMode::Light,
            'timezone' => 'UTC',
            'last_login_at' => null,
            'last_login_ip' => null,
            'last_activity_at' => null,
            'created_by' => null,
            'updated_by' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
