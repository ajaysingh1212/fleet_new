<?php

namespace Tests\Feature;

use App\Events\HardwareLocationUpdated;
use App\Events\HardwarePacketLive;
use App\Events\VehicleGeofenceAlertChanged;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DashboardFleetTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite' || $app['db']->connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Dashboard tests require isolated SQLite.');
        }

        return $app;
    }

    private function vehicle(?User $owner = null, string $status = 'active'): Vehicle
    {
        return Vehicle::create(['registration_number' => fake()->unique()->bothify('TEST-####-??'),
            'name' => 'Test vehicle', 'status' => $status, 'owner_user_id' => $owner?->id]);
    }

    public function test_admin_sees_every_vehicle_including_offline_inactive_and_without_tracker(): void
    {
        $this->actingAsSuperAdmin();
        $owner = User::factory()->create();
        $this->vehicle($owner);
        $this->vehicle(null, 'inactive');
        $this->vehicle(null, 'maintenance');
        $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonCount(3, 'vehicles')
            ->assertJsonPath('vehicles.1.authorization', 'inactive')->assertJsonPath('vehicles.0.latitude', null);
        $this->get(route('dashboard'))->assertOk()->assertSee('id="dashboard-map-tab"', false)
            ->assertSee('Information &amp; activity', false);
    }

    public function test_user_cannot_widen_ownership_scope_or_see_unassigned_vehicles(): void
    {
        $user = $this->actingAsPlainUser();
        $mine = $this->vehicle($user);
        $this->vehicle(User::factory()->create());
        $this->vehicle();
        $this->getJson(route('dashboard.fleet', ['owner_user_id' => 999, 'admin' => true]))
            ->assertOk()->assertJsonCount(1, 'vehicles')->assertJsonPath('vehicles.0.vehicle_id', $mine->id);
        $mine->update(['owner_user_id' => null]);
        $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonCount(0, 'vehicles');
        $this->get(route('dashboard'))->assertOk()->assertSee('Your fleet, in focus.');
    }

    public function test_cursor_loads_all_vehicles_and_does_not_drop_untracked_vehicles(): void
    {
        $this->actingAsSuperAdmin();
        $rows = [];
        for ($i = 1; $i <= 260; $i++) {
            $rows[] = ['registration_number' => 'BULK-'.$i, 'name' => 'Bulk', 'status' => 'active'];
        }
        Vehicle::insert($rows);
        $first = $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonCount(250, 'vehicles')->assertJsonPath('meta.has_more', true);
        $this->getJson(route('dashboard.fleet', ['after' => $first->json('meta.next_after')]))
            ->assertOk()->assertJsonCount(10, 'vehicles')->assertJsonPath('meta.has_more', false);
    }

    public function test_offline_gps_location_and_sensor_data_are_preserved(): void
    {
        $user = $this->actingAsPlainUser();
        $vehicle = $this->vehicle($user);
        $device = GpsDevice::create(['name' => 'Tracker', 'identifier' => '865167041931701', 'protocol' => 'pt06',
            'status' => 'active', 'vehicle_id' => $vehicle->id]);
        $fix = GpsDeviceLocation::create(['gps_device_id' => $device->id, 'vehicle_id' => $vehicle->id,
            'latitude' => 25.6, 'longitude' => 85.1, 'speed' => 32, 'satellites' => 8,
            'telemetry' => ['battery_voltage' => 4.1, 'raw_packet' => 'internal'], 'gps_fix' => true, 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2',
            'recorded_at' => now()->subHour(), 'received_at' => now()->subHour(), 'record_index' => 0, 'packet_hash' => hash('sha256', 'test')]);
        $device->update(['latest_location_id' => $fix->id]);
        $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonPath('vehicles.0.online', false)
            ->assertJsonPath('vehicles.0.latitude', 25.6)->assertJsonPath('vehicles.0.speed', 32)
            ->assertJsonPath('vehicles.0.sensors.battery_voltage', 4.1)
            ->assertJsonMissingPath('vehicles.0.sensors.raw_packet')->assertJsonMissingPath('vehicles.0.telemetry');
    }

    public function test_owner_broadcast_channel_is_private_and_events_follow_current_owner(): void
    {
        $user = $this->actingAsPlainUser();
        $other = User::factory()->create();
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $request = Request::create('/broadcasting/auth', 'POST', ['socket_id' => '123.456', 'channel_name' => 'private-dashboard.fleet.'.$other->id]);
        $request->setUserResolver(fn () => $user);
        try {
            Broadcast::driver('reverb')->auth($request);
            $this->fail('Other owner channel must be denied.');
        } catch (AccessDeniedHttpException) {
            $this->assertTrue(true);
        }
        $request->merge(['channel_name' => 'private-dashboard.fleet.'.$user->id]);
        $this->assertNotEmpty(Broadcast::driver('reverb')->auth($request));
        $vehicle = $this->vehicle($user);
        $device = GpsDevice::create(['name' => 'Tracker', 'identifier' => '865167041931701', 'protocol' => 'pt06', 'status' => 'active', 'vehicle_id' => $vehicle->id]);
        $event = new HardwareLocationUpdated(['device_id' => $device->id]);
        $this->assertContains('private-dashboard.fleet.'.$user->id, array_map(strval(...), $event->broadcastOn()));
        $vehicle->update(['owner_user_id' => $other->id]);
        $alert = new VehicleGeofenceAlertChanged(['device_id' => $device->id, 'vehicle_id' => $vehicle->id]);
        $this->assertContains('private-dashboard.fleet.'.$other->id, array_map(strval(...), $alert->broadcastOn()));
        $this->assertNotContains('private-dashboard.fleet.'.$user->id, array_map(strval(...), $event->broadcastOn()));
        $live = new HardwarePacketLive(['device_id' => $device->id, 'vehicle_id' => $vehicle->id]);
        $this->assertContains('private-dashboard.fleet.'.$other->id, array_map(strval(...), $live->broadcastOn()));
        $device->update(['vehicle_id' => null]);
        $this->assertCount(1, $live->broadcastOn());
        $this->assertCount(1, $alert->broadcastOn());
    }

    public function test_geofence_rules_are_limited_to_owned_vehicles(): void
    {
        $this->actingAsSuperAdmin();
        $owner = User::factory()->create();
        $mine = $this->vehicle($owner);
        $other = $this->vehicle();
        foreach ([$mine, $other] as $vehicle) {
            $this->postJson(route('admin.vehicle-geofences.store'), [
                'name' => 'Zone '.$vehicle->id, 'category' => 'warehouse', 'type' => 'circle',
                'center_lat' => 25.6, 'center_lng' => 85.1, 'radius_meters' => 200,
                'vehicle_id' => $vehicle->id,
            ])->assertCreated();
        }
        $this->actingAs($owner);
        $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonCount(1, 'zones')
            ->assertJsonPath('zones.0.vehicle_id', $mine->id)->assertJsonCount(0, 'alerts');
    }

    public function test_guests_cannot_read_fleet(): void
    {
        $this->getJson(route('dashboard.fleet'))->assertUnauthorized();
    }

    public function test_admin_also_gets_each_unregistered_tracker_with_its_imei_and_gps_fix(): void
    {
        $this->actingAsSuperAdmin();
        $vehicle = $this->vehicle();
        foreach (['864540081540460', '861128068689493'] as $imei) {
            $device = GpsDevice::create(['name' => 'Unregistered tracker', 'identifier' => $imei, 'protocol' => 'pt06', 'status' => 'active']);
            $fix = GpsDeviceLocation::create(['gps_device_id' => $device->id, 'vehicle_id' => null,
                'latitude' => 25.6, 'longitude' => 85.1, 'speed' => 0, 'gps_fix' => true,
                'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2', 'record_index' => 0,
                'recorded_at' => now()->subHour(), 'received_at' => now()->subHour(), 'packet_hash' => hash('sha256', $imei)]);
            $device->update(['latest_location_id' => $fix->id]);
        }
        $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonCount(1, 'vehicles')
            ->assertJsonPath('vehicles.0.vehicle_id', $vehicle->id)->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.next_kind', 'devices')->assertJsonPath('meta.next_after', 0);
        $this->getJson(route('dashboard.fleet', ['kind' => 'devices']))->assertOk()->assertJsonCount(2, 'vehicles')
            ->assertJsonPath('vehicles.0.imei', '864540081540460')->assertJsonPath('vehicles.1.imei', '861128068689493')
            ->assertJsonPath('vehicles.0.latitude', 25.6)->assertJsonPath('vehicles.1.latitude', 25.6)
            ->assertJsonPath('meta.has_more', false);
        $this->actingAsPlainUser();
        $this->getJson(route('dashboard.fleet', ['kind' => 'devices']))->assertOk()->assertJsonCount(0, 'vehicles');
    }

    public function test_unregistered_tracker_pagination_is_independent_of_vehicle_ids(): void
    {
        $this->actingAsSuperAdmin();
        $rows = [];
        for ($i = 1; $i <= 251; $i++) {
            $rows[] = ['name' => 'Tracker', 'identifier' => (string) (860000000000000 + $i), 'protocol' => 'pt06', 'status' => 'inactive'];
        }
        GpsDevice::insert($rows);
        $first = $this->getJson(route('dashboard.fleet', ['kind' => 'devices']))
            ->assertOk()->assertJsonCount(250, 'vehicles')->assertJsonPath('meta.next_kind', 'devices');
        $this->getJson(route('dashboard.fleet', ['kind' => 'devices', 'after' => $first->json('meta.next_after')]))
            ->assertOk()->assertJsonCount(1, 'vehicles')->assertJsonPath('meta.has_more', false);
    }
}
