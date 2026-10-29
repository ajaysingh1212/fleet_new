<?php

namespace Tests\Unit;

use App\Services\Gps\LiveDiagnosticStream;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LiveDiagnosticStreamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['gps.live_cache_store' => 'array', 'gps.live_diagnostic_buffer' => 100]);
        Cache::store('array')->flush();
    }

    public function test_keeps_transient_bounded_events_and_redacts_sensitive_fields(): void
    {
        $stream = app(LiveDiagnosticStream::class);
        $stream->append('864540081540460', 'PACKET_DECODED', ['raw_hex' => '7878', 'phone' => 'secret', 'decoded' => ['acc' => true]]);
        $events = $stream->read('864540081540460');

        $this->assertCount(1, $events);
        $this->assertSame('PACKET_DECODED', $events[0]['event']);
        $this->assertSame('7878', $events[0]['fields']['raw_hex']);
        $this->assertArrayNotHasKey('phone', $events[0]['fields']);
        $this->assertSame([], $stream->read('864540081540460', $events[0]['id']));
    }
}
