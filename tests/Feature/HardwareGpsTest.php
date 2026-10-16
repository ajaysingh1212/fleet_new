<?php

namespace Tests\Feature;

use App\Events\HardwareLocationUpdated;
use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use App\Services\Gps\Gateway\Connection;
use App\Services\Gps\Gateway\DeviceAllowlist;
use App\Services\Gps\Gateway\Gateway;
use App\Services\Gps\Gateway\Location;
use App\Services\Gps\Gateway\ProtocolRegistry;
use App\Services\Gps\Gateway\Protocols\ConcoxX1;
use App\Services\Gps\Gateway\Protocols\Teltonika;
use App\Services\Gps\HardwareRegistrationService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Predis\Connection\Resource\Exception\StreamInitException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class HardwareGpsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($expected = getenv('GPS_ISOLATED_TEST_DATABASE')) {
            if (! preg_match('/^fleet_gps_test_[a-f0-9]{12}$/D', $expected)
                || $app['config']->get('database.default') !== 'mysql'
                || $app['db']->connection()->getDatabaseName() !== $expected) {
                throw new \RuntimeException('Unsafe MySQL test target: refusing migrations.');
            }
        } elseif ($app['config']->get('database.default') !== 'sqlite' || $app['db']->connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Hardware tests require in-memory SQLite or a verified isolated MySQL database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['gps.cache_store' => 'array', 'gps.queue_connection' => 'sync']);
        Event::fake([HardwareLocationUpdated::class]);
    }

    private function registration(): array
    {
        return ['name' => 'Fleet tracker', 'identifier' => '356307042441013', 'imei' => '356307042441013',
            'manufacturer' => 'Teltonika', 'model' => 'FMB920', 'protocol' => 'teltonika', 'protocol_version' => '8',
            'status' => 'active', 'configuration' => ['transport' => 'tcp', 'server_host' => 'gps.example.test',
                'server_port' => 9000, 'timezone' => 'UTC']];
    }

    private function device(): GpsDevice
    {
        return app(HardwareRegistrationService::class)->save($this->registration());
    }

    private function login(): string
    {
        return "\x00\x0f356307042441013";
    }

    /** Simulated Codec8/8E record with no model-specific IO. */
    private function avl(int $codec = 8, float $lat = 25.6, float $lon = 85.1, int $secondsAgo = 0): string
    {
        $data = chr($codec)."\x01".pack('J', (int) now()->subSeconds($secondsAgo)->timestamp * 1000)."\x00"
            .pack('NNnnCn', ((int) round($lon * 1e7)) & 0xFFFFFFFF, ((int) round($lat * 1e7)) & 0xFFFFFFFF, 40, 120, 9, 45)
            .str_repeat("\0", $codec === 8 ? 6 : 14)."\x01";

        return str_repeat("\0", 4).pack('N', strlen($data)).$data.pack('N', Teltonika::crc($data));
    }

    private function x1(int $type, string $payload): string
    {
        $data = chr(strlen($payload) + 5).chr($type).$payload."\x00\x01";

        return "\x78\x78".$data.pack('n', ConcoxX1::crc($data))."\r\n";
    }

    public function test_admin_registers_vehicle_and_device_and_pages_render(): void
    {
        $this->actingAsSuperAdmin();
        $data = $this->registration();
        $data['new_vehicle'] = ['registration_number' => 'BR01AA1234', 'name' => 'Delivery van', 'details' => ['driver_phone' => '12345']];
        $this->post(route('admin.gps-devices.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $device = GpsDevice::firstOrFail();
        $this->assertNotNull($device->vehicle_id);
        $this->assertDatabaseHas('gps_device_assignments', ['gps_device_id' => $device->id, 'vehicle_id' => $device->vehicle_id, 'removed_at' => null]);
        foreach (['index', 'create', 'diagnostics'] as $action) {
            $this->get(route('admin.gps-devices.'.$action))->assertOk();
        }
        $this->get(route('admin.gps-devices.edit', $device))->assertOk();
        $this->get(route('admin.gps-devices.show', $device))->assertOk();
        $this->get(route('admin.vehicles.index'))->assertOk();
        $this->get(route('admin.vehicles.edit', $device->vehicle_id))->assertOk();
    }

    public function test_duplicate_identifier_and_second_tracker_assignment_are_rejected(): void
    {
        $this->actingAsSuperAdmin();
        $device = $this->device();
        $this->post(route('admin.gps-devices.store'), $this->registration())->assertSessionHasErrors(['identifier', 'imei']);
        $vehicle = Vehicle::create(['registration_number' => 'BR01', 'name' => 'Van']);
        app(HardwareRegistrationService::class)->save([...$this->registration(), 'vehicle_id' => $vehicle->id], $device);
        $data = [...$this->registration(), 'identifier' => '123456789012345', 'imei' => '123456789012345', 'vehicle_id' => $vehicle->id];
        $this->post(route('admin.gps-devices.store'), $data)->assertSessionHasErrors('vehicle_id');
    }

    public function test_vehicle_registration_and_assignment_history(): void
    {
        $this->actingAsSuperAdmin();
        $this->post(route('admin.vehicles.store'), ['registration_number' => 'BR01', 'name' => 'Van', 'status' => 'active', 'details' => ['initial_odometer' => 120]])->assertSessionHasNoErrors()->assertRedirect();
        $device = $this->device();
        $service = app(HardwareRegistrationService::class);
        $service->save([...$this->registration(), 'vehicle_id' => Vehicle::first()->id], $device);
        $service->save([...$this->registration(), 'vehicle_id' => null], $device);
        $this->assertNotNull(DB::table('gps_device_assignments')->first()->removed_at);
    }

    public function test_non_admin_cannot_register_or_read_hardware_or_diagnostics(): void
    {
        $this->actingAsPlainUser();
        $this->post(route('admin.gps-devices.store'), $this->registration())->assertForbidden();
        $this->get(route('admin.gps-devices.index'))->assertForbidden();
        $this->get(route('admin.gps-devices.diagnostics'))->assertForbidden();
    }

    public function test_unsupported_version_udp_and_commands_cannot_be_activated(): void
    {
        $this->actingAsSuperAdmin();
        $data = $this->registration();
        $data['configuration']['transport'] = 'udp';
        $data['configuration']['commands'] = ['GUESS'];
        $data['protocol_version'] = '16';
        $this->post(route('admin.gps-devices.store'), $data)->assertSessionHasErrors(['configuration.transport', 'configuration.commands', 'protocol_version']);
    }

    public function test_official_codec8_fixture_checksum_and_no_fix(): void
    {
        $adapter = new Teltonika;
        $frame = hex2bin(trim(file_get_contents(base_path('tests/Fixtures/gps/teltonika-codec8-official.hex'))));
        $packet = $adapter->decode($frame, true);
        $this->assertSame(pack('N', 1), $packet->ack);
        $this->assertCount(1, $packet->locations);
        $this->assertFalse($packet->locations[0]->gpsFix);
        $this->assertSame(0xC7CF, Teltonika::crc(substr($frame, 8, -4)));
        $this->assertSame(0xBB3D, Teltonika::crc('123456789'));
        $this->assertSame(0x906E, ConcoxX1::crc('123456789'));
    }

    public function test_fragmented_login_and_multiple_avl_packets_receive_exact_acks(): void
    {
        $this->device();
        $gateway = app(Gateway::class);
        $c = new Connection('127.0.0.1');
        $this->assertSame('', $gateway->feed($c, substr($this->login(), 0, 1)));
        $this->assertSame('', $gateway->feed($c, substr($this->login(), 1, 4)));
        $this->assertSame("\x01", $gateway->feed($c, substr($this->login(), 5)));
        $avl = $this->avl();
        $this->assertSame('', $gateway->feed($c, substr($avl, 0, 7)));
        $this->assertSame(pack('NN', 1, 1), $gateway->feed($c, substr($avl, 7).$avl));
        $this->assertSame('', $c->buffer);
        $this->assertDatabaseCount('gps_device_locations', 1);
        $this->assertDatabaseCount('gps_connection_sessions', 1);
    }

    public function test_login_and_avl_coalesced_and_reconnect_work(): void
    {
        $this->device();
        foreach ([1, 2] as $_) {
            $c = new Connection('127.0.0.1');
            $this->assertSame("\x01".pack('N', 1), app(Gateway::class)->feed($c, $this->login().$this->avl()));
            app(Gateway::class)->disconnected($c);
        }
        $this->assertDatabaseCount('gps_connection_sessions', 2);
        $this->assertSame(2, DB::table('gps_connection_sessions')->whereNotNull('disconnected_at')->count());
        $this->assertDatabaseCount('gps_device_locations', 1);
    }

    public function test_unknown_identifier_is_rejected_and_metadata_contains_no_raw_payload(): void
    {
        config(['gps.pending_diagnostics' => true]);
        $c = new Connection('127.0.0.1');
        try {
            app(Gateway::class)->feed($c, $this->login());
            $this->fail('Unknown tracker accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('unauthorized_device', $e->getMessage());
        }
        app(Gateway::class)->diagnostic($c, $this->login().'secret-token', 'unauthorized_device');
        $this->assertDatabaseCount('gps_device_locations', 0);
        $row = DB::table('gps_packet_diagnostics')->first();
        $this->assertSame('356307042441013', $row->identifier);
        $this->assertStringNotContainsString('secret-token', json_encode($row));
    }

    public function test_corrupted_checksum_is_rejected_before_storage(): void
    {
        $this->device();
        $c = new Connection('127.0.0.1');
        app(Gateway::class)->feed($c, $this->login());
        $frame = $this->avl();
        $frame[20] = ~$frame[20];
        $this->expectExceptionMessage('invalid_checksum');
        app(Gateway::class)->feed($c, $frame);
    }

    public function test_codec8e_signed_coordinates_and_invalid_coordinate_rejection(): void
    {
        $packet = (new Teltonika)->decode($this->avl(0x8E, -25.6, -85.1), true);
        $this->assertEquals(-25.6, $packet->locations[0]->latitude);
        $this->assertEquals(-85.1, $packet->locations[0]->longitude);
        $this->assertSame('8E', $packet->version);
        $this->expectExceptionMessage('invalid_location');
        (new Teltonika)->decode($this->avl(8, 91), true);
    }

    public function test_old_locations_are_retained_without_replacing_latest_and_retries_dedupe(): void
    {
        $device = $this->device();
        $c = new Connection('127.0.0.1');
        app(Gateway::class)->feed($c, $this->login().$this->avl());
        $latest = $device->fresh()->latest_location_id;
        app(Gateway::class)->feed($c, $this->avl(8, 25.5, 85.0, 60));
        $this->assertSame($latest, $device->fresh()->latest_location_id);
        $this->assertDatabaseCount('gps_device_locations', 2);
    }

    public function test_blocking_device_revokes_existing_connection(): void
    {
        $device = $this->device();
        $c = new Connection('127.0.0.1');
        app(Gateway::class)->feed($c, $this->login());
        app(HardwareRegistrationService::class)->save([...$this->registration(), 'status' => 'blocked'], $device);
        $this->expectExceptionMessage('unauthorized_device');
        app(Gateway::class)->feed($c, $this->avl());
    }

    public function test_unknown_protocol_and_oversize_buffer_fail_closed(): void
    {
        try {
            app(ProtocolRegistry::class)->detect('arbitrary bytes');
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertSame('unknown_protocol', $e->getMessage());
        }
        $this->expectExceptionMessage('buffer_limit');
        app(Gateway::class)->feed(new Connection('127.0.0.1'), str_repeat('a', 65537));
    }

    public function test_x1_verified_structure_login_heartbeat_and_offline_expiry(): void
    {
        $device = app(HardwareRegistrationService::class)->save([...$this->registration(), 'protocol' => 'concox-x1', 'protocol_version' => '1.1', 'model' => 'X1']);
        $c = new Connection('127.0.0.1');
        $login = $this->x1(1, hex2bin('0356307042441013a0010000'));
        $this->assertSame($this->x1(1, ''), app(Gateway::class)->feed($c, $login));
        $this->assertSame($this->x1(0x13, ''), app(Gateway::class)->feed($c, $this->x1(0x13, hex2bin('0206040002'))));
        $this->assertTrue($device->fresh()->is_online);
        $this->assertDatabaseHas('gps_device_events', ['type' => 'heartbeat']);
        $this->travel(181)->seconds();
        $this->assertFalse($device->fresh()->is_online);
    }

    public function test_safe_broadcast_payload_and_private_channel_authorization(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->device();
        app(Gateway::class)->feed(new Connection('127.0.0.1'), $this->login().$this->avl());
        Event::assertDispatched(HardwareLocationUpdated::class);
        $device = GpsDevice::first();
        $event = new HardwareLocationUpdated(HardwareLocationUpdated::payload($device, $device->latestLocation));
        $this->assertSame('private-admin.gps.hardware', $event->broadcastOn()[0]->name);
        $this->assertArrayNotHasKey('configuration', $event->broadcastWith());
        $this->assertArrayNotHasKey('identifier', $event->broadcastWith());
        $this->assertArrayNotHasKey('telemetry', $event->broadcastWith());
        $this->assertTrue($admin->can('viewAny', GpsDevice::class));
        $plain = $this->actingAsPlainUser();
        $this->assertFalse($plain->can('viewAny', GpsDevice::class));
    }

    public function test_retention_keeps_latest_fix_and_installation_history(): void
    {
        $this->device();
        app(Gateway::class)->feed(new Connection('127.0.0.1'), $this->login().$this->avl());
        DB::table('gps_device_locations')->update(['received_at' => now()->subDays(100)]);
        $this->artisan('gps:purge')->assertSuccessful();
        $this->assertDatabaseCount('gps_device_locations', 1);
    }

    public function test_codec8e_io_counts_and_truncated_packet_are_rejected(): void
    {
        $frame = $this->avl(0x8E);
        $data = substr($frame, 8, -4);
        // Total IO count claims an element while every group is empty.
        $data[29] = "\x01";
        $bad = str_repeat("\0", 4).pack('N', strlen($data)).$data.pack('N', Teltonika::crc($data));
        $this->expectException(InvalidArgumentException::class);
        (new Teltonika)->decode($bad, true);
    }

    public function test_invalid_timestamps_and_heading_cannot_enter_dto(): void
    {
        foreach ([['latitude' => 10, 'time' => CarbonImmutable::now('UTC')->addHour(), 'heading' => 20],
            ['latitude' => 10, 'time' => CarbonImmutable::now('UTC'), 'heading' => 361]] as $data) {
            try {
                new Location($data['latitude'], 20, $data['time'], heading: $data['heading']);
                $this->fail('Invalid location accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('invalid_location', $e->getMessage());
            }
        }
    }

    public function test_oversize_announced_frame_is_rejected_without_waiting_for_body(): void
    {
        $this->device();
        $c = new Connection('127.0.0.1');
        app(Gateway::class)->feed($c, $this->login());
        $this->expectExceptionMessage('invalid_length');
        app(Gateway::class)->feed($c, str_repeat("\0", 4).pack('N', 10000000));
    }

    public function test_queue_failure_never_returns_successful_login_ack(): void
    {
        $this->device();
        $queue = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('size')->once()->andReturn(config('gps.max_queue'));
        Queue::shouldReceive('connection')->once()->andReturn($queue);
        $this->expectExceptionMessage('queue_full');
        app(Gateway::class)->feed(new Connection('127.0.0.1'), $this->login());
    }

    public function test_worker_reauthenticates_delayed_packets_after_revocation(): void
    {
        $device = $this->device();
        $device->updateQuietly(['status' => 'blocked']);
        $packet = (new Teltonika)->decode($this->avl(), true);
        $job = new ProcessHardwarePacket($device->id, $device->identifier, (string) Str::uuid(),
            now()->format('Y-m-d H:i:s'), '127.0.0.1', 'teltonika', '8', 'location', hash('sha256', 'delayed'),
            [$packet->locations[0]->toArray()], [], now()->format('Y-m-d H:i:s'), null);
        $job->handle();
        $this->assertDatabaseCount('gps_device_locations', 0);
    }

    public function test_x1_simulated_location_alarm_and_bad_checksum(): void
    {
        $payload = pack('N', 0x01000000).pack('C6', 26, 10, 1, 12, 0, 0)."\x09"
            .pack('NNCnnN', 25 * 1800000, 85 * 1800000, 40, 0x1400 | 120, 0, 1000)
            .str_repeat("\0", 13).pack('nnNCC', 1250, 420, 0x20000000, 3, 0);
        $frame = $this->x1(0x34, $payload);
        $packet = (new ConcoxX1)->decode($frame, true);
        $this->assertSame('', $packet->ack);
        $this->assertEquals(25, $packet->locations[0]->latitude);
        $this->assertEquals(85, $packet->locations[0]->longitude);
        $this->assertTrue($packet->events['sos']);
        $frame[10] = ~$frame[10];
        $this->expectExceptionMessage('invalid_checksum');
        (new ConcoxX1)->decode($frame, true);
    }

    public function test_actual_reverb_channel_callback_denies_ordinary_users(): void
    {
        $admin = $this->actingAsSuperAdmin();
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $request = Request::create('/broadcasting/auth', 'POST', [
            'socket_id' => '123.456', 'channel_name' => 'private-admin.gps.hardware',
        ]);
        $request->setUserResolver(fn () => $admin);
        $this->assertNotEmpty(Broadcast::driver('reverb')->auth($request));
        $plain = $this->actingAsPlainUser();
        $request->setUserResolver(fn () => $plain);
        $this->expectException(AccessDeniedHttpException::class);
        Broadcast::driver('reverb')->auth($request);
    }

    public function test_hardware_admin_changes_use_existing_audit_pipeline(): void
    {
        $this->actingAsSuperAdmin();
        $device = $this->device();
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => GpsDevice::class, 'auditable_id' => $device->id, 'event' => 'created']);
        app(HardwareRegistrationService::class)->save([...$this->registration(), 'status' => 'inactive'], $device);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => GpsDevice::class, 'auditable_id' => $device->id, 'event' => 'updated']);
    }

    private function useUnavailableGatewayRedis(): void
    {
        $store = \Mockery::mock(ArrayStore::class)->makePartial();
        $store->shouldReceive('put')->andThrow(new StreamInitException('tcp://private-redis:6379 secret-connection-detail'));
        $store->shouldReceive('forget')->andThrow(new StreamInitException('tcp://private-redis:6379 secret-connection-detail'));
        $store->shouldNotReceive('lock');
        Cache::extend('offline-gps-test', fn () => Cache::repository($store));
        config(['cache.stores.offline-gps-test' => ['driver' => 'offline-gps-test'], 'gps.cache_store' => 'offline-gps-test']);
    }

    public function test_new_inactive_device_and_vehicle_can_be_registered_without_redis(): void
    {
        $this->actingAsSuperAdmin();
        $this->useUnavailableGatewayRedis();
        $data = [...$this->registration(), 'status' => 'inactive',
            'new_vehicle' => ['registration_number' => 'BR-OFFLINE', 'name' => 'Offline van']];
        $response = $this->post(route('admin.gps-devices.store'), $data);
        $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('gps_gateway_warning');
        $device = GpsDevice::firstOrFail();
        $this->assertSame('inactive', $device->status);
        $this->assertNotNull($device->vehicle_id);
        $this->assertDatabaseCount('gps_device_assignments', 1);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('GPS gateway synchronization is pending')->assertDontSee('secret-connection-detail');
    }

    public function test_active_registration_without_redis_saves_and_reports_pending_sync(): void
    {
        $this->actingAsSuperAdmin();
        $this->useUnavailableGatewayRedis();
        $response = $this->post(route('admin.gps-devices.store'), $this->registration());
        $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('gps_gateway_warning');
        $this->assertDatabaseHas('gps_devices', ['identifier' => '356307042441013', 'status' => 'active']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Device saved.')
            ->assertDontSee('secret-connection-detail');
    }

    public function test_blocked_status_saves_during_outage_and_worker_rejects_stale_cached_authorization(): void
    {
        $this->actingAsSuperAdmin();
        $device = $this->device();
        $this->useUnavailableGatewayRedis();
        $this->put(route('admin.gps-devices.update', $device), [...$this->registration(), 'status' => 'blocked'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('gps_gateway_warning');
        $this->assertSame('blocked', $device->fresh()->status);
        // Simulate Redis recovery with an older active snapshot still present.
        config(['gps.cache_store' => 'array']);
        app(Gateway::class)->feed(new Connection('127.0.0.1'), $this->login().$this->avl());
        $this->assertDatabaseCount('gps_device_locations', 0);
        $this->assertDatabaseCount('gps_connection_sessions', 0);
        Event::assertNotDispatched(HardwareLocationUpdated::class);
    }

    public function test_registration_keeps_private_uploads_when_only_gateway_sync_is_unavailable(): void
    {
        $this->actingAsSuperAdmin();
        Storage::fake('local');
        $this->useUnavailableGatewayRedis();
        $this->post(route('admin.gps-devices.store'), [...$this->registration(),
            'documents' => [UploadedFile::fake()->create('install.pdf', 10, 'application/pdf')]])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('gps_gateway_warning');
        $this->assertCount(1, Storage::disk('local')->allFiles('gps-documents'));
        $this->assertCount(1, GpsDevice::first()->details['documents']);
    }

    public function test_redis_sync_failure_after_commit_reports_saved_device_without_duplicate_retry(): void
    {
        $this->actingAsSuperAdmin();
        // The lock succeeds; Redis fails only when publishing authorization.
        $store = \Mockery::mock(ArrayStore::class)->makePartial();
        $store->shouldReceive('put')->andThrow(new StreamInitException('private-sync-error'));
        Cache::extend('sync-failure-gps-test', fn () => Cache::repository($store));
        config(['cache.stores.sync-failure-gps-test' => ['driver' => 'sync-failure-gps-test'],
            'gps.cache_store' => 'sync-failure-gps-test']);
        $response = $this->post(route('admin.gps-devices.store'), $this->registration());
        $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('gps_gateway_warning');
        $this->assertDatabaseCount('gps_devices', 1);
        $this->assertSame('active', GpsDevice::first()->status);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Device saved.')
            ->assertDontSee('private-sync-error');
    }

    public function test_admin_registration_does_not_require_a_redis_registry_lock(): void
    {
        $this->actingAsSuperAdmin();
        $store = \Mockery::mock(ArrayStore::class)->makePartial();
        $store->shouldNotReceive('lock');
        Cache::extend('busy-gps-test', fn () => Cache::repository($store));
        config(['cache.stores.busy-gps-test' => ['driver' => 'busy-gps-test'], 'gps.cache_store' => 'busy-gps-test']);
        $this->post(route('admin.gps-devices.store'), $this->registration())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('gps_devices', 1);
    }

    public function test_sync_refreshes_authorization_from_current_database_state(): void
    {
        $device = $this->device();
        GpsDevice::whereKey($device->id)->update(['status' => 'blocked']);
        app(DeviceAllowlist::class)->syncCurrent($device->id);
        $snapshot = app(DeviceAllowlist::class)->find($device->identifier);
        $this->assertFalse($snapshot['active']);
    }

    public function test_identifier_edit_during_redis_outage_does_not_authorize_old_identifier_data(): void
    {
        $this->actingAsSuperAdmin();
        $device = $this->device();
        $this->useUnavailableGatewayRedis();
        $this->put(route('admin.gps-devices.update', $device), [...$this->registration(),
            'identifier' => '123456789012345', 'imei' => '123456789012345'])->assertSessionHasNoErrors()->assertRedirect();
        config(['gps.cache_store' => 'array']);
        app(Gateway::class)->feed(new Connection('127.0.0.1'), $this->login().$this->avl());
        $this->assertDatabaseCount('gps_device_locations', 0);
        app(DeviceAllowlist::class)->syncCurrent($device->id, '356307042441013');
        $this->assertNull(app(DeviceAllowlist::class)->find('356307042441013'));
        $this->assertNotNull(app(DeviceAllowlist::class)->find('123456789012345'));
    }

    public function test_device_and_vehicle_validation_messages_are_rendered_once(): void
    {
        $this->actingAsSuperAdmin();
        $data = $this->registration();
        unset($data['name']);
        $this->from(route('admin.gps-devices.create'))->post(route('admin.gps-devices.store'), $data)->assertSessionHasErrors('name');
        $response = $this->get(route('admin.gps-devices.create'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'The name field is required.'));
        $this->from(route('admin.vehicles.create'))->post(route('admin.vehicles.store'),
            ['registration_number' => 'BR-VALIDATION', 'status' => 'active'])->assertSessionHasErrors('name');
        $response = $this->get(route('admin.vehicles.create'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'The name field is required.'));
    }
}
