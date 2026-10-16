<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\TrackingRelation;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiagnosticEventTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesTrackingLicense;

    public function test_a_valid_diagnostic_event_is_recorded(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/gps/diagnostics', [
            'event_type' => 'browser_hidden',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('diagnostic_logs', [
            'user_id' => $user->id,
            'event_type' => 'browser_hidden',
        ]);
    }

    public function test_a_non_browser_event_type_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/gps/diagnostics', [
            'event_type' => 'phone_restarted',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('event_type');
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->postJson('/api/v1/gps/diagnostics', ['event_type' => 'browser_hidden'])
            ->assertStatus(401);
    }

    public function test_relationship_owner_can_view_authorized_user_diagnostics(): void
    {
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();

        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Assigned user',
            'user_license_id' => $this->trackingLicense($tracker, $tracked)->id,
            'status' => 'active',
        ]);

        $tracked->diagnosticLogs()->create([
            'event_type' => 'internet_off',
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($tracker);

        $this->getJson('/api/v1/users/'.$tracked->id.'/diagnostics')
            ->assertOk()
            ->assertJsonPath('history.0.event_type', 'internet_off');
    }

    public function test_unrelated_user_cannot_view_diagnostics(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users/'.User::factory()->create()->id.'/diagnostics')
            ->assertForbidden();
    }

    public function test_super_admin_can_view_any_user_diagnostics(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $target = User::factory()->create();

        $target->diagnosticLogs()->create([
            'event_type' => 'browser_hidden',
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users/'.$target->id.'/diagnostics')
            ->assertOk()
            ->assertJsonPath('history.0.event_type', 'browser_hidden');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'ADMIN_VIEWED_DIAGNOSTICS',
        ]);
    }

    public function test_duplicate_diagnostic_state_is_suppressed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'event_type' => 'internet_off',
            'network_type' => 'offline',
        ];

        $this->postJson('/api/v1/gps/diagnostics', $payload)->assertCreated();
        $this->postJson('/api/v1/gps/diagnostics', $payload)->assertCreated();

        $this->assertDatabaseCount('diagnostic_logs', 1);
    }
}
