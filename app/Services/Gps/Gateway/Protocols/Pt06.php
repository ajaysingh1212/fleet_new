<?php

namespace App\Services\Gps\Gateway\Protocols;

use App\Services\Gps\Gateway\BinaryReader;
use App\Services\Gps\Gateway\Gt06DiagnosticDetails;
use App\Services\Gps\Gateway\Gt06DiagnosticFrames;
use App\Services\Gps\Gateway\Location;
use App\Services\Gps\Gateway\Packet;
use App\Services\Gps\Gateway\Protocol;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** User-supplied PT06 2022-04-27 V02; section/page references in docs/GPS-PT06.md. */
final class Pt06 implements Protocol
{
    public const VERSION = '2022.4.27-v2';

    public function name(): string
    {
        return 'pt06';
    }

    public function detect(string $buffer): ?bool
    {
        if (strlen($buffer) < 2) {
            return null;
        }
        if (substr($buffer, 0, 2) !== "\x78\x78") {
            return false;
        }
        if (strlen($buffer) < 4) {
            return null;
        }
        // Legacy 18-byte IMEI-only login is shared with GT06 and is resolved
        // against the registered protocol, never guessed by byte magic alone.
        if (ord($buffer[2]) !== 17 || ord($buffer[3]) !== 1) {
            return false;
        }
        if (strlen($buffer) < 14) {
            return null;
        }

        return substr($buffer, 12, 2) !== "\xa0\x01";
    }

    public function extract(string &$buffer, bool $authenticated): ?string
    {
        return (new Gt06DiagnosticFrames)->extract($buffer);
    }

    public function decode(string $frame, bool $authenticated): Packet
    {
        $buffer = $frame;
        if ((new Gt06DiagnosticFrames)->extract($buffer) !== $frame || $buffer !== '') {
            throw new InvalidArgumentException('invalid_frame');
        }
        $extended = substr($frame, 0, 2) === "\x79\x79";
        $offset = $extended ? 4 : 3;
        $type = ord($frame[$offset]);
        $payload = substr($frame, $offset + 1, -6);
        $serial = substr($frame, -6, 2);
        if ($type === 1) {
            return $this->login($payload, $serial, $authenticated, $extended);
        }
        if (! $authenticated) {
            throw new InvalidArgumentException('login_required');
        }

        return match ($type) {
            0x13 => $this->heartbeat($payload, $serial),
            0x22 => $this->position($payload, $serial),
            0x26 => $this->alarm($payload, $serial),
            0x24 => $this->lbs($payload),
            0x94 => $this->information($frame, $payload),
            0x21 => $this->commandResponse($payload),
            0x2A => $this->addressRequest($payload),
            // IDs 27 and 2C are listed in section 4.3 but have no field layout.
            // Preserve diagnostics without inventing coordinates or ACKs.
            default => new Packet('unsupported', null, self::VERSION, events: ['protocol_type' => sprintf('0x%02X', $type),
                'payload_hex' => strtoupper(bin2hex($payload)), 'decode_status' => 'Layout not defined by supplied PT06 manual; no ACK sent']),
        };
    }

    private function login(string $payload, string $serial, bool $authenticated, bool $extended): Packet
    {
        if ($authenticated || $extended || ! in_array(strlen($payload), [8, 12], true)) {
            throw new InvalidArgumentException('unsupported_login_variant');
        }
        $bcd = bin2hex(substr($payload, 0, 8));
        if (! preg_match('/^0[0-9]{15}$/D', $bcd)) {
            throw new InvalidArgumentException('invalid_identifier');
        }
        $events = ['login_variant' => strlen($payload) === 12 ? 'model and timezone' : 'legacy IMEI-only capture',
            'model_identifier' => null, 'device_timezone' => null, 'language' => null];
        if (strlen($payload) === 12) {
            $model = unpack('n', substr($payload, 8, 2))[1];
            $timezone = unpack('n', substr($payload, 10, 2))[1];
            $magnitude = $timezone >> 4;
            $hours = intdiv($magnitude, 100);
            $minutes = $magnitude % 100;
            if ($hours > 14 || $minutes > 59) {
                throw new InvalidArgumentException('invalid_timezone');
            }
            $events += ['model_capability' => match ($model) {
                0x53 => 'door / air-conditioning', 0x54 => 'temperature sensor', default => 'not specified for this model code'
            }];
            $events['model_identifier'] = sprintf('0x%04X', $model);
            $events['device_timezone'] = sprintf('UTC%s%02d:%02d', ($timezone & 8) ? '-' : '+', $hours, $minutes);
            $events['language'] = $this->language($timezone & 3);
            $events['timezone_language_hex'] = sprintf('%04X', $timezone);
        }

        return new Packet('login', substr($bcd, 1), self::VERSION, $this->ack(1, $serial), events: $events);
    }

