<?php

namespace Tests\Unit;

use App\Services\Gps\HardwareAvailability;
use Tests\TestCase;

class HardwareAvailabilityTest extends TestCase
{
    public function test_defaults_to_the_configured_last_seen_threshold(): void
    {
        config(['gps.idle_seconds' => 180, 'gps.offline_grace_seconds' => 30]);

        $this->assertSame(180, app(HardwareAvailability::class)->seconds([]));
    }

    public function test_device_reporting_cadence_extends_only_the_offline_grace_window(): void
    {
        config(['gps.idle_seconds' => 180, 'gps.offline_grace_seconds' => 30]);

        $this->assertSame(390, app(HardwareAvailability::class)->seconds(['heartbeat_interval' => 120]));
        $this->assertSame(930, app(HardwareAvailability::class)->seconds(['reporting_interval' => '300']));
        $this->assertSame(180, app(HardwareAvailability::class)->seconds(['heartbeat_interval' => 'unknown']));
    }
}
