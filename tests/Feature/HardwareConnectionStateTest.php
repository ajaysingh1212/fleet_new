<?php

namespace Tests\Feature;

use App\Jobs\DeliverHardwarePacket;
use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Services\Gps\HardwareConnectionState;
use App\Services\Gps\HardwareFleet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HardwareConnectionStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Connection tests require isolated SQLite.');
        }
        config(['gps.live_cache_store' => 'array', 'gps.live_first' => true]);
        Event::fake();
        Queue::fake();
    }

    private function device(): GpsDevice
    {
        return GpsDevice::create(['identifier' => '865167041931701', 'name' => 'Connection test',
            'status' => 'active', 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2', 'last_packet_at' => now()]);
    }

    public function test_close_does_not_imply_device_offline_and_queued_packets_cannot_reopen_the_socket(): void
    {
        $d = $this->device();
        $presence = app(HardwareConnectionState::class);
        $presence->touch($d->id, 'session-a');
        $this->assertTrue($d->is_online);
        $presence->close($d->id, 'session-a');
        $this->assertTrue($d->is_online);
        $packet = new ProcessHardwarePacket($d->id, $d->identifier, 'session-a', now()->utc()->format('Y-m-d H:i:s'),
            '127.0.0.1', 'pt06', '2022.4.27-v2', 'heartbeat', hash('sha256', 'late'), [], ['acc' => true],
            now()->utc()->format('Y-m-d H:i:s'), null);
        app()->call([new DeliverHardwarePacket($packet, 'database'), 'handle']);
        $state = app(HardwareFleet::class)->snapshot()[0];
        $this->assertFalse($state['connection_connected']);
        $this->assertTrue($state['online']);
        $this->travel(181)->seconds();
        $this->assertFalse($d->is_online);
        $this->assertSame('offline', app(HardwareFleet::class)->snapshot()[0]['state']);
        $presence->touch($d->id, 'session-b');
        $this->assertTrue($d->is_online);
        $this->assertTrue(app(HardwareFleet::class)->snapshot()[0]['online']);
    }

    public function test_old_session_close_does_not_disconnect_a_reconnected_device_and_idle_sessions_expire(): void
    {
        $d = $this->device();
        $presence = app(HardwareConnectionState::class);
        $presence->touch($d->id, 'old');
        $presence->touch($d->id, 'new');
        $presence->close($d->id, 'old');
        $this->assertTrue($presence->snapshot($d->id)['connection_connected']);
        $this->travel(181)->seconds();
        $this->assertFalse($presence->snapshot($d->id)['connection_connected']);
    }

    public function test_closed_sql_socket_stays_recently_seen_until_the_packet_timeout(): void
    {
        $d = $this->device();
        DB::table('gps_connection_sessions')->insert(['id' => 'existing-closed', 'gps_device_id' => $d->id,
            'protocol' => 'pt06', 'remote_ip' => '127.0.0.1', 'authenticated' => true,
            'connected_at' => now(), 'last_packet_at' => now(), 'disconnected_at' => now()]);
        $this->assertTrue($d->is_online);
        $this->assertTrue(app(HardwareFleet::class)->snapshot()[0]['online']);
        $this->assertCount(1, app(HardwareFleet::class)->page(['status' => 'online'])['devices']);
        $this->assertCount(0, app(HardwareFleet::class)->page(['status' => 'offline'])['devices']);
        $this->travel(181)->seconds();
        $this->assertFalse($d->is_online);
    }

    public function test_receiver_packets_keep_presence_online_even_while_history_and_live_jobs_are_waiting(): void
    {
        $device = $this->device();
        $device->update(['last_packet_at' => now()->subMinutes(10)]);
        app(HardwareConnectionState::class)->touch($device->id, 'receiving-now');
        $this->assertTrue($device->fresh()->is_online);
        $state = app(HardwareFleet::class)->snapshot()[0];
        $this->assertTrue($state['online']);
        $this->assertTrue($state['connection_connected']);
        $this->assertNotEmpty($state['connection_last_packet_at']);
        $this->assertTrue(CarbonImmutable::parse($state['last_packet_at'])->gte(now()->subSecond()));
        app(HardwareConnectionState::class)->close($device->id, 'receiving-now');
        $this->assertTrue($device->fresh()->is_online);
        $this->travel(181)->seconds();
        $this->assertFalse($device->fresh()->is_online);
    }

    public function test_socket_keepalive_does_not_forge_device_packets_or_extend_device_availability(): void
    {
        $device = $this->device();
        $presence = app(HardwareConnectionState::class);
        $presence->touch($device->id, 'quiet-open');
        $last = $presence->snapshot($device->id)['connection_last_packet_at'];
        $this->travel(181)->seconds();
        $presence->keepAlive($device->id, 'quiet-open');
        $this->assertTrue($presence->snapshot($device->id)['connection_connected']);
        $this->assertSame($last, $presence->snapshot($device->id)['connection_last_packet_at']);
        $this->assertFalse($device->fresh()->is_online);
    }
}