    private function heartbeat(string $payload, string $serial): Packet
    {
        if (strlen($payload) !== 5) {
            throw new InvalidArgumentException('invalid_heartbeat_length');
        }
        $events = $this->status(ord($payload[0]), ord($payload[1]), ord($payload[2]));
        $power = ord($payload[1]);
        if (($power & 0xF0) === 0xF0) {
            $events['power_level'] = null;
            $events['external_power'] = true;
            $events['external_voltage'] = ((($power & 15) << 8) | ord($payload[3])) / 10;
        } elseif ($power > 6) {
            throw new InvalidArgumentException('unsupported_voltage_encoding');
        } else {
            $events['external_voltage'] = null;
        }
        $events['extension_byte_hex'] = sprintf('%02X', ord($payload[3]));
        $events['language'] = $this->language(ord($payload[4]));

        return new Packet('heartbeat', null, self::VERSION, $this->ack(0x13, $serial), events: $events);
    }

    private function position(string $payload, string $serial): Packet
    {
        // Section 5.2: 29 bytes without mileage, 33 with a uint32 metre counter.
        if (! in_array(strlen($payload), [29, 33], true)) {
            throw new InvalidArgumentException('invalid_position_length');
        }
        $r = new BinaryReader($payload);
        $gps = $this->gps($r);
        $telemetry = $this->cell($r);
        $acc = $r->number(1);
        $mode = $r->number(1);
        $retransmission = $r->number(1);
        if ($acc > 1 || $retransmission > 1) {
            throw new InvalidArgumentException('invalid_position_status');
        }
        $telemetry += ['acc' => (bool) $acc, 'reporting_mode_raw' => $mode, 'gps_retransmission' => (bool) $retransmission,
            'odometer_m' => strlen($payload) === 33 ? $r->number(4) : null];
        $location = $this->location($gps, $telemetry);

        // GT06-compatible firmware can wait for a serial-matched location ACK
        // before draining its stored fixes. The receiver sends this only after
        // the authenticated packet has been durably accepted, like login/heartbeat.
        return new Packet('location', null, self::VERSION,
            config('gps.pt06_location_ack', true) ? $this->ack(0x22, $serial) : '', [$location]);
    }

    private function alarm(string $payload, string $serial): Packet
    {
        if (strlen($payload) !== 32) {
            throw new InvalidArgumentException('invalid_alarm_length');
        }
        $r = new BinaryReader($payload);
        $gps = $this->gps($r);
        $lbsLength = $r->number(1);
        if ($lbsLength !== 8) {
            throw new InvalidArgumentException('unsupported_alarm_lbs_length');
        }
        $telemetry = $this->cell($r);
        $status = $this->status($r->number(1), $r->number(1), $r->number(1));
        if ($status['voltage_raw'] > 6) {
            throw new InvalidArgumentException('unsupported_alarm_voltage');
        }
        $alarm = $r->number(1);
        $language = $r->number(1);
        $events = [...$status, 'alarm_code' => sprintf('0x%02X', $alarm), 'alarm_type' => $this->alarmName($alarm),
            'sos' => $alarm === 1 || $status['terminal_alarm_bits'] === 4, 'language' => $this->language($language)];

        return new Packet('alarm', null, self::VERSION, $this->ack(0x26, $serial),
            [$this->location($gps, [...$telemetry, ...$events])], $events);
    }

