<?php

namespace Tests\Feature\Admin;

use App\Models\LicensePlan;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserLicenseTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function makePlan(): LicensePlan
    {
        return LicensePlan::create([
            'uuid' => Str::uuid(),
            'name' => 'Quarterly Plan',
            'type' => 'quarterly',
            'duration_in_days' => 90,
            'price' => 79.99,
            'status' => 'active',
            'display_order' => 1,
        ]);
    }

    public function test_plain_user_cannot_manage_user_licenses(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.user-licenses.index'))->assertForbidden();
    }

    public function test_super_admin_can_assign_a_license_to_a_user(): void
    {
        $this->actingAsSuperAdmin();
        $plan = $this->makePlan();
        $target = User::factory()->create();

        $response = $this->post(route('admin.user-licenses.store'), [
            'user_id' => $target->id,
            'license_plan_id' => $plan->id,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.user-licenses.index'));
        $this->assertDatabaseHas('user_licenses', [
            'user_id' => $target->id,
            'license_plan_id' => $plan->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_license_list_renders_the_assigned_tracked_user(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $license = app(LicenseService::class)->issueForAdmin($admin, $this->makePlan());
        $trackedUser = User::factory()->create(['name' => 'Tracked Employee']);
        $license->update(['assigned_tracked_user_id' => $trackedUser->id]);

        $this->get(route('admin.user-licenses.index'))
            ->assertOk()
            ->assertSee('Tracked Employee');

        $this->get(route('admin.user-licenses.show', $license))
            ->assertOk()
            ->assertSee('Tracked Employee');
    }

    public function test_super_admin_can_extend_and_cancel_a_license(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $plan = $this->makePlan();
        $license = app(LicenseService::class)->issueForAdmin($admin, $plan);

        $extendResponse = $this->post(route('admin.user-licenses.extend', $license), ['days' => 30]);
        $extendResponse->assertRedirect();
        $this->assertTrue($license->fresh()->expiry_date->isAfter(now()->addDays(29)));

        $cancelResponse = $this->post(route('admin.user-licenses.cancel', $license));
        $cancelResponse->assertRedirect();
        $this->assertSame('cancelled', $license->fresh()->status->value);
    }
}
