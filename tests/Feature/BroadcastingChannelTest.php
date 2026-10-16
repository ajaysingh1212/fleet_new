<?php

namespace Tests\Feature;

use App\Models\TrackingRelation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class BroadcastingChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // routes/channels.php only ran once, at boot, registering its
        // patterns on whichever broadcaster was the app-wide test default
        // at that moment ("log"). Broadcast::driver('reverb') below would
        // otherwise resolve a fresh instance with no channels registered on
        // it at all — every check would fall through to "denied" regardless
        // of the actual authorization logic. Re-running the file against
        // "reverb" as the current default registers the same patterns there
        // too.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    }

    /**
     * Exercises the real registered channel-authorization logic
     * (routes/channels.php) via the "reverb" driver directly — regardless
     * of whatever broadcaster the app-wide test default uses (normally
     * "log", so broadcast() never leaks a live push to a real Reverb
     * server). Auth is a local callback evaluation with no network
     * involved either way, so calling the reverb driver by name here is
     * safe and avoids depending on global config or the HTTP middleware
     * stack at all.
     */
    private function authorize(User $user, User $subject): bool
    {
        $request = Request::create('/broadcasting/auth', 'POST', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$subject->id,
        ]);
        $request->setUserResolver(fn () => $user);

        try {
            Broadcast::driver('reverb')->auth($request);

            return true;
        } catch (AccessDeniedHttpException) {
            return false;
        }
    }

    private function authorizeDiagnostics(User $user, User $subject): bool
    {
        $request = Request::create('/broadcasting/auth', 'POST', [
            'socket_id' => '123.456',
            'channel_name' => 'private-diagnostics.'.$subject->id,
        ]);
        $request->setUserResolver(fn () => $user);

        try {
            Broadcast::driver('reverb')->auth($request);

            return true;
        } catch (AccessDeniedHttpException) {
            return false;
        }
    }

    public function test_a_user_can_authorize_their_own_channel(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->authorize($user, $user));
    }

    public function test_an_unrelated_user_cannot_authorize_someone_elses_channel(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->assertFalse($this->authorize($user, $stranger));
    }

    public function test_a_tracker_can_authorize_their_tracked_users_channel(): void
    {
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();

        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Test Relation',
            'status' => 'active',
        ]);

        $this->assertTrue($this->authorize($tracker, $tracked));
    }

    public function test_an_inactive_tracking_relation_does_not_authorize(): void
    {
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();

        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Test Relation',
            'status' => 'inactive',
        ]);

        $this->assertFalse($this->authorize($tracker, $tracked));
    }

    public function test_a_privileged_role_can_authorize_any_users_channel(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $anyone = User::factory()->create();

        $this->assertTrue($this->authorize($admin, $anyone));
    }

    public function test_plain_admin_role_is_not_global_channel_access(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $anyone = User::factory()->create();

        $this->assertFalse($this->authorize($admin, $anyone));
    }

    public function test_diagnostic_channel_uses_relationship_authorization(): void
    {
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();
        $stranger = User::factory()->create();

        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Test Relation',
            'status' => 'active',
        ]);

        $this->assertTrue($this->authorizeDiagnostics($tracker, $tracked));
        $this->assertFalse($this->authorizeDiagnostics($stranger, $tracked));
    }
}