    private function lbs(string $payload): Packet
    {
        // Section 5.5 explicitly allocates five six-byte tower slots and three
        // reserved bytes, even though its example Cell Num is four.
        if (strlen($payload) !== 44) {
            throw new InvalidArgumentException('invalid_lbs_length');
        }
        $r = new BinaryReader($payload);
        $time = $this->timestamp($r);
        $ta = $r->number(1);
        $mcc = $r->number(2);
        $mnc = $r->number(1);
        $count = $r->number(1);
        if ($count > 5 || $mcc > 999) {
            throw new InvalidArgumentException('invalid_lbs_count');
        }
        $cells = [];
        for ($n = 0; $n < 5; $n++) {
            $lac = $r->number(2);
            $cell = hexdec(bin2hex($r->take(3)));
            $rssi = $r->number(1);
            $cells[] = ['slot' => $n + 1, 'lac' => $lac, 'cell_id' => $cell, 'rssi_raw' => $rssi,
                'rssi_dbm' => -$rssi, 'active' => $n < $count];
        }

        return new Packet('lbs', null, self::VERSION, events: ['recorded_at' => $time->format('Y-m-d H:i:s'),
            'timing_advance' => $ta, 'mcc' => $mcc, 'mnc' => $mnc, 'cell_count' => $count, 'cells' => $cells,
            'reserved_hex' => strtoupper(bin2hex($r->take(3))), 'coordinates' => null,
            'note' => 'Cell tower measurements only; no latitude/longitude without a separate lookup service']);
    }

    private function information(string $frame, string $payload): Packet
    {
        if ($payload === '') {
            throw new InvalidArgumentException('empty_information');
        }
        $subtype = ord($payload[0]);
        if ($subtype === 0x0A) {
            $data = (new Gt06DiagnosticDetails)->simInformation($frame);
            if ($data === null) {
                throw new InvalidArgumentException('unsupported_sim_information_length');
            }

            return new Packet('device_sim_information', null, self::VERSION, events: $data);
        }

        return new Packet('information', null, self::VERSION, events: ['subtype' => sprintf('0x%02X', $subtype),
            'information_type' => match ($subtype) {
                0 => 'external voltage', 4 => 'terminal status synchronization', 5 => 'door / air conditioning',
                8 => 'self inspection', 9 => 'positioning satellite information', default => 'custom / unknown'
            },
            'payload_hex' => strtoupper(bin2hex(substr($payload, 1))), 'decode_status' => 'Subtype named but byte layout not supplied; values not inferred']);
    }

    private function commandResponse(string $payload): Packet
    {
        if (strlen($payload) < 5) {
            throw new InvalidArgumentException('invalid_command_response');
        }
        $flag = unpack('N', substr($payload, 0, 4))[1];
        $encoding = ord($payload[4]);
        $content = substr($payload, 5);
        if ($encoding === 1) {
            if (preg_match('/[^\x09\x0a\x0d\x20-\x7e]/', $content)) {
                throw new InvalidArgumentException('invalid_ascii_response');
            }
            $text = $content;
        } elseif ($encoding === 2) {
            if (strlen($content) % 2 !== 0 || ! mb_check_encoding($content, 'UTF-16BE')) {
                throw new InvalidArgumentException('invalid_unicode_response');
            }
            $text = mb_convert_encoding($content, 'UTF-8', 'UTF-16BE');
        } else {
            throw new InvalidArgumentException('unsupported_response_encoding');
        }

        return new Packet('command_response', null, self::VERSION, events: ['server_flag' => $flag,
            'content_encoding' => $encoding === 1 ? 'ASCII' : 'UTF-16BE', 'response_text' => $text]);
    }

    private function addressRequest(string $payload): Packet
    {
        if (strlen($payload) !== 41) {
            throw new InvalidArgumentException('invalid_address_request_length');
        }
        $r = new BinaryReader($payload);
        $gps = $this->gps($r);
        $phone = $r->take(21);
        $language = $r->number(2);
        // Right padding is explicitly documented; preserve the raw bytes too.
        $events = ['phone_field_ascii' => $phone, 'phone_field_hex' => strtoupper(bin2hex($phone)),
            'language' => $this->language($language), 'address_reply' => 'Requires an address service; no fabricated address sent'];

        return new Packet('address_request', null, self::VERSION, '', [$this->location($gps, [])], $events);
    }

