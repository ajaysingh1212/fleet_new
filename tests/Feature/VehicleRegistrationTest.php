<?php

namespace Tests\Feature;

use App\Models\GpsDevice;
use App\Models\GpsDeviceModel;
use App\Models\Vehicle;
use App\Services\Gps\Gateway\Connection;
use App\Services\Gps\Gateway\Location;
use App\Services\Gps\Gateway\Packet;
use App\Services\Gps\Gateway\Protocols\Pt06;
use App\Services\Gps\HardwareRegistrationService;
use App\Services\Gps\LocalHardwareRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class VehicleRegistrationTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Registration tests require isolated in-memory SQLite.');
        }
        config(['gps.cache_store' => 'array', 'gps.live_cache_store' => 'array', 'gps.queue_connection' => 'sync',
            'gps.live_first' => false, 'gps.local_broadcast' => false]);
        Storage::fake('local');
    }

    private function model(): GpsDeviceModel
    {
        return GpsDeviceModel::create(['name' => 'PT06 test model', 'manufacturer' => 'Test',
            'protocol' => 'pt06', 'protocol_version' => Pt06::VERSION, 'network_generation' => '4G', 'active' => true]);
    }

    private function payload(GpsDeviceModel $model): array
    {
        return ['registration_number' => 'BR01TEST', 'name' => 'Delivery truck', 'status' => 'active',
            'details' => ['icon' => 'truck', 'initial_odometer' => 125000, 'odometer_source' => 'manual'],
            'device_enabled' => 1, 'device' => ['device_model_id' => $model->id, 'identifier' => '990000000000001',
                'status' => 'active', 'sim_number' => '+919876543210', 'network_provider' => 'Jio', 'sim_generation' => '4G',
                'serial_number' => 'SERIAL-1', 'vts' => 'VTS-1']];
    }

    public function test_combined_registration_assigns_device_and_first_fix_reaches_owner_map_with_selected_icon(): void
    {
        $owner = $this->actingAsSuperAdmin();
        $data = [...$this->payload($this->model()), 'owner_user_id' => $owner->id];
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $vehicle = Vehicle::firstOrFail();
        $device = $vehicle->gpsDevice;
        $this->assertSame($data['device']['identifier'], $device->imei);
        $this->assertSame('4G', $device->details['network_generation']);
        $this->assertSame('Jio', $device->details['network_provider']);
        $this->assertSame('VTS-1', $device->details['vts']);
        $this->assertDatabaseHas('gps_device_assignments', ['vehicle_id' => $vehicle->id, 'gps_device_id' => $device->id]);
        $c = new Connection('127.0.0.1');
        app(LocalHardwareRecorder::class)->record(new Packet('location', null, Pt06::VERSION,
            locations: [new Location(25.6, 85.1, CarbonImmutable::now('UTC'), 21, 90, gpsFix: true)]),
            'first-realistic-fix', $device->identifier, 'pt06', $c->id, $c->connectedAt, $c->peer);
        $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonFragment(['vehicle_icon' => 'fa-truck', 'latitude' => 25.6]);
        $this->get(route('admin.vehicles.create'))->assertOk()->assertSee('One registration. Ready to track.');
        $this->get(route('admin.vehicles.edit', $vehicle))->assertOk()->assertSee('SERIAL-1');
        $this->get(route('admin.vehicles.show', $vehicle))->assertOk()->assertSee('VTS-1');
    }

    public function test_update_preserves_assignment_and_private_attachments_and_delete_retains_history(): void
    {
        $this->actingAsSuperAdmin();
        $data = $this->payload($this->model());
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6ioAAAAASUVORK5CYII=');
        $data['vehicle_images'] = [UploadedFile::fake()->createWithContent('vehicle.png', $png)];
        $data['documents'] = [UploadedFile::fake()->createWithContent('registration.pdf', '%PDF-1.4 test')];
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasNoErrors();
        $vehicle = Vehicle::firstOrFail();
        $device = $vehicle->gpsDevice;
        $this->assertCount(2, $vehicle->details['documents']);
        Storage::disk('local')->assertExists(array_column($vehicle->details['documents'], 'path'));
        $imageIndex = collect($vehicle->details['documents'])->search(fn ($d) => $d['kind'] === 'vehicle');
        $this->get(route('admin.vehicles.document', [$vehicle, $imageIndex, 'preview' => 1]))->assertOk()->assertHeader('Content-Type', 'image/png');
        unset($data['vehicle_images'], $data['documents']);
        $data['device']['device_model_id'] = null; // Keep installed profile.
        $data['name'] = 'Updated truck';
        $data['details']['icon'] = 'bus';
        $this->put(route('admin.vehicles.update', $vehicle), $data)->assertSessionHasNoErrors();
        $this->assertSame($device->id, $vehicle->fresh()->gpsDevice->id);
        $this->assertSame($device->gps_device_model_id, $vehicle->fresh()->gpsDevice->gps_device_model_id);
        $this->assertDatabaseCount('gps_device_assignments', 1);
        $this->assertCount(2, $vehicle->fresh()->details['documents']);
        $c = new Connection('127.0.0.1');
        app(LocalHardwareRecorder::class)->record(new Packet('location', null, Pt06::VERSION,
            locations: [new Location(25.6, 85.1, CarbonImmutable::now('UTC'), 21, 90, gpsFix: true)]),
            'history-before-deletion', $device->identifier, 'pt06', $c->id, $c->connectedAt, $c->peer);
        $this->assertDatabaseCount('gps_device_locations', 1);
        $this->delete(route('admin.vehicles.destroy', $vehicle))->assertRedirect(route('admin.vehicles.index'));
        $this->assertSoftDeleted($vehicle);
        $this->assertSame('inactive', $device->fresh()->status);
        $this->assertDatabaseCount('gps_device_assignments', 1);
        $this->assertDatabaseCount('gps_device_locations', 1);
    }

    public function test_invalid_duplicate_device_or_unsupported_activation_cannot_create_partial_vehicle(): void
    {
        $this->actingAsSuperAdmin();
        $model = $this->model();
        $data = $this->payload($model);
        $data['device']['identifier'] = 'bad-imei';
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasErrors('device.identifier');
        $this->assertDatabaseCount('vehicles', 0);
        $data = $this->payload($model);
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasNoErrors();
        $data['registration_number'] = 'BR02TEST';
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasErrors('device.identifier');
        $this->assertDatabaseCount('vehicles', 1);
        $model->update(['protocol' => 'gt05', 'protocol_version' => null]);
        $data['device']['identifier'] = '990000000000002';
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasErrors('device.device_model_id');
        $this->assertDatabaseCount('vehicles', 1);
        $data['device']['status'] = 'inactive';
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('vehicles', 2);
    }

    public function test_catalog_crud_is_authorized_and_installed_model_cannot_be_deleted_or_reprotocolled(): void
    {
        $this->actingAsSuperAdmin();
        $data = ['name' => 'Unused model', 'protocol' => 'gt06', 'protocol_version' => '1.8.1', 'active' => 1, 'network_generation' => '2G'];
        $this->post(route('admin.device-models.store'), $data)->assertSessionHasNoErrors();
        $unused = GpsDeviceModel::firstOrFail();
        $this->get(route('admin.device-models.index'))->assertOk()->assertSee('Unused model');
        $this->get(route('admin.device-models.edit', $unused))->assertOk();
        $this->put(route('admin.device-models.update', $unused), [...$data, 'name' => 'Renamed model'])->assertSessionHasNoErrors();
        $this->delete(route('admin.device-models.destroy', $unused))->assertRedirect();
        $this->assertDatabaseCount('gps_device_models', 0);
        $model = $this->model();
        $this->post(route('admin.vehicles.store'), $this->payload($model))->assertSessionHasNoErrors();
        $this->delete(route('admin.device-models.destroy', $model))->assertSessionHasErrors('model');
        $this->put(route('admin.device-models.update', $model), [...$model->only(['name', 'protocol', 'protocol_version', 'active']), 'protocol' => 'gt06'])->assertSessionHasErrors('protocol');
        $vehicle = Vehicle::firstOrFail();
        $this->actingAsPlainUser();
        $this->get(route('admin.vehicles.edit', $vehicle))->assertForbidden();
        $this->get(route('admin.vehicles.document', [$vehicle, 0, 'preview' => 1]))->assertForbidden();
        $this->post(route('admin.vehicles.store'), [])->assertForbidden();
        $this->delete(route('admin.vehicles.destroy', $vehicle))->assertForbidden();
        $this->get(route('admin.device-models.index'))->assertForbidden();
        $this->delete(route('admin.device-models.destroy', $model))->assertForbidden();
    }

    public function test_failed_device_storage_rolls_back_vehicle_and_removes_new_uploads(): void
    {
        $this->actingAsSuperAdmin();
        $data = $this->payload($this->model());
        $data['documents'] = [UploadedFile::fake()->createWithContent('registration.pdf', '%PDF-1.4 fixture')];
        $this->mock(HardwareRegistrationService::class)->shouldReceive('save')
            ->once()->andThrow(new \RuntimeException('Simulated storage failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('admin.vehicles.store'), $data);
            $this->fail('Expected storage failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated storage failure', $error->getMessage());
        }
        $this->assertDatabaseCount('vehicles', 0);
        $this->assertDatabaseCount('gps_devices', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_malformed_model_and_forged_attachment_paths_are_handled_without_partial_records(): void
    {
        $this->actingAsSuperAdmin();
        $data = $this->payload($this->model());
        $data['device']['device_model_id'] = ['invalid'];
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasErrors('device.device_model_id');
        $this->assertDatabaseCount('vehicles', 0);
        $data = $this->payload(GpsDeviceModel::first());
        $data['details']['documents'] = [['path' => '.env', 'name' => 'stolen']];
        $data['details']['arbitrary'] = 'not allowed';
        $this->post(route('admin.vehicles.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame([], Vehicle::first()->details['documents']);
        $this->assertArrayNotHasKey('arbitrary', Vehicle::first()->details);
    }

    public function test_existing_unassigned_tracker_is_listed_and_can_receive_a_vehicle_number_without_duplicate_or_lost_fix(): void
    {
        $owner = $this->actingAsSuperAdmin();
        $model = $this->model();
        $device = GpsDevice::create(['name' => 'Pending GPS', 'identifier' => '990000000000001',
            'imei' => '990000000000001', 'status' => 'active', 'model' => $model->name,
            'gps_device_model_id' => $model->id, 'protocol' => 'pt06', 'protocol_version' => Pt06::VERSION]);
        $c = new Connection('127.0.0.1');
        app(LocalHardwareRecorder::class)->record(new Packet('location', null, Pt06::VERSION,
            locations: [new Location(25.6, 85.1, CarbonImmutable::now('UTC'), 21, 90, gpsFix: true)]),
            'unassigned-last-known-fix', $device->identifier, 'pt06', $c->id, $c->connectedAt, $c->peer);
        $lastFix = $device->fresh()->latest_location_id;
        $this->get(route('admin.vehicles.index', ['search' => $device->identifier]))->assertOk()
            ->assertSee('Awaiting vehicle number')->assertSee($device->identifier)->assertSee('Add vehicle number');
        $this->get(route('admin.vehicles.from-device', $device))->assertOk()->assertSee('Add vehicle number for IMEI '.$device->identifier);
        $data = [...$this->payload($model), 'owner_user_id' => $owner->id];
        $this->post(route('admin.vehicles.attach-device', $device), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('gps_devices', 1);
        $this->assertDatabaseCount('gps_device_assignments', 1);
        $this->assertDatabaseCount('gps_device_locations', 1);
        $this->assertSame($lastFix, $device->fresh()->latest_location_id);
        $this->assertSame(Vehicle::first()->id, $device->fresh()->vehicle_id);
        $this->getJson(route('dashboard.fleet'))->assertOk()->assertJsonFragment(['registration' => 'BR01TEST', 'latitude' => 25.6]);
        $this->get(route('admin.vehicles.from-device', $device))->assertStatus(409);
        $data['registration_number'] = 'DO-NOT-CREATE';
        $this->post(route('admin.vehicles.attach-device', $device), $data)->assertSessionHasErrors('device');
        $this->assertDatabaseCount('vehicles', 1);
        $this->actingAsPlainUser();
        $this->get(route('admin.vehicles.from-device', $device))->assertForbidden();
        $this->post(route('admin.vehicles.attach-device', $device), $data)->assertForbidden();
    }

    public function test_completing_registration_without_device_edits_keeps_legacy_tracker_profile(): void
    {
        $this->actingAsSuperAdmin();
        $device = GpsDevice::create(['name' => 'Legacy orphan', 'identifier' => '990000000000009', 'status' => 'active',
            'protocol' => 'pt06', 'protocol_version' => Pt06::VERSION, 'model' => 'Existing legacy profile',
            'details' => ['serial_number' => 'KEEP-ME'], 'configuration' => ['apn' => 'original-apn']]);
        $this->post(route('admin.vehicles.attach-device', $device), ['registration_number' => 'BR-LEGACY',
            'name' => 'Legacy vehicle', 'status' => 'active', 'device_enabled' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('gps_devices', 1);
        $this->assertSame('Existing legacy profile', $device->fresh()->model);
        $this->assertSame('KEEP-ME', $device->fresh()->details['serial_number']);
        $this->assertSame('original-apn', $device->fresh()->configuration['apn']);
        $this->assertSame(Vehicle::first()->id, $device->fresh()->vehicle_id);
    }
}
