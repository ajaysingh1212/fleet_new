<?php

namespace Tests\Feature\Admin;

use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DeviceSessionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_plain_user_cannot_access_admin_device_session_list(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.device-sessions.index'))->assertForbidden();
    }

    public function test_admin_can_revoke_another_users_device_session(): void
    {
        $this->actingAsSuperAdmin();
        $owner = User::factory()->create();
        $session = DeviceSession::create([
            'uuid' => Str::uuid(),
            'user_id' => $owner->id,
            'session_id' => 'fake-session-id',
            'device_name' => 'Chrome on Windows',
            'ip_address' => '127.0.0.1',
            'last_login_at' => now(),
            'last_activity_at' => now(),
            'is_current' => true,
        ]);

        $response = $this->post(route('admin.device-sessions.revoke', $session));

        $response->assertRedirect();
        $session->refresh();
        $this->assertFalse($session->is_current);
        $this->assertNotNull($session->logged_out_at);
    }

    public function test_user_can_revoke_their_own_device_session_but_not_others(): void
    {
        $me = $this->actingAsPlainUser();
        $mySession = DeviceSession::create([
            'uuid' => Str::uuid(),
            'user_id' => $me->id,
            'session_id' => 'my-session-id',
            'device_name' => 'My Device',
            'ip_address' => '127.0.0.1',
            'last_login_at' => now(),
            'last_activity_at' => now(),
            'is_current' => true,
        ]);

        $other = User::factory()->create();
        $otherSession = DeviceSession::create([
            'uuid' => Str::uuid(),
            'user_id' => $other->id,
            'session_id' => 'other-session-id',
            'device_name' => 'Other Device',
            'ip_address' => '127.0.0.1',
            'last_login_at' => now(),
            'last_activity_at' => now(),
            'is_current' => true,
        ]);

        $this->post(route('my-devices.revoke', $mySession))->assertRedirect();
        $this->assertFalse($mySession->fresh()->is_current);

        $this->post(route('my-devices.revoke', $otherSession))->assertForbidden();
        $this->assertTrue($otherSession->fresh()->is_current);
    }
}