    private function gps(BinaryReader $r): array
    {
        $time = $this->timestamp($r);
        $satelliteByte = $r->number(1);
        if (! in_array($satelliteByte >> 4, [0, 12], true)) {
            throw new InvalidArgumentException('unsupported_gps_length');
        }
        $lat = $r->number(4) / 1800000;
        $lon = $r->number(4) / 1800000;
        $speed = $r->number(1);
        $course = $r->number(2);
        if (! ($course & 0x400)) {
            $lat = -$lat;
        }
        if ($course & 0x800) {
            $lon = -$lon;
        }

        return ['time' => $time, 'latitude' => $lat, 'longitude' => $lon, 'speed' => $speed,
            'heading' => $course & 1023, 'satellites' => $satelliteByte & 15, 'gps_fix' => (bool) ($course & 0x1000),
            'course_status_hex' => sprintf('%04X', $course), 'differential_gps' => (bool) ($course & 0x2000)];
    }

    private function timestamp(BinaryReader $r): CarbonImmutable
    {
        [$y, $m, $d, $h, $i, $s] = array_values(unpack('C6', $r->take(6)));
        if (! checkdate($m, $d, $y + 2000) || $h > 23 || $i > 59 || $s > 59) {
            throw new InvalidArgumentException('invalid_timestamp');
        }
        $time = CarbonImmutable::create($y + 2000, $m, $d, $h, $i, $s, 'UTC');
        if ($time->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            throw new InvalidArgumentException('invalid_timestamp');
        }

        return $time;
    }

    private function cell(BinaryReader $r): array
    {
        return ['mcc' => $r->number(2), 'mnc' => $r->number(1), 'lac' => $r->number(2), 'cell_id' => hexdec(bin2hex($r->take(3)))];
    }

    private function location(array $gps, array $telemetry): Location
    {
        return new Location($gps['latitude'], $gps['longitude'], $gps['time'], $gps['speed'], $gps['heading'],
            null, $gps['satellites'], $gps['gps_fix'], [...$telemetry, 'course_status_hex' => $gps['course_status_hex'],
                'differential_gps' => $gps['differential_gps']]);
    }

    private function status(int $terminal, int $power, int $gsm): array
    {
        if ($gsm > 4) {
            throw new InvalidArgumentException('invalid_gsm_level');
        }
        $alarm = ($terminal >> 3) & 7;

        return ['acc' => (bool) ($terminal & 2), 'gps_positioned_reported' => (bool) ($terminal & 0x40),
            'relay_disconnected' => (bool) ($terminal & 0x80), 'charging' => (bool) ($terminal & 4),
            'defense_active' => (bool) ($terminal & 1), 'terminal_alarm_bits' => $alarm,
            'terminal_alarm_type' => [0 => 'normal', 1 => 'vibration', 2 => 'power_cut', 3 => 'low_battery', 4 => 'sos'][$alarm] ?? 'reserved',
            'terminal_status_hex' => sprintf('%02X', $terminal), 'voltage_raw' => $power, 'power_level' => $power <= 6 ? $power : null,
            'power_description' => [0 => 'power_off', 1 => 'extremely_low', 2 => 'very_low', 3 => 'low_normal_use', 4 => 'medium', 5 => 'high', 6 => 'very_high'][$power] ?? 'external_supply',
            'gsm_level' => $gsm, 'gsm_description' => [0 => 'none', 1 => 'extremely_weak', 2 => 'weak', 3 => 'good', 4 => 'strong'][$gsm]];
    }

    private function alarmName(int $alarm): string
    {
        return [0 => 'normal', 1 => 'sos', 2 => 'power_cut', 3 => 'vibration', 4 => 'fence_entry', 5 => 'fence_exit',
            6 => 'overspeed', 9 => 'movement', 10 => 'gps_blind_area_entry', 11 => 'gps_blind_area_exit', 12 => 'power_on',
            14 => 'external_power_low', 15 => 'external_power_low_protection', 17 => 'power_off', 19 => 'demolition',
            20 => 'door', 21 => 'low_power_shutdown', 44 => 'collision', 45 => 'flip', 76 => 'sharp_turn', 48 => 'harsh_braking', 41 => 'rapid_acceleration'][$alarm] ?? 'unknown_'.sprintf('%02X', $alarm);
    }

    private function language(int $code): string
    {
        return [1 => 'Chinese', 2 => 'English'][$code] ?? 'unknown_'.sprintf('%04X', $code);
    }

    private function ack(int $type, string $serial): string
    {
        $data = "\x05".chr($type).$serial;

        return "\x78\x78".$data.pack('n', ConcoxX1::crc($data))."\r\n";
    }
}
