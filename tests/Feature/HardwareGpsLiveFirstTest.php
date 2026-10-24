<?php

namespace Tests\Feature;

use App\Events\HardwarePacketLive;
use App\Jobs\DeliverHardwarePacket;
use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Services\Gps\Gateway\Connection;
use App\Services\Gps\Gateway\Location;
use App\Services\Gps\Gateway\Packet;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\LocalHardwareRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HardwareGpsLiveFirstTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite' || $app['db']->connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Live-first tests require isolated in-memory SQLite.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['gps.live_first' => true, 'gps.live_cache_store' => 'array', 'gps.live_connection' => 'database', 'gps.local_broadcast' => true]);
        Event::fake([HardwarePacketLive::class]);
        Queue::fake();
    }

    private function device(): GpsDevice
    {
        return GpsDevice::create(['name' => 'Live test tracker', 'identifier' => '865167041931701',
            'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2', 'status' => 'active']);
    }

    private function packet(GpsDevice $device, string $hash, float $latitude = 25.6, ?CarbonImmutable $time = null): ProcessHardwarePacket
    {
        $c = new Connection('127.0.0.1');
        $location = new Location($latitude, 85.1, $time ?? CarbonImmutable::now('UTC'), 0, 0, gpsFix: true, telemetry: ['acc' => false]);
        return new ProcessHardwarePacket($device->id, $device->identifier, $c->id, $c->connectedAt, $c->peer,
            'pt06', '2022.4.27-v2', 'location', hash('sha256', $hash), [$location->toArray()], [], now()->utc()->format('Y-m-d H:i:s'), null);
    }

    public function test_receiver_queues_durable_input_without_writing_gps_history(): void
    {
        $device = $this->device(); $c = new Connection('127.0.0.1');
        app(LocalHardwareRecorder::class)->record(new Packet('heartbeat', null, '2022.4.27-v2', events: ['acc' => false]),
            'realistic-heartbeat-fixture', $device->identifier, 'pt06', $c->id, $c->connectedAt, $c->peer);
        Queue::assertPushed(DeliverHardwarePacket::class, fn ($job, $queue) => $queue === 'gps-live');
        $this->assertDatabaseCount('gps_device_events', 0);
        $this->assertDatabaseCount('gps_connection_sessions', 0);
        Event::assertNotDispatched(HardwarePacketLive::class);
    }

    public function test_live_payload_is_available_before_history_and_persistence_is_independent(): void
    {
        $device = $this->device();
        $job = new DeliverHardwarePacket($this->packet($device, 'first'), 'database');
        app()->call([$job, 'handle']);
        Event::assertDispatched(HardwarePacketLive::class, fn ($event) => $event->payload['latitude'] === 25.6
            && $event->payload['state'] === 'parked' && $event->payload['live_revision'] === 1);
        $this->assertDatabaseCount('gps_device_locations', 0);
        $this->assertNull($device->fresh()->last_packet_at);
        Queue::assertPushed(ProcessHardwarePacket::class, fn ($packet, $queue) => ! $packet->broadcast && $queue === 'gps-persist');
        $snapshot = app(HardwareFleet::class)->snapshot()[0];
        $this->assertSame(25.6, $snapshot['latitude']);
        $this->assertSame('parked', $snapshot['state']);
        $this->assertSame(1, $snapshot['live_revision']);
        // No browser/render acknowledgement is needed to store the packet.
        $job->packet->handle();
        $this->assertDatabaseCount('gps_device_locations', 1);
        $this->assertNotNull($device->fresh()->last_packet_at);
    }

    public function test_out_of_order_live_packets_do_not_rewind_and_warm_path_does_not_read_history(): void
    {
        $device = $this->device();
        app()->call([new DeliverHardwarePacket($this->packet($device, 'new', 26), 'database'), 'handle']);
        DB::enableQueryLog(); DB::flushQueryLog();
        app()->call([new DeliverHardwarePacket($this->packet($device, 'old', 24, CarbonImmutable::now('UTC')->subMinute()), 'database'), 'handle']);
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('gps_device_locations', $query['query']);
            $this->assertStringNotContainsString('gps_device_events', $query['query']);
        }
        DB::disableQueryLog();
        $this->assertSame(26.0, app(HardwareFleet::class)->snapshot()[0]['latitude']);
        $this->assertDatabaseCount('gps_device_locations', 0);
    }

    public function test_revocation_before_delivery_blocks_live_publication_and_storage(): void
    {
        $device = $this->device(); $job = new DeliverHardwarePacket($this->packet($device, 'revoked'), 'database');
        $device->forceFill(['status' => 'blocked'])->saveQuietly();
        app()->call([$job, 'handle']);
        Event::assertNotDispatched(HardwarePacketLive::class);
        Queue::assertNotPushed(ProcessHardwarePacket::class);
        $this->assertDatabaseCount('gps_device_locations', 0);
    }

    public function test_newer_off_heartbeat_parks_without_waiting_for_a_new_coordinate_or_history(): void
    {
        $device = $this->device();
        $moving = $this->packet($device, 'moving');
        $moving->locations[0]['speed'] = 25;
        $moving->locations[0]['telemetry']['acc'] = true;
        app()->call([new DeliverHardwarePacket($moving, 'database'), 'handle']);
        $this->travel(2)->seconds();
        $heartbeat = $this->packet($device, 'off');
        $heartbeat->type = 'heartbeat';
        $heartbeat->locations = [];
        $heartbeat->events = ['acc' => false, 'gps_positioned_reported' => true];
        app()->call([new DeliverHardwarePacket($heartbeat, 'database'), 'handle']);
        $state = app(HardwareFleet::class)->snapshot()[0];
        $this->assertSame('parked', $state['state']);
        $this->assertSame(25, $state['speed']); // last measured speed retained as history
        $this->assertFalse($state['acc']);
        $this->assertDatabaseCount('gps_device_locations', 0);
    }

    public function test_broadcast_failure_does_not_block_history_queue(): void
    {
        // Use an explicit throwing dispatcher to simulate a failed live transport.
        $dispatcher = \Mockery::mock(\Illuminate\Contracts\Events\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->with(\Mockery::type(HardwarePacketLive::class))->andThrow(new \RuntimeException('transport down'));
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->once()->with('gps_live_broadcast_failed', ['device_id' => 1]);
        $device = $this->device();
        $job = new DeliverHardwarePacket($this->packet($device, 'offline-browser'), 'database');
        $this->app->instance('events', $dispatcher);
        app()->call([$job, 'handle']);
        Queue::assertPushed(ProcessHardwarePacket::class);
        $this->assertDatabaseCount('gps_device_locations', 0);
    }

    public function test_live_cache_failure_still_queues_history(): void
    {
        $device = $this->device();
        config(['gps.live_cache_store' => 'unavailable-live-cache']);
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->once();
        app()->call([new DeliverHardwarePacket($this->packet($device, 'cache-failure'), 'database'), 'handle']);
        Queue::assertPushed(ProcessHardwarePacket::class);
        Event::assertNotDispatched(HardwarePacketLive::class);
    }

    public function test_history_enqueue_failure_propagates_for_retry_after_live_publish(): void
    {
        $device = $this->device();
        $queue = \Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->once()->andReturnUsing(function () {
            Event::assertDispatched(HardwarePacketLive::class);
            $this->assertDatabaseCount('gps_device_locations', 0);
            throw new \RuntimeException('history_queue_unavailable');
        });
        Queue::shouldReceive('connection')->with('database')->once()->andReturn($queue);
        $this->expectExceptionMessage('history_queue_unavailable');
        app()->call([new DeliverHardwarePacket($this->packet($device, 'retry-history'), 'database'), 'handle']);
    }
}
