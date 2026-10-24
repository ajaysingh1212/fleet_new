<?php

namespace Tests\Feature;

use App\Services\Gps\Gateway\GpsDiagnosticFormatter;
use App\Services\Gps\Gateway\GpsDiagnosticState;
use App\Services\Gps\Gateway\ProtocolRegistry;
use App\Services\Gps\Gateway\Protocols\ConcoxX1;
use App\Services\Gps\Gateway\Protocols\Pt06;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Pt06ProtocolTest extends TestCase
{
    private function deviceFixture(string $type): string
    {
        return hex2bin(trim(file_get_contents(base_path('tests/Fixtures/gps/pt06/device-'.$type.'-20261017.hex'))));
    }

    public function test_two_allowlisted_devices_have_separate_tcp_output_and_unknown_device_is_rejected(): void
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($reservation, false), ':'), 1);
        fclose($reservation);
        $process = new Process([PHP_BINARY, 'artisan', 'gps:diagnose', '--host=127.0.0.1', '--port='.$port,
            '--imei=864540081540460,861128068689493', '--seconds=4', '--format=json'], base_path());
        $sockets = [];
        try {
            $process->start();
            $deadline = microtime(true) + 5;
            while (! str_contains($process->getOutput(), 'GPS TCP DIAGNOSTIC LISTENER')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail($process->getOutput());
                }
                usleep(20000);
            }
            foreach (['864540081540460', '861128068689493', '123456789012345'] as $index => $imei) {
                $socket = stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 3);
                $sockets[] = $socket;
                stream_set_timeout($socket, 3);
                // Simulated IMEI-only logins. Only the first device has a GPS packet.
                fwrite($socket, $this->frame(1, hex2bin('0'.$imei))
                    .($index === 0 ? $this->fixture('location') : '')
                    .$this->deviceFixture('heartbeat'));
                $reply = '';
                while (strlen($reply) < 20 && ($chunk = fread($socket, 20 - strlen($reply))) !== '' && $chunk !== false) {
                    $reply .= $chunk;
                }
                $this->assertSame($index < 2 ? 20 : 0, strlen($reply));
            }
            $this->assertSame(0, $process->wait());
            $records = [];
            foreach (explode("\n", $process->getOutput()) as $line) {
                $record = json_decode(trim($line), true);
                if (is_array($record)) {
                    $records[] = $record;
                }
            }
            $summaries = array_values(array_filter($records, fn ($record) => $record['event'] === 'DEVICE_STATUS'));
            $this->assertCount(2, $summaries);
            $this->assertSame('864540081540460', $summaries[0]['imei']);
            $this->assertNotNull($summaries[0]['last_reported_position']);
            $this->assertSame('861128068689493', $summaries[1]['imei']);
            $this->assertNull($summaries[1]['last_reported_position']);
            $this->assertSame(['login' => 1, 'heartbeat' => 1], $summaries[1]['packet_counts']);
            $rejected = array_values(array_filter($records, fn ($record) => $record['event'] === 'DISCONNECTED' && $record['reason'] === 'not_diagnostic_allowlisted'));
            $this->assertCount(1, $rejected);
        } finally {
            foreach ($sockets as $socket) {
                if (is_resource($socket)) {
                    fclose($socket);
                }
            }
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    public function test_actual_device_capture_explains_no_fix_low_power_and_decodes_all_available_fields(): void
    {
        $state = new GpsDiagnosticState;
        $protocol = new Pt06;
        foreach (['login', 'heartbeat', 'sim'] as $type) {
            $packet = $protocol->decode($this->deviceFixture($type), $type !== 'login');
            $this->assertSame([], $packet->locations);
            $state->observe($packet, '864540081540460', '2026-10-17 11:53:47 IST');
        }
        $data = $state->snapshot(false);
        $this->assertSame(['login' => 1, 'heartbeat' => 1, 'device_sim_information' => 1], $data['packet_counts']);
        $this->assertFalse($data['device_connected_now']);
        $this->assertNull($data['last_reported_position']);
        $this->assertStringContainsString('No coordinate-bearing packet received', $data['location_status']);
        $this->assertStringContainsString('GPS NOT positioned', $data['location_status']);
        $heartbeat = $data['last_reported_heartbeat'];
        $this->assertSame('extremely_low', $heartbeat['power_description']);
        $this->assertSame('strong', $heartbeat['gsm_description']);
        $this->assertFalse($heartbeat['acc']);
        $this->assertFalse($heartbeat['charging']);
        $this->assertSame('normal', $heartbeat['terminal_alarm_type']);
        $this->assertSame('English', $heartbeat['language']);
        $this->assertSame('4055434022540900', $data['last_reported_sim']['imsi_encoded']);
        $this->assertSame('89915426034022540908', $data['last_reported_sim']['iccid_encoded']);
        $formatter = new GpsDiagnosticFormatter;
        $text = $formatter->format('time', 'HEARTBEAT', $heartbeat);
        foreach (['EXTREMELY LOW', 'STRONG', 'NO GPS FIX', 'not a battery percentage'] as $message) {
            $this->assertStringContainsString($message, $text);
        }
        $summary = $formatter->format('time', 'DEVICE_STATUS', $data);
        foreach (['864540081540460', '4055434022540900', '89915426034022540908', 'No coordinate-bearing packet received'] as $message) {
            $this->assertStringContainsString($message, $summary);
        }
        $state->observe($protocol->decode($this->fixture('location'), true), '864540081540460', 'later');
        $this->assertNotNull($state->snapshot(true)['last_reported_position']);
        $this->assertStringNotContainsString('No coordinate-bearing packet received', $state->snapshot(true)['location_status']);
    }

    public function test_actual_capture_is_decoded_in_real_terminal_without_inventing_a_location(): void
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($reservation, false), ':'), 1);
        fclose($reservation);
        $process = new Process([PHP_BINARY, 'artisan', 'gps:diagnose', '--host=127.0.0.1', '--port='.$port,
            '--imei=864540081540460', '--seconds=3', '--format=pretty', '--protocol=pt06'], base_path());
        $socket = null;
        try {
            $process->start();
            $deadline = microtime(true) + 5;
            while (! str_contains($process->getOutput(), 'GPS TCP DIAGNOSTIC LISTENER')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail($process->getOutput());
                }
                usleep(20000);
            }
            $socket = stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 3);
            stream_set_timeout($socket, 3);
            fwrite($socket, $this->deviceFixture('login').$this->deviceFixture('heartbeat').$this->deviceFixture('sim'));
            $reply = '';
            while (strlen($reply) < 20 && ($chunk = fread($socket, 20 - strlen($reply))) !== '' && $chunk !== false) {
                $reply .= $chunk;
            }
            $this->assertSame('787805010008441D0D0A78780513000965B90D0A', strtoupper(bin2hex($reply)));
            fclose($socket);
            $socket = null;
            $this->assertSame(0, $process->wait());
            $output = $process->getOutput();
            foreach (['decoded-device-summary-v2', 'DEVICE STATUS', 'EXTREMELY LOW', 'STRONG', 'NO GPS FIX', '4055434022540900', '89915426034022540908'] as $text) {
                $this->assertStringContainsString($text, $output);
            }
            $this->assertStringNotContainsString('https://maps.google.com', $output);
            $this->assertStringNotContainsString('PACKET NOT DECODED', $output);
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    private function fixture(string $name): string
    {
        return hex2bin(trim(file_get_contents(base_path('tests/Fixtures/gps/pt06/pdf-'.$name.'.hex'))));
    }

    private function frame(int $type, string $payload, bool $extended = false, string $serial = "\x00\x01"): string
    {
        $body = chr($type).$payload.$serial;
        $data = ($extended ? pack('n', strlen($body) + 2) : chr(strlen($body) + 2)).$body;

        return ($extended ? "\x79\x79" : "\x78\x78").$data.pack('n', ConcoxX1::crc($data))."\r\n";
    }

    public function test_exact_pdf_login_timezone_heartbeat_and_acknowledgements(): void
    {
        $p = new Pt06;
        $login = $p->decode($this->fixture('login'), false);
        $this->assertSame('359097370008313', $login->identifier);
        $this->assertSame('UTC+05:30', $login->events['device_timezone']);
        $this->assertSame('English', $login->events['language']);
        $this->assertSame($this->fixture('login-ack'), $login->ack);
        $this->assertSame('pt06', app(ProtocolRegistry::class)->detect($this->fixture('login'))->name());
        $heartbeat = $p->decode($this->fixture('heartbeat'), true);
        $this->assertSame($this->fixture('heartbeat-ack'), $heartbeat->ack);
        $this->assertSame(6, $heartbeat->events['power_level']);
        $this->assertSame(4, $heartbeat->events['gsm_level']);
        // Actual user's legacy IMEI-only login remains compatible.
        $capture = hex2bin(trim(file_get_contents(base_path('tests/Fixtures/gps/bt50-user-login.hex'))));
        $this->assertSame('864540081540460', $p->decode($capture, false)->identifier);
    }

    public function test_exact_pdf_location_and_alarm_coordinates_and_units(): void
    {
        $p = new Pt06;
        $packet = $p->decode($this->fixture('location'), true);
        $gps = $packet->locations[0];
        $this->assertSame($this->frame(0x22, '', false, substr($this->fixture('location'), -6, 2)), $packet->ack);
        $this->assertEqualsWithDelta(12.8852583333, $gps->latitude, 0.0000001);
        $this->assertEqualsWithDelta(74.879855, $gps->longitude, 0.0000001);
        $this->assertSame('2022-04-23 13:47:29', $gps->recordedAt->format('Y-m-d H:i:s'));
        $this->assertSame(17.0, $gps->speed);
        $this->assertSame(78.0, $gps->heading);
        $this->assertTrue($gps->gpsFix);
        $this->assertSame(12, $gps->satellites);
        $this->assertNull($gps->telemetry['odometer_m']);
        $alarm = $p->decode($this->fixture('alarm'), true);
        $this->assertSame($this->fixture('alarm-ack'), $alarm->ack);
        $this->assertSame('demolition', $alarm->events['alarm_type']);
        $this->assertTrue($alarm->locations[0]->gpsFix);
        $this->assertSame(404, $alarm->locations[0]->telemetry['mcc']);
    }

    public function test_simulated_optional_mileage_hemispheres_and_external_voltage(): void
    {
        $payload = substr($this->fixture('location'), 4, -6);
        // Replace course flags: south, west, valid fix, heading 90.
        $payload = substr_replace($payload, pack('n', 0x185A), 16, 2);
        $gps = (new Pt06)->decode($this->frame(0x22, $payload.pack('N', 123456)), true)->locations[0];
        $this->assertLessThan(0, $gps->latitude);
        $this->assertLessThan(0, $gps->longitude);
        $this->assertSame(90.0, $gps->heading);
        $this->assertSame(123456, $gps->telemetry['odometer_m']);
        $heartbeat = (new Pt06)->decode($this->frame(0x13, hex2bin('46F0027E02')), true);
        $this->assertNull($heartbeat->events['power_level']);
        $this->assertSame(12.6, $heartbeat->events['external_voltage']);
        $this->assertTrue($heartbeat->events['acc']);
    }

    public function test_location_ack_matches_each_serial_and_can_be_disabled_for_strict_manual_compatibility(): void
    {
        $payload = substr($this->fixture('location'), 4, -6);
        foreach (["\x00\x10", "\x12\x34", "\xff\xff"] as $serial) {
            $frame = $this->frame(0x22, $payload, false, $serial);
            $this->assertSame($this->frame(0x22, '', false, $serial), (new Pt06)->decode($frame, true)->ack);
        }
        config(['gps.pt06_location_ack' => false]);
        $this->assertSame('', (new Pt06)->decode($frame, true)->ack);
    }

    public function test_simulated_lbs_sim_command_response_and_unknown_layouts(): void
    {
        $p = new Pt06;
        $time = hex2bin('1604170D2F1D');
        $slot = pack('n', 5432).hex2bin('123456').chr(75);
        $lbs = $p->decode($this->frame(0x24, $time.chr(1).pack('nCC', 404, 2, 4).str_repeat($slot, 5).str_repeat("\0", 3)), true);
        $this->assertCount(5, $lbs->events['cells']);
        $this->assertSame(-75, $lbs->events['cells'][0]['rssi_dbm']);
        $this->assertFalse($lbs->events['cells'][4]['active']);
        $this->assertSame([], $lbs->locations);
        $this->assertSame('', $lbs->ack);
        $sim = $p->decode($this->frame(0x94, hex2bin('0A01234567890123450460042429802256898604041018C0002255'), true), true);
        $this->assertSame('123456789012345', $sim->events['reported_imei']);
        $this->assertSame('898604041018C0002255', $sim->events['iccid_encoded']);
        foreach ([1 => 'GPS OK', 2 => '位置正常'] as $encoding => $text) {
            $content = $encoding === 2 ? mb_convert_encoding($text, 'UTF-16BE', 'UTF-8') : $text;
            $reply = $p->decode($this->frame(0x21, pack('N', 7).chr($encoding).$content, true), true);
            $this->assertSame($text, $reply->events['response_text']);
            $this->assertSame('', $reply->ack);
        }
        foreach ([0x27, 0x2C] as $type) {
            $packet = $p->decode($this->frame($type, 'unknown'), true);
            $this->assertSame('unsupported', $packet->type);
            $this->assertSame([], $packet->locations);
            $this->assertSame('', $packet->ack);
            $this->assertSame(strtoupper(bin2hex('unknown')), $packet->events['payload_hex']);
        }
        $address = $p->decode($this->frame(0x2A, substr($this->fixture('location'), 4, 18).str_pad('919876543210', 21, '0').pack('n', 2)), true);
        $this->assertCount(1, $address->locations);
        $this->assertSame('', $address->ack);
        $this->assertSame('English', $address->events['language']);
    }

    public function test_crc_authentication_and_semantic_validation_reject_bad_packets(): void
    {
        $p = new Pt06;
        $bad = $this->fixture('location');
        $bad[12] = ~$bad[12];
        $payload = substr($this->fixture('location'), 4, -6);
        $cases = [[$bad, true], [$this->fixture('location'), false], [$this->fixture('login'), true],
            [$this->frame(0x22, substr($payload, 1)), true],
            [$this->frame(0x22, substr_replace($payload, chr(13), 1, 1)), true],
            [$this->frame(0x22, substr_replace($payload, pack('N', 181 * 1800000), 11, 4)), true]];
        foreach ($cases as [$frame, $authenticated]) {
            try {
                $p->decode($frame, $authenticated);
                $this->fail('Malformed or unauthenticated packet accepted');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_real_tcp_pt06_listener_handles_fragmented_login_and_coalesced_pdf_packets(): void
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($reservation, false), ':'), 1);
        fclose($reservation);
        $process = new Process([PHP_BINARY, 'artisan', 'gps:diagnose', '--host=127.0.0.1', '--port='.$port,
            '--imei=359097370008313', '--seconds=3', '--format=json', '--protocol=pt06'], base_path());
        $socket = null;
        try {
            $process->start();
            $deadline = microtime(true) + 5;
            while (! str_contains($process->getOutput(), 'GPS TCP DIAGNOSTIC LISTENER')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail($process->getOutput());
                }
                usleep(20000);
            }
            $socket = stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 3);
            stream_set_timeout($socket, 3);
            fwrite($socket, substr($this->fixture('login'), 0, 5));
            usleep(20000);
            fwrite($socket, substr($this->fixture('login'), 5).$this->fixture('heartbeat').$this->fixture('location').$this->fixture('alarm'));
            $reply = '';
            while (strlen($reply) < 40 && ($chunk = fread($socket, 40 - strlen($reply))) !== '' && $chunk !== false) {
                $reply .= $chunk;
            }
            $this->assertSame($this->fixture('login-ack').$this->fixture('heartbeat-ack').$this->frame(0x22, '', false, substr($this->fixture('location'), -6, 2)).$this->fixture('alarm-ack'), $reply);
            fclose($socket);
            $socket = null;
            $this->assertSame(0, $process->wait());
            $output = $process->getOutput();
            foreach (['LOGIN_DETAILS', 'HEARTBEAT', 'GPS_LOCATION', 'ALARM_DETAILS', 'ACK_SENT'] as $event) {
                $this->assertStringContainsString('"event":"'.$event.'"', $output);
            }
            $this->assertStringNotContainsString('PACKET_NOT_DECODED', $output);
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
