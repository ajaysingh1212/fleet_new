<?php

namespace Tests\Feature\Admin;

use App\Models\LicensePlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class TrackingRelationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_plain_user_cannot_manage_tracking_relations(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.tracking-relations.index'))->assertForbidden();
    }

    public function test_a_license_stays_bound_to_the_same_tracked_user_after_relation_deletion(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedSettings();

        $plan = LicensePlan::create([
            'uuid' => Str::uuid(),
            'name' => 'Tracking Plan',
            'type' => 'yearly',
            'duration_in_days' => 365,
            'price' => 99,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $tracker = User::factory()->create();
        $tracked = User::factory()->create();
        app(LicenseService::class)->issueForAdmin($tracker, $plan);

        $createResponse = $this->post(route('admin.tracking-relations.store'), [
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Field Manager',
            'status' => 'active',
        ]);

        $createResponse->assertSessionHasNoErrors()->assertRedirect(route('admin.tracking-relations.index'));

        $license = $tracker->userLicenses()->first();
        $this->assertSame($tracked->id, $license->assigned_tracked_user_id);
        $this->assertNotNull($license->activation_date);
        $originalExpiry = $license->expiry_date;

        $relation = $tracker->trackedUsers()->first();
        $this->assertNotNull($relation);

        $deleteResponse = $this->delete(route('admin.tracking-relations.destroy', $relation));
        $deleteResponse->assertRedirect(route('admin.tracking-relations.index'));

        $this->assertSame($tracked->id, $license->fresh()->assigned_tracked_user_id);
        $this->assertEquals($originalExpiry, $license->fresh()->expiry_date);
        $this->assertSoftDeleted('tracking_relations', ['id' => $relation->id]);

        $otherTrackedUser = User::factory()->create();
        $this->post(route('admin.tracking-relations.store'), [
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $otherTrackedUser->id,
            'relationship_name' => 'Another Person',
            'status' => 'active',
        ])->assertSessionHasErrors('tracker_user_id');

        Carbon::setTestNow(now()->addDays(10));
        $restoreResponse = $this->post(route('admin.tracking-relations.store'), [
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Field Manager',
            'status' => 'active',
        ]);
        $restoreResponse->assertSessionHasNoErrors();
        $this->assertEquals($originalExpiry, $license->fresh()->expiry_date);
        Carbon::setTestNow();
    }

    public function test_creating_a_relation_without_a_license_fails_validation(): void
    {
        $this->actingAsSuperAdmin();
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();

        $response = $this->post(route('admin.tracking-relations.store'), [
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'No License',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('tracker_user_id');
        $this->assertDatabaseCount('tracking_relations', 0);
    }
}
