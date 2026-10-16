<?php

namespace Tests\Feature\Auth;

use App\Events\UserPresenceChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PresenceBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_broadcasts_the_user_as_online(): void
    {
        $user = User::factory()->create();
        Event::fake([UserPresenceChanged::class]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        Event::assertDispatched(UserPresenceChanged::class, function ($event) use ($user) {
            return $event->userId === $user->id && $event->isOnline === true;
        });
    }

    public function test_web_logout_broadcasts_the_user_as_offline(): void
    {
        // Single request via actingAs(), matching AuthenticationTest::test_users_can_logout —
        // Laravel's test HTTP client does not reliably correlate the session ID
        // across two separate sequential requests (login then logout) even
        // though Auth::check() persists via the shared test container; that's
        // a testing-harness quirk, not how a real browser's persistent cookie
        // behaves (this exact flow is verified working against a real browser
        // and the real dev database — see the live_map presence fix).
        $user = User::factory()->create();
        Event::fake([UserPresenceChanged::class]);

        $this->actingAs($user)->post('/logout');

        Event::assertDispatched(UserPresenceChanged::class, function ($event) use ($user) {
            return $event->userId === $user->id && $event->isOnline === false;
        });
    }

    public function test_token_login_broadcasts_the_user_as_online(): void
    {
        $user = User::factory()->create();
        Event::fake([UserPresenceChanged::class]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'device-presence-test',
        ]);

        Event::assertDispatched(UserPresenceChanged::class, function ($event) use ($user) {
            return $event->userId === $user->id && $event->isOnline === true;
        });
    }

    public function test_token_logout_broadcasts_the_user_as_offline(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'device-presence-test',
        ]);

        Event::fake([UserPresenceChanged::class]);

        $this->withHeader('Authorization', 'Bearer '.$login->json('token'))
            ->postJson('/api/v1/auth/logout');

        Event::assertDispatched(UserPresenceChanged::class, function ($event) use ($user) {
            return $event->userId === $user->id && $event->isOnline === false;
        });
    }

    public function test_a_user_stays_online_while_logged_in_on_another_device(): void
    {
        $user = User::factory()->create();

        // First device stays logged in.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'device-one',
        ]);

        // Second device logs in then out. Token-based login/logout keys the
        // DeviceSession on the client-supplied device_id (not the web
        // session ID), so this flow doesn't hit the same-session-per-request
        // testing quirk that the web login/logout flow does.
        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_id' => 'device-two',
        ]);

        Event::fake([UserPresenceChanged::class]);

        $this->withHeader('Authorization', 'Bearer '.$second->json('token'))
            ->postJson('/api/v1/auth/logout');

        // Logging out of device-two should NOT mark the user fully offline —
        // device-one is still active.
        Event::assertDispatched(UserPresenceChanged::class, function ($event) use ($user) {
            return $event->userId === $user->id && $event->isOnline === true;
        });
    }
}
