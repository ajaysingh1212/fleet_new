<?php

namespace App\Services\Gps\Gateway\Protocols;

use App\Services\Gps\Gateway\BinaryReader;
use App\Services\Gps\Gateway\Location;
use App\Services\Gps\Gateway\Packet;
use App\Services\Gps\Gateway\Protocol;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Manufacturer X1 V1.1 (2017-05-10); not a generic GT06 decoder. */
final class ConcoxX1 implements Protocol
{
    public function name(): string
    {
        return 'concox-x1';
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

        if (ord($buffer[2]) !== 17 || ord($buffer[3]) !== 1) {
            return false;
        }
        if (strlen($buffer) < 14) {
            return null;
        }

        return substr($buffer, 12, 2) === "\xa0\x01";
    }

    public function extract(string &$buffer, bool $authenticated): ?string
    {
        if (strlen($buffer) < 3) {
            return null;
        }
        if (substr($buffer, 0, 2) !== "\x78\x78") {
            throw new InvalidArgumentException('unsupported_framing');
        }
        $size = ord($buffer[2]);
        if ($size < 5) {
            throw new InvalidArgumentException('invalid_length');
        }
        $length = $size + 5;
        if (strlen($buffer) < $length) {
            return null;
        }
        $frame = substr($buffer, 0, $length);
        $buffer = substr($buffer, $length);

        return $frame;
    }

    public static function crc(string $data): int
    {
        $crc = 0xFFFF;
        foreach (unpack('C*', $data) as $byte) {
            $crc ^= $byte;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 1) ? ($crc >> 1) ^ 0x8408 : $crc >> 1;
            }
        }

        return (~$crc) & 0xFFFF;
    }

    public function decode(string $frame, bool $authenticated): Packet
    {
        if (substr($frame, -2) !== "\r\n" || unpack('n', substr($frame, -4, 2))[1] !== self::crc(substr($frame, 2, -4))) {
            throw new InvalidArgumentException('invalid_checksum');
        }
        $type = ord($frame[3]);
        $payload = substr($frame, 4, -6);
        $serial = substr($frame, -6, 2);
        if ($type === 1) {
            if ($authenticated || strlen($payload) !== 12 || substr($payload, 8, 2) !== "\xa0\x01") {
                throw new InvalidArgumentException('unsupported_variant');
            }
            $identifier = bin2hex(substr($payload, 0, 8));
            if (! preg_match('/^0[0-9]{15}$/D', $identifier)) {
                throw new InvalidArgumentException('invalid_identifier');
            }

            return new Packet('login', substr($identifier, 1), '1.1', $this->ack($type, $serial));
        }
        if (! $authenticated) {
            throw new InvalidArgumentException('login_required');
        }
        if ($type === 0x13) {
            if (strlen($payload) !== 5 || ord($payload[1]) > 6 || ord($payload[2]) > 4) {
                throw new InvalidArgumentException('invalid_status');
            }

            return new Packet('heartbeat', null, '1.1', $this->ack($type, $serial), events: [
                'acc' => (bool) (ord($payload[0]) & 2), 'gsm_level' => ord($payload[2]), 'power_level' => ord($payload[1]),
            ]);
        }
        if ($type !== 0x34) {
            throw new InvalidArgumentException('unsupported_packet');
        }
        $r = new BinaryReader($payload);
        $alarm = $r->number(4) >> 24;
        $date = array_values(unpack('C6', $r->take(6)));
        [$year, $month, $day, $hour, $minute, $second] = $date;
        if (! checkdate($month, $day, 2000 + $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidArgumentException('invalid_timestamp');
        }
        $time = CarbonImmutable::create(2000 + $year, $month, $day, $hour, $minute, $second, 'UTC');
        $satellites = $r->number(1) & 15;
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
        $r->number(2); // terminal-info
        $odometer = $r->number(4);
        $r->take(13); // MCC(2), MNC(1), LAC(2), Cell-ID(4), RFID(4)
        $external = $r->number(2) / 100;
        $battery = $r->number(2) / 100;
        $ports = $r->number(4);
        $input = $r->number(1);
        $r->number(1); // outputs
        $r->take(2 * (($ports >> 20) & 15) + 2 * (($ports >> 12) & 15));
        if ($r->offset !== strlen($payload)) {
            throw new InvalidArgumentException('invalid_length');
        }
        $telemetry = ['odometer_m' => $odometer, 'external_voltage' => $external, 'battery_voltage' => $battery,
            'alarm_code' => $alarm, 'acc' => (($ports >> 28) & 15) >= 2 ? (bool) ($input & 2) : null,
            'sos' => (($ports >> 28) & 15) >= 1 ? (bool) ($input & 1) : null];

        // X1 manual does not define an ACK for 0x34; never invent one.
        return new Packet('location', null, '1.1', '', [new Location($lat, $lon, $time, $speed,
            $course & 1023, null, $satellites, (bool) ($course & 0x1000), $telemetry)], $telemetry);
    }

    private function ack(int $type, string $serial): string
    {
        $data = "\x05".chr($type).$serial;

        return "\x78\x78".$data.pack('n', self::crc($data))."\r\n";
    }
}
