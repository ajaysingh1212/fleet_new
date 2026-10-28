<?php

namespace Tests\Unit;

use App\Services\Gps\HardwareTelemetry;
use PHPUnit\Framework\TestCase;

class HardwareTelemetryTest extends TestCase
{
    public function test_diagnostic_redaction_keeps_identity_and_timing_but_removes_secrets_and_raw_payloads(): void
    {
        $safe = HardwareTelemetry::redact(['imei' => '990000000000001', 'type' => 'heartbeat',
            'raw_hex' => 'sensitive', 'payload_hex' => 'sensitive', 'response_text' => 'sensitive',
            'nested' => ['token' => 'sensitive', 'iccid_encoded' => 'sensitive', 'gps_fix' => true]]);
        $this->assertSame(['imei' => '990000000000001', 'type' => 'heartbeat', 'nested' => ['gps_fix' => true]], $safe);
    }
}
