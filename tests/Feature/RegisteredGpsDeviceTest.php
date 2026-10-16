<?php

namespace Tests\Feature;

use App\Models\LicensePlan;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\Gps\GpsDeviceRegistrationService;
use App\Services\Gps\MobilePacketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RegisteredGpsDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $plan = LicensePlan::create(['name' => 'Registered GPS', 'type' => 'daily',
            'duration_in_days' => 1, 'price' => 1, 'renewal_price' => 1, 'is_free' => false, 'status' => 'active']);
        $license = UserLicense::create(['user_id' => $user->id, 'license_plan_id' => $plan->id,
            'license_number' => 'REGISTERED-TEST', 'purchase_date' => now(),
            'status' => 'active', 'payment_status' => 'paid', 'expiry_date' => now()->addDay()]);
        $registration = app(GpsDeviceRegistrationService::class)->register($user->id, $license->license_number, '123456789012345');
        $packet = ['user_id' => $user->id, 'license_number' => $license->license_number,
            'imei' => '123456789012345', 'device_key' => $registration['device_key'],
            'packet_id' => (string) str()->uuid(), 'recorded_at' => now()->toIso8601String(),
            'source_type' => 'android', 'latitude' => 28.6139, 'longitude' => 77.2090];

        return [$user, $license, $registration['device'], $packet];
    }

    public function test_location_is_saved_without_login_or_access_token(): void
    {
        [$user, $license, $device, $packet] = $this->fixture();
        $this->assertFalse($device->is_current);
        $this->assertNull($device->last_activity_at);
        $this->assertArrayNotHasKey('tracking_key_hash', $device->toArray());
        $this->assertSame(hash('sha256', $packet['device_key']), $device->tracking_key_hash);
        $reply = app(MobilePacketService::class)->handle(2, $packet);
        $this->assertTrue($reply['saved']);
        $this->assertSame($user->id, $reply['user_id']);
        $this->assertDatabaseHas('gps_locations', ['user_id' => $user->id, 'device_session_id' => $device->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_incorrect_identity_or_device_key_is_rejected(): void
    {
        [$user, $license, $device, $packet] = $this->fixture();
        foreach (['user_id' => 999999, 'imei' => '999999999999999',
            'license_number' => 'OTHER', 'device_key' => str_repeat('0', 64)] as $field => $value) {
            try {
                app(MobilePacketService::class)->handle(2, [...$packet, $field => $value]);
                $this->fail('Invalid identity accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertContains($e->getMessage(), ['unauthorized', 'device_revoked', 'invalid_license']);
            }
        }
        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_stop_and_resume_do_not_require_login(): void
    {
        [$user, $license, $device, $packet] = $this->fixture();
        $service = app(MobilePacketService::class);
        $service->handle(3, $packet);
        $service->handle(4, [...$packet, 'packet_id' => (string) str()->uuid()]);
        $this->assertFalse($device->fresh()->is_current);
        $service->handle(1, [...$packet, 'packet_id' => (string) str()->uuid()]);
        $this->assertTrue($device->fresh()->is_current);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rotation_rejects_old_key_even_for_previously_accepted_retry(): void
    {
        [$user, $license, $device, $packet] = $this->fixture();
        app(MobilePacketService::class)->handle(3, $packet);
        $new = app(GpsDeviceRegistrationService::class)->register($user->id, $license->license_number, $packet['imei'], true);
        try {
            app(MobilePacketService::class)->handle(3, $packet);
            $this->fail('Old key accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('unauthorized', $e->getMessage());
        }
        $this->assertTrue(app(MobilePacketService::class)->handle(3, [...$packet,
            'device_key' => $new['device_key'], 'packet_id' => (string) str()->uuid()])['ok']);
    }

    public function test_expired_license_and_revoked_device_are_rejected(): void
    {
        [$user, $license, $device, $packet] = $this->fixture();
        $license->update(['expiry_date' => now()->subMinute()]);
        try {
            app(MobilePacketService::class)->handle(3, $packet);
            $this->fail('Expired license accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('invalid_license', $e->getMessage());
        }
        $license->update(['expiry_date' => now()->addDay()]);
        $device->update(['tracking_revoked_at' => now(), 'tracking_key_hash' => null]);
        $this->expectExceptionMessage('device_revoked');
        app(MobilePacketService::class)->handle(3, $packet);
    }

    public function test_inactive_user_and_another_valid_unbound_license_are_rejected(): void
    {
        [$user, $license, $device, $packet] = $this->fixture();
        $other = $license->replicate(['uuid']);
        $other->license_number = 'SECOND-VALID-LICENSE';
        $other->save();
        try {
            app(MobilePacketService::class)->handle(3, [...$packet, 'license_number' => $other->license_number]);
            $this->fail('Unbound licence accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('invalid_license', $e->getMessage());
        }
        $user->update(['status' => 'inactive']);
        $this->expectExceptionMessage('unauthorized');
        app(MobilePacketService::class)->handle(3, $packet);
    }

    public function test_missing_or_mixed_credentials_fail_validation(): void
    {
        [$user, $license, $device, $packet] = $this->fixture();
        foreach ([
            array_diff_key($packet, ['device_key' => true]),
            array_diff_key($packet, ['user_id' => true]),
            [...$packet, 'token' => 'legacy-token'],
        ] as $invalid) {
            try {
                app(MobilePacketService::class)->handle(3, $invalid);
                $this->fail('Invalid credential mode accepted.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }
}
