<?php

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\LicensePlan;
use App\Models\DeviceSession;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveMapTest extends TestCase
{
    use RefreshDatabase;

    private function connect(User $tracker, User $tracked, ?\Illuminate\Support\Carbon $expiresAt = null): void
    {
        $license = UserLicense::create([
            'user_id' => $tracker->id,
            'assigned_tracked_user_id' => $tracked->id,
            'license_plan_id' => LicensePlan::firstOrCreate(
                ['name' => 'Test Daily'],
                ['type' => LicenseType::Daily, 'duration_in_days' => 1, 'price' => 1, 'renewal_price' => 1, 'is_free' => false, 'status' => UserStatus::Active],
            )->id,
            'license_number' => 'TEST-'.str()->uuid(),
            'purchase_date' => now(),
            'activation_date' => now(),
            'expiry_date' => $expiresAt ?? now()->addDay(),
            'status' => LicenseStatus::Active,
            'payment_status' => PaymentStatus::Paid,
        ]);

        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'user_license_id' => $license->id,
            'relationship_name' => 'Test Relation',
            'status' => 'active',
        ]);
    }

    public function test_a_user_with_no_tracked_people_sees_an_empty_page(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('User');

        $response = $this->actingAs($user)->get(route('live-map.index'));

        $response->assertOk();
        $response->assertViewHas('people', fn ($people) => $people->isEmpty());
    }

    public function test_marker_preferences_save_per_viewer_and_reject_unrelated_users(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $tracker = User::factory()->create();
        $tracker->assignRole('User');
        $tracked = User::factory()->create();
        $stranger = User::factory()->create();
        $this->connect($tracker, $tracked);
        $this->actingAs($tracker)->postJson(route('live-map.marker.update', $tracked), ['kind' => 'bike'])
            ->assertOk()->assertJsonPath('kind', 'bike');
        $this->get(route('live-map.snapshot'))->assertOk()->assertJsonPath('people.0.marker.kind', 'bike');
        $this->postJson(route('live-map.marker.update', $stranger), ['kind' => 'car'])->assertForbidden();
        $this->postJson(route('live-map.marker.update', $tracked), ['kind' => 'invalid'])->assertUnprocessable();
        $this->actingAs($stranger)->get(route('live-map.marker.image', $tracked))->assertForbidden();
    }

    public function test_custom_marker_image_can_be_uploaded_replaced_and_removed(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $tracker = User::factory()->create();
        $tracker->assignRole('User');
        $tracked = User::factory()->create();
        $this->connect($tracker, $tracked);
        $this->actingAs($tracker)->postJson(route('live-map.marker.update', $tracked), ['kind' => 'other'])
            ->assertUnprocessable();
        $this->postJson(route('live-map.marker.update', $tracked), [
            'kind' => 'other', 'image' => \Illuminate\Http\UploadedFile::fake()->image('marker.png'),
        ])->assertOk()->assertJsonPath('kind', 'other');
        $preference = \App\Models\MapMarkerPreference::firstOrFail();
        $path = $preference->image_path;
        $this->get(route('live-map.marker.image', $tracked))->assertOk();
        $this->postJson(route('live-map.marker.update', $tracked), [
            'kind' => 'other', 'image' => \Illuminate\Http\UploadedFile::fake()->image('new.png'),
        ])->assertOk();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
        $path = $preference->fresh()->image_path;
        $this->postJson(route('live-map.marker.update', $tracked), ['kind' => 'car'])->assertOk();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
        $this->get(route('live-map.marker.image', $tracked))->assertNotFound();
    }

    public function test_a_regular_user_only_sees_their_tracked_people(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tracker = User::factory()->create();
        $tracker->assignRole('User');

        $tracked = User::factory()->create();
        $stranger = User::factory()->create();

        $this->connect($tracker, $tracked);

        $response = $this->actingAs($tracker)->get(route('live-map.index'));

        $response->assertOk();
        $response->assertViewHas('people', function ($people) use ($tracked, $stranger) {
            $ids = $people->pluck('id');

            return $ids->contains($tracked->id) && ! $ids->contains($stranger->id);
        });
    }

    public function test_a_tracked_person_shows_online_as_soon_as_they_log_in_even_without_a_gps_ping(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tracker = User::factory()->create();
        $tracker->assignRole('Admin');

        $tracked = User::factory()->create();

        $this->connect($tracker, $tracked);

        // Simulate a logged-in, still-active session — exactly what
        // AuthenticatedSessionController::store() writes on real login —
        // with no GPS location ever recorded for this user.
        DeviceSession::create([
            'user_id' => $tracked->id,
            'session_id' => 'test-session-id',
            'device_name' => 'Test Browser',
            'is_current' => true,
            'last_login_at' => now(),
            'last_activity_at' => now(),
            'logged_out_at' => null,
        ]);

        $response = $this->actingAs($tracker)->get(route('live-map.index'));

        $response->assertOk();
        $response->assertViewHas('people', function ($people) use ($tracked) {
            $person = $people->firstWhere('id', $tracked->id);

            return $person !== null && $person['isOnline'] === true && $person['lat'] === null;
        });
    }

    public function test_expired_license_keeps_renewal_row_without_location_access(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tracker = User::factory()->create();
        $tracker->assignRole('User');

        $tracked = User::factory()->create();
        $this->connect($tracker, $tracked, now()->subDay());

        $response = $this->actingAs($tracker)->get(route('live-map.index'));

        $response->assertOk();
        $response->assertViewHas('people', function ($people) use ($tracked) {
            $person = $people->firstWhere('id', $tracked->id);
            return $person && $person['licenseExpired'] && ! $person['canReport']
                && $person['lat'] === null && ! $person['canRouteHistory'];
        });
        $response->assertSee('License expired')->assertSee('Renew');
        $this->get(route('my-licenses.index'))->assertOk()->assertSee('Expired');
    }

    public function test_a_privileged_role_sees_all_users(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $others = User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('live-map.index'));

        $response->assertOk();
        $response->assertViewHas('people', function ($people) use ($others) {
            $ids = $people->pluck('id');

            return $others->every(fn ($other) => $ids->contains($other->id));
        });
    }
}
