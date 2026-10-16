<?php

namespace Tests\Feature;

use App\Services\Gps\Gateway\Gt06DiagnosticFrames;
use App\Services\Gps\Gateway\ProtocolRegistry;
use App\Services\Gps\Gateway\Protocols\ConcoxX1;
use App\Services\Gps\Gateway\Protocols\Gt06;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Gt06DiagnosticTest extends TestCase
{
    private function fixture(string $name): string
    {
        return hex2bin(trim(file_get_contents(base_path('tests/Fixtures/gps/'.$name.'.hex'))));
    }

    private function simulatedLocation(): string
    {
        // Published GPS bytes with independently recomputed CRC. The manual's
        // printed 8081 does not validate for its printed packet. Never loosen CRC.
        $frame = $this->fixture('gt06-location-official');

        return substr($frame, 0, -4).pack('n', ConcoxX1::crc(substr($frame, 2, -4)))."\r\n";
    }

    public function test_user_capture_is_valid_gt06_login_with_exact_identifier_and_ack(): void
    {
        $packet = (new Gt06)->decode($this->fixture('bt50-user-login'), false);
        $this->assertSame('864540081540460', $packet->identifier);
        $this->assertSame('787805010001D9DC0D0A', strtoupper(bin2hex($packet->ack)));
    }

    public function test_official_vendor_login_and_location_fixtures(): void
    {
        $login = (new Gt06)->decode($this->fixture('gt06-login-official'), false);
        $this->assertSame('123456789012345', $login->identifier);
        $location = (new Gt06)->decode($this->simulatedLocation(), true)->locations[0];
        $this->assertTrue($location->gpsFix);
        $this->assertEqualsWithDelta(23.1116683, $location->latitude, 0.000001);
        $this->assertEqualsWithDelta(114.409285, $location->longitude, 0.000001);
        $this->assertSame(12, $location->satellites);
    }

    public function test_registry_disambiguates_x1_and_classic_and_handles_fragments(): void
    {
        $registry = app(ProtocolRegistry::class);
        $this->assertNull($registry->detect("\x78\x78"));
        $this->assertSame('gt06', $registry->detect($this->fixture('bt50-user-login'))->name());
        $this->assertSame('concox-x1', $registry->detect(hex2bin('78781101'))->name());
        $protocol = new Gt06;
        $buffer = substr($this->fixture('bt50-user-login'), 0, 7);
        $this->assertNull($protocol->extract($buffer, false));
        $buffer .= substr($this->fixture('bt50-user-login'), 7).$this->fixture('gt06-location-official');
        $this->assertSame($this->fixture('bt50-user-login'), $protocol->extract($buffer, false));
        $this->assertSame($this->fixture('gt06-location-official'), $protocol->extract($buffer, true));
        $this->assertSame('', $buffer);
    }

    public function test_corrupted_capture_never_receives_an_ack(): void
    {
        $frame = $this->fixture('bt50-user-login');
        $frame[8] = ~$frame[8];
        $this->expectException(InvalidArgumentException::class);
        (new Gt06)->decode($frame, false);
    }

    public function test_published_location_with_inconsistent_crc_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Gt06)->decode($this->fixture('gt06-location-official'), true);
    }

    public function test_extended_diagnostic_frame_does_not_swallow_following_location(): void
    {
        $body = "\x94".'simulated-status'."\x00\x02";
        $data = pack('n', strlen($body) + 2).$body;
        $extended = "\x79\x79".$data.pack('n', ConcoxX1::crc($data))."\r\n";
        $framer = new Gt06DiagnosticFrames;
        $buffer = substr($extended, 0, 5);
        $this->assertNull($framer->extract($buffer));
        $buffer .= substr($extended, 5).$this->simulatedLocation();
        $this->assertSame($extended, $framer->extract($buffer));
        $this->assertSame('94', $framer->type($extended));
        $this->assertSame($this->simulatedLocation(), $framer->extract($buffer));
        $this->assertSame('', $buffer);
    }

    public function test_real_tcp_diagnostic_process_receives_simulated_packets_and_sends_documented_ack(): void
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($reservation, false), ':'), 1);
        fclose($reservation);
        $process = new Process([PHP_BINARY, 'artisan', 'gps:diagnose', '--host=127.0.0.1', '--port='.$port,
            '--imei=123456789012345', '--seconds=3'], base_path());
        $socket = null;
        try {
            $process->start();
            $deadline = microtime(true) + 5;
            while (! str_contains($process->getOutput(), 'GPS TCP DIAGNOSTIC LISTENER')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail('Diagnostic startup failed: '.$process->getOutput());
                }
                usleep(20000);
            }
            $socket = stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 3);
            stream_set_timeout($socket, 3);
            fwrite($socket, substr($this->fixture('gt06-login-official'), 0, 5));
            usleep(20000);
            fwrite($socket, substr($this->fixture('gt06-login-official'), 5).$this->simulatedLocation());
            $reply = '';
            while (strlen($reply) < 10) {
                $chunk = fread($socket, 10 - strlen($reply));
                if ($chunk === '' || $chunk === false) {
                    break;
                }
                $reply .= $chunk;
            }
            $this->assertSame('787805010001D9DC0D0A', strtoupper(bin2hex($reply)));
            fclose($socket);
            $socket = null;
            $this->assertSame(0, $process->wait());
            $output = $process->getOutput();
            foreach (['CONNECTED', 'DATA_RECEIVED', 'LOGIN', 'ACK_SENT', 'GPS_LOCATION', 'DISCONNECTED'] as $event) {
                $this->assertStringContainsString('"event":"'.$event.'"', $output);
            }
            $this->assertStringContainsString('DIAGNOSTIC ONLY', $output);
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
}
