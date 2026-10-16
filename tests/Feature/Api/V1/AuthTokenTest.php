<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceSession;
use App\Models\FailedLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_token_and_creates_a_device_session(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'device-abc-123',
            'device_name' => 'Test Phone',
            'platform' => 'android',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['uuid', 'email']]);

        $this->assertDatabaseHas('device_sessions', [
            'user_id' => $user->id,
            'session_id' => 'device-abc-123',
            'device_name' => 'Test Phone',
            'platform' => 'android',
            'is_current' => true,
        ]);
    }

    public function test_login_with_wrong_password_fails_and_logs_failed_login(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_id' => 'device-abc-123',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('failed_logins', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'device_id' => 'device-abc-123',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_id' => 'device-abc-123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_token_and_marks_device_session_logged_out(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'device-abc-123',
        ]);

        $token = $login->json('token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);

        $this->assertDatabaseHas('device_sessions', [
            'user_id' => $user->id,
            'session_id' => 'device-abc-123',
            'is_current' => false,
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->postJson('/api/v1/gps/locations', [])->assertStatus(401);
    }
}
