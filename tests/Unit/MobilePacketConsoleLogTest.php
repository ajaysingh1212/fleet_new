<?php

namespace Tests\Unit;

use App\Console\Commands\ListenMobileGps;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class MobilePacketConsoleLogTest extends TestCase
{
    public function test_terminal_log_distinguishes_saved_cached_and_duplicate_packets_without_leaking_tokens(): void
    {
        $buffer = new BufferedOutput;
        $command = new class extends ListenMobileGps {
            public function sample(array $payload, array $reply): void
            {
                $this->logAcceptedPacket('127.0.0.1', 2, $payload, $reply);
            }
        };
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));
        $payload = ['imei' => '123456789012345', 'license_number' => "LICENSE\nTEST",
            'packet_id' => 'example', 'latitude' => 28.6139, 'longitude' => 77.209,
            'token' => 'secret-credential', 'device_key' => 'secret-device-key', 'unexpected' => 'private-value'];
        foreach ([
            ['saved' => true, 'live_cached' => true],
            ['saved' => false, 'live_cached' => true, 'reason' => 'throttled'],
            ['saved' => true, 'duplicate' => true],
        ] as $reply) {
            $command->sample($payload, $reply);
        }
        $text = $buffer->fetch();
        $this->assertStringNotContainsString('secret-credential', $text);
        $this->assertStringNotContainsString('secret-device-key', $text);
        $this->assertStringNotContainsString('private-value', $text);
        $lines = explode(PHP_EOL, trim($text));
        $this->assertCount(3, $lines);
        $logs = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
        $this->assertSame(['SAVED', 'LIVE_CACHED', 'DUPLICATE'], array_column($logs, 'event'));
        $this->assertSame('02', $logs[0]['code']);
        $this->assertSame(28.6139, $logs[0]['latitude']);
    }
}
