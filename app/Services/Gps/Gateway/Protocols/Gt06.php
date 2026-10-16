<?php

namespace App\Services\Gps\Gateway\Protocols;

use App\Services\Gps\Gateway\BinaryReader;
use App\Services\Gps\Gateway\Location;
use App\Services\Gps\Gateway\Packet;
use App\Services\Gps\Gateway\Protocol;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Concox GT06 v1.8.1: classic 01 login, 12 position and 13 heartbeat only. */
final class Gt06 implements Protocol
{
    public function name(): string
    {
        return 'gt06';
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

        return ord($buffer[2]) === 13 && ord($buffer[3]) === 1;
    }

    public function extract(string &$buffer, bool $authenticated): ?string
    {
        return (new ConcoxX1)->extract($buffer, $authenticated);
    }

    public function decode(string $frame, bool $authenticated): Packet
    {
        if (strlen($frame) < 10 || substr($frame, 0, 2) !== "\x78\x78"
            || strlen($frame) !== ord($frame[2]) + 5 || substr($frame, -2) !== "\r\n"
            || unpack('n', substr($frame, -4, 2))[1] !== ConcoxX1::crc(substr($frame, 2, -4))) {
            throw new InvalidArgumentException('invalid_checksum_or_frame');
        }
        $type = ord($frame[3]);
        $payload = substr($frame, 4, -6);
        $serial = substr($frame, -6, 2);
        if ($type === 1) {
            if ($authenticated || strlen($payload) !== 8) {
                throw new InvalidArgumentException('unsupported_variant');
            }
            $bcd = bin2hex($payload);
            if (! preg_match('/^0[0-9]{15}$/D', $bcd)) {
                throw new InvalidArgumentException('invalid_identifier');
            }

            return new Packet('login', substr($bcd, 1), '1.8.1', $this->ack($type, $serial));
        }
        if (! $authenticated) {
            throw new InvalidArgumentException('login_required');
        }
        if ($type === 0x13) {
            if (strlen($payload) !== 5 || ord($payload[1]) > 6 || ord($payload[2]) > 4) {
                throw new InvalidArgumentException('unsupported_heartbeat_variant');
            }

            return new Packet('heartbeat', null, '1.8.1', $this->ack($type, $serial), events: [
                'acc' => (bool) (ord($payload[0]) & 2), 'power_level' => ord($payload[1]), 'gsm_level' => ord($payload[2]),
            ]);
        }
        if ($type !== 0x12 || strlen($payload) !== 26) {
            throw new InvalidArgumentException('unsupported_packet_'.sprintf('%02X', $type));
        }
        $r = new BinaryReader($payload);
        [$year, $month, $day, $hour, $minute, $second] = array_values(unpack('C6', $r->take(6)));
        if (! checkdate($month, $day, $year + 2000) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidArgumentException('invalid_timestamp');
        }
        $satellites = $r->number(1);
        if (($satellites >> 4) !== 12) {
            throw new InvalidArgumentException('unsupported_gps_length');
        }
        $latitude = $r->number(4) / 1800000;
        $longitude = $r->number(4) / 1800000;
        $speed = $r->number(1);
        $course = $r->number(2);
        if (! ($course & 0x400)) {
            $latitude = -$latitude;
        }
        if ($course & 0x800) {
            $longitude = -$longitude;
        }
        $telemetry = ['mcc' => $r->number(2), 'mnc' => $r->number(1), 'lac' => $r->number(2), 'cell_id_hex' => bin2hex($r->take(3))];

        return new Packet('location', null, '1.8.1', '', [new Location($latitude, $longitude,
            CarbonImmutable::create($year + 2000, $month, $day, $hour, $minute, $second, 'UTC'),
            $speed, $course & 1023, null, $satellites & 15, (bool) ($course & 0x1000), $telemetry)]);
    }

    private function ack(int $type, string $serial): string
    {
        $data = "\x05".chr($type).$serial;

        return "\x78\x78".$data.pack('n', ConcoxX1::crc($data))."\r\n";
    }
}
