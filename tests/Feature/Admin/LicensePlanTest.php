<?php

namespace Tests\Feature\Admin;

use App\Models\LicensePlan;
use App\Enums\LicenseType;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class LicensePlanTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_plain_user_cannot_manage_license_plans(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.license-plans.index'))->assertForbidden();
    }

    public function test_daily_and_weekly_license_types_are_available(): void
    {
        $this->assertSame('Daily', LicenseType::Daily->label());
        $this->assertSame('Weekly', LicenseType::Weekly->label());
    }

    public function test_super_admin_can_create_daily_and_weekly_license_plans(): void
    {
        $this->actingAsSuperAdmin();

        foreach ([['Daily Plan', 'daily', 1], ['Weekly Plan', 'weekly', 7]] as [$name, $type, $days]) {
            $this->post(route('admin.license-plans.store'), [
                'name' => $name,
                'type' => $type,
                'duration_in_days' => $days,
                'price' => 10,
                'renewal_price' => 8,
                'is_free' => false,
                'status' => 'active',
                'display_order' => 1,
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('license_plans', ['name' => 'Daily Plan', 'type' => 'daily', 'duration_in_days' => 1]);
        $this->assertDatabaseHas('license_plans', ['name' => 'Weekly Plan', 'type' => 'weekly', 'duration_in_days' => 7]);
    }

    public function test_super_admin_can_create_a_license_plan(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('admin.license-plans.store'), [
            'name' => 'Test Plan',
            'type' => 'monthly',
            'duration_in_days' => 30,
            'price' => 49.99,
            'renewal_price' => 19.99,
            'is_free' => false,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.license-plans.index'));
        $this->assertDatabaseHas('license_plans', ['name' => 'Test Plan']);
    }

    public function test_super_admin_can_update_a_license_plan(): void
    {
        $this->actingAsSuperAdmin();
        $plan = LicensePlan::create([
            'uuid' => Str::uuid(),
            'name' => 'Original Plan',
            'type' => 'monthly',
            'duration_in_days' => 30,
            'price' => 19.99,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $response = $this->put(route('admin.license-plans.update', $plan), [
            'name' => 'Renamed Plan',
            'type' => 'monthly',
            'duration_in_days' => 30,
            'price' => 29.99,
            'renewal_price' => 19.99,
            'is_free' => false,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.license-plans.index'));
        $this->assertSame('Renamed Plan', $plan->fresh()->name);
    }

    public function test_license_plan_with_active_licenses_cannot_be_deleted(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $plan = LicensePlan::create([
            'uuid' => Str::uuid(),
            'name' => 'In Use Plan',
            'type' => 'lifetime',
            'price' => 199,
            'status' => 'active',
            'display_order' => 1,
        ]);

        app(LicenseService::class)->purchase($admin, $plan);

        $this->delete(route('admin.license-plans.destroy', $plan))->assertForbidden();
        $this->assertDatabaseHas('license_plans', ['id' => $plan->id]);
    }
}
