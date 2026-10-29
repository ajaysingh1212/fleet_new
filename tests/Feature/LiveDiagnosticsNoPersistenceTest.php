<?php

namespace Tests\Feature;

use App\Models\GpsDevice;
use App\Services\Gps\LiveDiagnosticStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveDiagnosticsNoPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gps.live_cache_store' => 'array', 'gps.live_diagnostic_buffer' => 100]);
    }

    public function test_transient_packet_viewer_never_creates_a_diagnostic_or_telemetry_record(): void
    {
        $device = GpsDevice::create(['name' => 'Transient diagnostic test', 'identifier' => '864540081540460',
            'status' => 'active', 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2']);

        app(LiveDiagnosticStream::class)->append($device->identifier, 'ACK_SENT', [
            'raw_hex' => '787805130001D9DC0D0A', 'ack_hex' => '787805130001D9DC0D0A', 'bytes' => 10,
        ]);

        $this->assertCount(1, app(LiveDiagnosticStream::class)->read($device->identifier));
        $this->assertDatabaseCount('gps_packet_diagnostics', 0);
        $this->assertDatabaseCount('gps_device_events', 0);
        $this->assertDatabaseCount('gps_device_locations', 0);
        $this->assertDatabaseCount('gps_device_commands', 0);
    }
}
