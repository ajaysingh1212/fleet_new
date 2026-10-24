<?php

namespace Tests\Feature;

use App\Events\VehicleGeofenceAlertChanged;
use App\Events\VehicleGeofenceRulesChanged;
use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use App\Services\Geofence\VehicleGeofenceMonitor;
use App\Services\Gps\Gateway\Connection;
use App\Services\Gps\Gateway\Location;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VehicleGeofenceTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite' || $app['db']->connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated SQLite required.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([VehicleGeofenceAlertChanged::class, VehicleGeofenceRulesChanged::class]);
    }

    private function setupRule(): array
    {
        $this->actingAsSuperAdmin();
        $v = Vehicle::create(['registration_number' => 'TEST-001', 'name' => 'Test vehicle', 'status' => 'active']);
        $d = GpsDevice::create(['name' => 'Tracker', 'identifier' => '865167041931701', 'vehicle_id' => $v->id, 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2', 'status' => 'active']);
        $payload = ['name' => 'Depot', 'category' => 'warehouse', 'type' => 'circle', 'center_lat' => 25.6, 'center_lng' => 85.1, 'radius_meters' => 200, 'min_speed_kmh' => 10, 'max_speed_kmh' => 60, 'vehicle_id' => $v->id];
        $id = $this->postJson(route('admin.vehicle-geofences.store'), $payload)->assertCreated()->json('id');

        return [$v, $d, $id, $payload];
    }

    private function position(GpsDevice $d, float $lat, float $speed): void
    {
        $this->travel(2)->seconds();
        app(VehicleGeofenceMonitor::class)->evaluate($d, ['gps_fix' => true, 'latitude' => $lat, 'longitude' => 85.1, 'speed' => $speed, 'recorded_at' => now()->utc()->format('Y-m-d H:i:s')]);
    }

    public function test_boundary_and_speed_episodes_record_repeated_breaches_without_per_packet_spam(): void
    {
        [$v,$d] = $this->setupRule();
        $this->position($d, 25.6, 20);
        $this->assertDatabaseCount('vehicle_geofence_alerts', 0);
        $this->position($d, 26, 80);
        $this->assertDatabaseCount('vehicle_geofence_alerts', 2);
        $this->position($d, 26, 90);
        $this->assertDatabaseCount('vehicle_geofence_alerts', 2);
        $this->position($d, 25.6, 20);
        $this->assertSame(0, DB::table('vehicle_geofence_alerts')->whereNull('ended_at')->count());
        $this->position($d, 26, 5);
        $this->assertDatabaseCount('vehicle_geofence_alerts', 4);
        $this->assertDatabaseHas('vehicle_geofence_alerts', ['type' => 'underspeed', 'limit' => 10]);
        $this->position($d, 26, 0);
        $this->assertSame(1, DB::table('vehicle_geofence_alerts')->whereNull('ended_at')->count());
        Event::assertDispatched(VehicleGeofenceAlertChanged::class, fn ($e) => $e->payload['registration'] === 'TEST-001' && count($e->payload['active_alerts']) === 2);
        $this->getJson(route('admin.vehicle-geofences.alerts', ['vehicle_id' => $v->id]))->assertOk()->assertJsonPath('total', 4);
    }

    public function test_invalid_old_duplicate_and_unassigned_fixes_do_not_fabricate_alerts(): void
    {
        [$v,$d] = $this->setupRule();
        $this->position($d, 25.6, 20);
        $p = ['gps_fix' => true, 'latitude' => 26, 'longitude' => 85.1, 'speed' => 100, 'recorded_at' => now()->utc()->format('Y-m-d H:i:s')];
        $monitor = app(VehicleGeofenceMonitor::class);
        $monitor->evaluate($d, $p);
        $p['recorded_at'] = now()->subMinutes(10)->utc()->format('Y-m-d H:i:s');
        $monitor->evaluate($d, $p);
        $p['recorded_at'] = now()->addSecond()->utc()->format('Y-m-d H:i:s');
        $p['gps_fix'] = false;
        $monitor->evaluate($d, $p);
        $this->assertDatabaseCount('vehicle_geofence_alerts', 0);
    }

    public function test_vehicle_selection_geometry_validation_and_pause_close_current_alerts(): void
    {
        [$v,$d,$id,$payload] = $this->setupRule();
        $this->postJson(route('admin.vehicle-geofences.store'), [...$payload, 'vehicle_id' => 99999])->assertUnprocessable();
        $this->postJson(route('admin.vehicle-geofences.store'), [...$payload, 'min_speed_kmh' => 80, 'max_speed_kmh' => 40])->assertUnprocessable();
        $this->position($d, 26, 80);
        $this->patchJson(route('admin.vehicle-geofences.status', $id), ['active' => false])->assertOk();
        $this->assertSame(0, DB::table('vehicle_geofence_alerts')->whereNull('ended_at')->count());
        $this->position($d, 26, 80);
        $this->assertDatabaseCount('vehicle_geofence_alerts', 2);
        $this->getJson(route('admin.vehicle-geofences.index'))->assertJsonPath('data.0.vehicle_id', $v->id)->assertJsonPath('data.0.status', 'inactive');
        $this->get(route('admin.geofences.index'))->assertOk()->assertSee('Vehicle / IMEI')->assertSee('geofence-create-user', false)->assertSee('tracker-geofence-shell', false);
    }

    public function test_unassigned_imei_can_be_selected_edited_monitored_and_deleted_with_history(): void
    {
        [$vehicle, $device, $oldRule, $payload] = $this->setupRule();
        $device->update(['vehicle_id' => null]);
        $device->refresh();
        $this->getJson(route('admin.vehicle-geofences.targets', ['q' => $device->identifier]))
            ->assertOk()->assertJsonPath('data.0.imei', $device->identifier)->assertJsonPath('data.0.registration_number', null);
        $payload['vehicle_id'] = null;
        $payload['gps_device_id'] = $device->id;
        $this->postJson(route('admin.vehicle-geofences.store'), [...$payload, 'vehicle_id' => $vehicle->id])->assertUnprocessable();
        $id = $this->postJson(route('admin.vehicle-geofences.store'), $payload)->assertCreated()->json('id');
        $this->position($device, 26, 80);
        $this->assertDatabaseHas('vehicle_geofence_alerts', ['rule_id' => $id, 'vehicle_id' => null, 'gps_device_id' => $device->id, 'type' => 'outside']);
        $this->getJson(route('admin.vehicle-geofences.alerts', ['active' => 1, 'device_ids' => [$device->id]]))
            ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.imei', $device->identifier);
        $this->getJson(route('admin.vehicle-geofences.index', ['device_ids' => [$device->id]]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.gps_device_id', $device->id)->assertJsonPath('data.0.imei', $device->identifier);
        $this->putJson(route('admin.vehicle-geofences.update', $id), [...$payload, 'name' => 'Edited depot', 'radius_meters' => 500])->assertOk();
        $this->assertSame(0, DB::table('vehicle_geofence_alerts')->where('rule_id', $id)->whereNull('ended_at')->count());
        $this->position($device, 26, 80);
        $this->deleteJson(route('admin.vehicle-geofences.destroy', $id))->assertOk();
        $this->assertSoftDeleted('geofences', ['id' => DB::table('vehicle_geofence_rules')->where('id', $id)->value('geofence_id')]);
        $this->position($device, 26, 80);
        $this->assertSame(4, DB::table('vehicle_geofence_alerts')->where('rule_id', $id)->count());
        $this->assertSame(0, DB::table('vehicle_geofence_alerts')->whereNull('ended_at')->count());
        $this->getJson(route('admin.vehicle-geofences.index', ['device_ids' => [$device->id]]))->assertJsonCount(0, 'data');
        $this->getJson(route('admin.vehicle-geofences.alerts', ['device_ids' => [$device->id]]))->assertJsonPath('total', 4);
        $this->putJson(route('admin.vehicle-geofences.update', $id), $payload)->assertNotFound();
    }

    public function test_edit_can_switch_a_vehicle_rule_to_an_unassigned_imei_and_worker_evaluates_it(): void
    {
        [$vehicle, $oldDevice, $id, $payload] = $this->setupRule();
        $device = GpsDevice::create(['name' => 'Unassigned', 'identifier' => '865167041931702', 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2', 'status' => 'active']);
        $this->putJson(route('admin.vehicle-geofences.update', $id), [...$payload, 'vehicle_id' => null])->assertUnprocessable();
        $this->putJson(route('admin.vehicle-geofences.update', $id), [...$payload, 'vehicle_id' => null, 'gps_device_id' => 99999])->assertUnprocessable();
        $this->position($oldDevice, 26, 80);
        $this->putJson(route('admin.vehicle-geofences.update', $id), [...$payload, 'vehicle_id' => null, 'gps_device_id' => $device->id])->assertOk();
        $this->position($oldDevice, 26, 80);
        $this->assertSame(0, DB::table('vehicle_geofence_alerts')->whereNull('ended_at')->count());
        $this->travel(2)->seconds();
        $c = new Connection('127.0.0.1');
        $point = (new Location(26, 85.1, CarbonImmutable::now('UTC'), 80, gpsFix: true))->toArray();
        (new ProcessHardwarePacket($device->id, $device->identifier, $c->id, $c->connectedAt, $c->peer, 'pt06', '2022.4.27-v2', 'location', hash('sha256', 'unassigned-crossing'), [$point], [], now()->utc()->format('Y-m-d H:i:s'), null, false))->handle();
        $this->assertSame(2, DB::table('vehicle_geofence_alerts')->where('gps_device_id', $device->id)->whereNull('ended_at')->count());
        $this->patchJson(route('admin.vehicle-geofences.status', $id), ['status' => 'archived'])->assertOk();
        $this->getJson(route('admin.vehicle-geofences.index', ['status' => 'active']))->assertJsonCount(0, 'data');
        $this->getJson(route('admin.vehicle-geofences.index', ['status' => 'archived']))->assertJsonCount(1, 'data');
        $this->patchJson(route('admin.vehicle-geofences.status', $id), ['status' => 'active'])->assertOk();
    }

    public function test_rule_changes_broadcast_to_open_fleet_maps_without_refresh(): void
    {
        [$vehicle, $device, $id, $payload] = $this->setupRule();
        Event::assertDispatchedTimes(VehicleGeofenceRulesChanged::class, 1);
        $this->putJson(route('admin.vehicle-geofences.update', $id), [...$payload, 'radius_meters' => 300])->assertOk();
        $this->patchJson(route('admin.vehicle-geofences.status', $id), ['active' => false])->assertOk();
        $this->deleteJson(route('admin.vehicle-geofences.destroy', $id))->assertOk();
        Event::assertDispatchedTimes(VehicleGeofenceRulesChanged::class, 4);
        $event = new VehicleGeofenceRulesChanged;
        $this->assertSame('private-admin.gps.alerts', $event->broadcastOn()[0]->name);
        $this->assertSame('vehicle.geofence.rules.changed', $event->broadcastAs());
        $this->assertSame(config('gps.broadcast_queue'), $event->broadcastQueue());
    }

    public function test_card_search_pagination_and_monitoring_permissions(): void
    {
        [$v,$d,$id,$payload] = $this->setupRule();
        for ($i = 0; $i < 13; $i++) {
            Vehicle::create(['registration_number' => 'OTHER-'.$i, 'name' => 'Delivery van', 'status' => 'active']);
        }
        $this->get(route('admin.vehicles.index'))->assertOk()->assertSee('Next');
        $this->get(route('admin.vehicles.index', ['search' => 'TEST-001']))->assertOk()->assertSee('TEST-001')->assertDontSee('OTHER-1');
        $this->getJson(route('admin.vehicle-geofences.vehicles', ['q' => 'TEST-001']))->assertOk()->assertJsonPath('total', 1);
        $this->actingAsPlainUser();
        $this->getJson(route('admin.vehicle-geofences.index'))->assertForbidden();
        $this->getJson(route('admin.vehicle-geofences.alerts'))->assertForbidden();
        $this->postJson(route('admin.vehicle-geofences.store'), $payload)->assertForbidden();
        $this->getJson(route('admin.vehicle-geofences.targets'))->assertForbidden();
        $this->putJson(route('admin.vehicle-geofences.update', $id), $payload)->assertForbidden();
        $this->deleteJson(route('admin.vehicle-geofences.destroy', $id))->assertForbidden();
    }

    public function test_actual_history_worker_evaluates_vehicle_fixes_and_alert_history_is_paginated(): void
    {
        [$v,$d,$id] = $this->setupRule();
        $this->travel(2)->seconds();
        $c = new Connection('127.0.0.1');
        $point = (new Location(26, 85.1, CarbonImmutable::now('UTC'), 80, gpsFix: true))->toArray();
        (new ProcessHardwarePacket($d->id, $d->identifier, $c->id, $c->connectedAt, $c->peer, 'pt06', '2022.4.27-v2', 'location', hash('sha256', 'vehicle-crossing'), [$point], [], now()->utc()->format('Y-m-d H:i:s'), $v->id, false))->handle();
        $this->assertDatabaseCount('gps_device_locations', 1);
        $this->assertDatabaseCount('vehicle_geofence_alerts', 2);
        $row = (array) DB::table('vehicle_geofence_alerts')->first();
        unset($row['id']);
        for ($i = 0; $i < 50; $i++) {
            DB::table('vehicle_geofence_alerts')->insert($row);
        }
        $this->getJson(route('admin.vehicle-geofences.alerts'))->assertJsonCount(50, 'data')->assertJsonPath('total', 52)->assertJsonPath('last_page', 2);
        $this->getJson(route('admin.vehicle-geofences.alerts', ['page' => 2]))->assertJsonCount(2, 'data');
    }
}
