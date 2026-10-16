<?php

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Models\LicensePlan;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Services\LicenseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTrackingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): LicensePlan
    {
        return LicensePlan::create([
            'uuid' => Str::uuid(),
            'name' => 'Tracking Plan',
            'type' => 'yearly',
            'duration_in_days' => 365,
            'price' => 99,
            'renewal_price' => 99,
            'status' => 'active',
            'display_order' => 1,
        ]);
    }

    public function test_user_can_request_tracking_existing_user_and_track_after_acceptance(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tracker = User::factory()->create();
        $tracker->assignRole('User');
        $tracked = User::factory()->create();
        $tracked->assignRole('User');
        app(LicenseService::class)->issueForAdmin($tracker, $this->plan());

        $this->actingAs($tracker)->post(route('my-tracking.requests.store'), [
            'identifier' => $tracked->email,
            'relationship_name' => 'Friend',
        ])->assertRedirect(route('my-tracking.index'));

        $relation = TrackingRelation::first();
        $this->assertSame('pending', $relation->status->value);
        $license = $tracker->userLicenses()->firstOrFail();
        $this->assertSame(LicenseStatus::Pending, $license->status);
        $this->assertNull($license->activation_date);

        $this->actingAs($tracker)->get(route('live-map.index'))
            ->assertViewHas('people', fn ($people) => $people->pluck('id')->doesntContain($tracked->id));

        $this->actingAs($tracked)->post(route('my-tracking.requests.accept', $relation))
            ->assertRedirect(route('my-tracking.index'));

        $this->assertSame('active', $relation->fresh()->status->value);
        $this->assertSame(LicenseStatus::Active, $license->fresh()->status);
        $this->assertNotNull($license->fresh()->activation_date);
        $this->actingAs($tracker)->get(route('live-map.index'))
            ->assertViewHas('people', fn ($people) => $people->pluck('id')->contains($tracked->id));
    }

    public function test_rejecting_or_cancelling_a_request_releases_the_reserved_license(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tracker = User::factory()->create();
        $tracker->assignRole('User');
        $firstTarget = User::factory()->create();
        $firstTarget->assignRole('User');
        $secondTarget = User::factory()->create();
        $secondTarget->assignRole('User');
        $license = app(LicenseService::class)->issueForAdmin($tracker, $this->plan());

        $this->actingAs($tracker)->post(route('my-tracking.requests.store'), [
            'identifier' => $firstTarget->email,
            'relationship_name' => 'Friend',
        ])->assertSessionHasNoErrors();

        $request = TrackingRelation::firstOrFail();
        $this->actingAs($firstTarget)->delete(route('my-tracking.requests.reject', $request))
            ->assertSessionHasNoErrors();
        $this->assertNull($license->fresh()->assigned_tracked_user_id);

        $this->actingAs($tracker)->post(route('my-tracking.requests.store'), [
            'identifier' => $secondTarget->phone,
            'relationship_name' => 'Friend',
        ])->assertSessionHasNoErrors();

        $request = TrackingRelation::where('tracked_user_id', $secondTarget->id)->firstOrFail();
        $this->actingAs($tracker)->delete(route('my-tracking.destroy', $request))
            ->assertSessionHasNoErrors();
        $this->assertNull($license->fresh()->assigned_tracked_user_id);
        $this->assertSame(LicenseStatus::Pending, $license->fresh()->status);
    }

    public function test_each_direction_requires_its_own_accepted_request_for_live_tracking(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $userA = User::factory()->create();
        $userA->assignRole('User');
        $userB = User::factory()->create();
        $userB->assignRole('User');
        $plan = $this->plan();
        app(LicenseService::class)->issueForAdmin($userA, $plan);
        app(LicenseService::class)->issueForAdmin($userB, $plan);

        $this->actingAs($userA)->post(route('my-tracking.requests.store'), [
            'identifier' => $userB->email,
            'relationship_name' => 'Friend',
        ]);
        $aToB = TrackingRelation::where('tracker_user_id', $userA->id)->firstOrFail();
        $this->actingAs($userB)->post(route('my-tracking.requests.accept', $aToB));

        $this->actingAs($userB)->get(route('live-map.index'))
            ->assertViewHas('people', fn ($people) => $people->pluck('id')->doesntContain($userA->id));

        $this->actingAs($userB)->post(route('my-tracking.requests.store'), [
            'identifier' => $userA->phone,
            'relationship_name' => 'Friend',
        ]);
        $bToA = TrackingRelation::where('tracker_user_id', $userB->id)->firstOrFail();
        $this->actingAs($userA)->post(route('my-tracking.requests.accept', $bToA));

        $this->actingAs($userB)->get(route('live-map.index'))
            ->assertViewHas('people', fn ($people) => $people->pluck('id')->contains($userA->id));
    }

    public function test_user_can_create_managed_user_with_license(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tracker = User::factory()->create();
        $tracker->assignRole('User');
        app(LicenseService::class)->issueForAdmin($tracker, $this->plan());

        $this->actingAs($tracker)->post(route('my-tracking.managed-users.store'), [
            'name' => 'Managed Friend',
            'email' => 'managed@example.com',
            'phone' => '9998887776',
            'password' => 'password',
            'password_confirmation' => 'password',
            'relationship_name' => 'Friend',
        ])->assertRedirect(route('my-tracking.index'));

        $managed = User::where('email', 'managed@example.com')->firstOrFail();
        $this->assertTrue($managed->hasRole('User'));
        $this->assertDatabaseHas('tracking_relations', [
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $managed->id,
            'relationship_name' => 'Friend',
            'status' => 'active',
        ]);
        $this->assertSame($managed->id, $tracker->userLicenses()->first()->assigned_tracked_user_id);
    }

    public function test_tracked_user_can_see_their_tracker_and_request_tracking_back(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $plan = $this->plan();
        $userA = User::factory()->create();
        $userA->assignRole('User');
        $userB = User::factory()->create();
        $userB->assignRole('User');
        app(LicenseService::class)->issueForAdmin($userA, $plan);
        app(LicenseService::class)->issueForAdmin($userB, $plan);

        $this->actingAs($userA)->post(route('my-tracking.requests.store'), [
            'identifier' => $userB->email,
            'relationship_name' => 'Friend',
        ]);
        $aToB = TrackingRelation::where('tracker_user_id', $userA->id)->firstOrFail();
        $this->actingAs($userB)->post(route('my-tracking.requests.accept', $aToB));

        $this->actingAs($userB)->get(route('live-map.index'))
            ->assertViewHas('trackedBy', fn ($trackers) => $trackers->pluck('user.id')->contains($userA->id));

        $this->actingAs($userB)->post(route('my-tracking.requests.back', $aToB))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tracking_relations', [
            'tracker_user_id' => $userB->id,
            'tracked_user_id' => $userA->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_can_enable_and_disable_self_tracking_from_live_map(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('User');
        app(LicenseService::class)->issueForAdmin($user, $this->plan());

        $this->actingAs($user)->post(route('live-map.self-tracking'))->assertRedirect();

        $this->assertTrue($user->trackingPreference()->firstOrFail()->self_tracking_enabled);
        $this->actingAs($user)->get(route('live-map.index'))
            ->assertViewHas('people', fn ($people) => $people->pluck('id')->contains($user->id));

        $this->actingAs($user)->post(route('live-map.self-tracking'))->assertRedirect();
        $this->assertFalse($user->trackingPreference()->firstOrFail()->self_tracking_enabled);
    }
}
