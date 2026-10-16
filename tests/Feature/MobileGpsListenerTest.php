<?php

namespace Tests\Feature;

use App\DTO\LocationUpdateData;
use App\Events\GpsLocationUpdated;
use App\Models\DeviceSession;
use App\Models\LicensePlan;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\Gps\GpsIngestionService;
use App\Services\Gps\HexPacketCodec;
use App\Services\Gps\LiveLocationService;
use App\Services\Gps\MobilePacketService;
use App\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class MobileGpsListenerTest extends TestCase
{
    use RefreshDatabase;

    private function setupDevice(string $imei = '123456789012345'): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $device = DeviceSession::create(['user_id' => $user->id, 'session_id' => $imei,
            'is_current' => true, 'last_activity_at' => now()]);
        $plan = LicensePlan::create(['name' => 'Listener test', 'type' => 'daily',
            'duration_in_days' => 1, 'price' => 1, 'renewal_price' => 1, 'is_free' => false, 'status' => 'active']);
        $license = UserLicense::create(['user_id' => $user->id, 'license_plan_id' => $plan->id,
            'license_number' => 'MOBILE-TEST', 'purchase_date' => now(),
            'status' => 'active', 'payment_status' => 'paid', 'expiry_date' => now()->addDay()]);

        return [$user, $device, $license, [
            'imei' => $imei, 'license_number' => $license->license_number,
            'token' => $user->createToken($imei)->plainTextToken,
            'packet_id' => (string) str()->uuid(), 'recorded_at' => now()->toIso8601String(),
            'source_type' => 'android', 'latitude' => 28.6139, 'longitude' => 77.2090,
            'battery_level' => 85, 'speed' => 1.5, 'heading' => 90,
        ]];
    }

    public function test_hex_roundtrip_and_corruption_rejection(): void
    {
        $codec = new HexPacketCodec;
        $frame = $codec->encode(2, ['imei' => '123456789012345']);
        $this->assertSame(['code' => 2, 'payload' => ['imei' => '123456789012345']], $codec->decode($frame));
        $frame[16] = $frame[16] === '0' ? '1' : '0';
        $this->expectException(InvalidArgumentException::class);
        $codec->decode($frame);
    }

    public function test_mobile_location_is_saved_and_retry_is_idempotent(): void
    {
        [$user, $device, $license, $packet] = $this->setupDevice();
        $service = app(MobilePacketService::class);
        $reply = $service->handle(2, $packet);
        $this->assertTrue($reply['saved']);
        $this->assertTrue($reply['live_cached']);
        $this->assertTrue($service->handle(2, $packet)['duplicate']);
        $this->assertDatabaseCount('gps_locations', 1);
        $this->assertSame($device->id, $user->fresh()->deviceSessions()->first()->id);
    }

    public function test_radius_filters_history_but_live_location_keeps_moving(): void
    {
        Event::fake([GpsLocationUpdated::class]);
        [$user, $device, $license, $packet] = $this->setupDevice();
        app(SettingsService::class)->set('system', 'location_save_radius_meters', 100, 'integer');
        $service = app(MobilePacketService::class);
        $service->handle(2, $packet);
        $this->travel(10)->seconds();
        $packet['packet_id'] = (string) str()->uuid();
        $packet['recorded_at'] = now()->toIso8601String();
        $packet['latitude'] += 0.0001;
        $reply = $service->handle(2, $packet);
        $this->assertFalse($reply['saved']);
        $this->assertTrue($reply['live_cached']);
        $this->assertSame('throttled', $reply['reason']);
        $this->assertDatabaseCount('gps_locations', 1);
        $this->assertEquals($packet['latitude'], (float) app(LiveLocationService::class)->latest($user->id)->latitude);
        Event::assertDispatched(GpsLocationUpdated::class);
        $this->travel(130)->seconds();
        $packet['packet_id'] = (string) str()->uuid();
        $packet['recorded_at'] = now()->toIso8601String();
        $packet['bearing'] = 180;
        $packet['speed'] = 30;
        $this->assertFalse($service->handle(2, $packet)['saved']);
        $this->assertDatabaseCount('gps_locations', 1);
        $this->travel(10)->seconds();
        $packet['packet_id'] = (string) str()->uuid();
        $packet['recorded_at'] = now()->toIso8601String();
        $packet['latitude'] += 0.001;
        $this->assertTrue($service->handle(2, $packet)['saved']);
        $this->assertDatabaseCount('gps_locations', 2);
    }

    public function test_heartbeat_logout_and_presence_expiry(): void
    {
        [$user, $device, $license, $packet] = $this->setupDevice();
        $service = app(MobilePacketService::class);
        $service->handle(3, $packet);
        $this->assertTrue(app(\App\Services\UserPresenceService::class)->isOnline($user->id));
        $this->assertDatabaseCount('gps_locations', 0);
        $this->travel(91)->seconds();
        $this->assertFalse(app(\App\Services\UserPresenceService::class)->isOnline($user->id));
        $packet['packet_id'] = (string) str()->uuid();
        $packet['recorded_at'] = now()->toIso8601String();
        $service->handle(4, $packet);
        $this->assertNotNull($device->fresh()->logged_out_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_imei_and_invalid_license_cannot_submit_locations(): void
    {
        [$user, $device, $license, $packet] = $this->setupDevice();
        foreach (['imei' => '999999999999999', 'license_number' => 'OTHER-LICENSE'] as $field => $value) {
            try {
                app(MobilePacketService::class)->handle(2, [...$packet, $field => $value]);
                $this->fail('Unauthorized packet was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertContains($exception->getMessage(), ['unauthorized', 'invalid_license']);
            }
        }
        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_installation_uuid_is_supported_for_apps_without_imei_access(): void
    {
        [$user, $device, $license, $packet] = $this->setupDevice((string) str()->uuid());
        $this->assertTrue(app(MobilePacketService::class)->handle(2, $packet)['saved']);
    }

    public function test_geofence_exit_is_evaluated_without_saving_a_subradius_location(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        [$user, $device, $license, $packet] = $this->setupDevice();
        $tracker = User::factory()->create();
        $license->update(['user_id' => $tracker->id, 'assigned_tracked_user_id' => $user->id]);
        TrackingRelation::create(['tracker_user_id' => $tracker->id, 'tracked_user_id' => $user->id,
            'user_license_id' => $license->id, 'relationship_name' => 'Friend', 'status' => 'active']);
        $zone = \App\Models\Geofence::create(['name' => 'Small Zone', 'type' => 'circle',
            'category' => 'home', 'status' => 'active', 'center_lat' => $packet['latitude'],
            'center_lng' => $packet['longitude'], 'radius_meters' => 5, 'created_by' => $tracker->id]);
        \App\Models\GeofenceAssignment::create(['geofence_id' => $zone->id, 'user_id' => $user->id,
            'assigned_by' => $tracker->id, 'schedule_type' => 'daily', 'alert_on_exit' => true, 'status' => 'active']);
        app(SettingsService::class)->set('system', 'location_save_radius_meters', 100, 'integer');
        app(MobilePacketService::class)->handle(2, $packet);
        $this->travel(10)->seconds();
        $packet['packet_id'] = (string) str()->uuid();
        $packet['recorded_at'] = now()->toIso8601String();
        $packet['latitude'] += 0.0001;
        $this->assertFalse(app(MobilePacketService::class)->handle(2, $packet)['saved']);
        $this->assertDatabaseCount('gps_locations', 1);
        $this->assertDatabaseHas('geofence_events', ['user_id' => $user->id, 'type' => 'exited']);
        \Illuminate\Support\Facades\Notification::assertSentTo($tracker, \App\Notifications\GeofenceComplianceNotification::class);
    }

    public function test_revoked_devices_expired_licenses_and_tokens_are_rejected(): void
    {
        [$user, $device, $license, $packet] = $this->setupDevice();
        $device->update(['is_current' => false]);
        try {
            app(MobilePacketService::class)->handle(2, $packet);
            $this->fail('Revoked device accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('device_revoked', $exception->getMessage());
        }
        $device->update(['is_current' => true]);
        $license->update(['expiry_date' => now()->subMinute()]);
        try {
            app(MobilePacketService::class)->handle(2, $packet);
            $this->fail('Expired license accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('invalid_license', $exception->getMessage());
        }
        $license->update(['expiry_date' => now()->addDay()]);
        $user->tokens()->update(['expires_at' => now()->subMinute()]);
        $this->expectExceptionMessage('unauthorized');
        app(MobilePacketService::class)->handle(2, $packet);
    }

    public function test_packet_id_conflicts_and_future_timestamps_are_rejected(): void
    {
        [$user, $device, $license, $packet] = $this->setupDevice();
        app(MobilePacketService::class)->handle(2, $packet);
        try {
            app(MobilePacketService::class)->handle(2, [...$packet, 'latitude' => 29]);
            $this->fail('Changed retry accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('packet_id_conflict', $exception->getMessage());
        }
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MobilePacketService::class)->handle(2, [...$packet,
            'packet_id' => (string) str()->uuid(), 'recorded_at' => now()->addMinute()->toIso8601String()]);
    }

    public function test_admin_can_set_radius_and_cannot_save_invalid_radius(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Location Save Radius Meters');
        $this->put('/admin/settings', ['group' => 'system', 'values' => ['location_save_radius_meters' => 150]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(150, app(SettingsService::class)->locationSaveRadius($admin));
        $this->put('/admin/settings', ['group' => 'system', 'values' => ['location_save_radius_meters' => 0]])
            ->assertSessionHasErrors('values.location_save_radius_meters');
        $this->assertSame(150, app(SettingsService::class)->locationSaveRadius($admin));
    }

    public function test_snapshot_is_scoped_and_geofence_access_works_without_new_permission_seeding(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        [$tracked, $device, $license, $packet] = $this->setupDevice();
        $tracker = User::factory()->create();
        $tracker->assignRole('User');
        $tracker->revokePermissionTo('manage tracked geofences');
        $tracker->syncRoles([]);
        $tracker->givePermissionTo('use tracking workspace');
        $license->update(['user_id' => $tracker->id, 'assigned_tracked_user_id' => $tracked->id]);
        TrackingRelation::create(['tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id, 'user_license_id' => $license->id,
            'relationship_name' => 'Friend', 'status' => 'active']);
        $stranger = User::factory()->create();
        app(MobilePacketService::class)->handle(2, $packet);
        $this->actingAs($tracker)->get('/admin/geofences')->assertOk();
        $this->getJson('/live-map/snapshot')->assertOk()->assertJsonCount(1, 'people')
            ->assertJsonPath('people.0.id', $tracked->id);
        $zone = $this->postJson('/api/v1/geofences', ['name' => 'My Zone', 'type' => 'circle',
            'category' => 'home', 'center_lat' => 28.6139, 'center_lng' => 77.2090, 'radius_meters' => 200])
            ->assertCreated()->json('data.uuid');
        $this->postJson('/api/v1/geofence-assignments', ['geofence_uuid' => $zone,
            'user_id' => $tracked->id, 'schedule_type' => 'daily'])->assertCreated();
        $this->postJson('/api/v1/geofence-assignments', ['geofence_uuid' => $zone,
            'user_id' => $stranger->id, 'schedule_type' => 'daily'])->assertForbidden();
        $this->actingAs($stranger)->getJson('/live-map/snapshot')->assertJsonCount(0, 'people');
    }
}
