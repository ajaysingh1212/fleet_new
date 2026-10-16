<?php

namespace App\Services\Gps\Gateway\Protocols;

use App\Services\Gps\Gateway\BinaryReader;
use App\Services\Gps\Gateway\Location;
use App\Services\Gps\Gateway\Packet;
use App\Services\Gps\Gateway\Protocol;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class Teltonika implements Protocol
{
    public function name(): string
    {
        return 'teltonika';
    }

    public function detect(string $buffer): ?bool
    {
        if (strlen($buffer) < 2) {
            return null;
        }

        return substr($buffer, 0, 2) === "\x00\x0f";
    }

    public function extract(string &$buffer, bool $authenticated): ?string
    {
        if (! $authenticated) {
            if (strlen($buffer) < 2) {
                return null;
            }
            if (substr($buffer, 0, 2) !== "\x00\x0f") {
                throw new InvalidArgumentException('invalid_login');
            }
            $length = 17;
        } else {
            if (strlen($buffer) < 8) {
                return null;
            }
            if (substr($buffer, 0, 4) !== str_repeat("\0", 4)) {
                throw new InvalidArgumentException('invalid_preamble');
            }
            $size = unpack('N', substr($buffer, 4, 4))[1];
            if ($size < 3 || $size > config('gps.max_frame')) {
                throw new InvalidArgumentException('invalid_length');
            }
            $length = 12 + $size;
        }
        if (strlen($buffer) < $length) {
            return null;
        }
        $frame = substr($buffer, 0, $length);
        $buffer = substr($buffer, $length);

        return $frame;
    }

    public static function crc(string $data): int
    {
        $crc = 0;
        foreach (unpack('C*', $data) as $byte) {
            $crc ^= $byte;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 1) ? ($crc >> 1) ^ 0xA001 : $crc >> 1;
            }
        }

        return $crc;
    }

    public function decode(string $frame, bool $authenticated): Packet
    {
        if (! $authenticated) {
            $imei = substr($frame, 2);
            if (strlen($frame) !== 17 || ! preg_match('/^[0-9]{15}$/D', $imei)) {
                throw new InvalidArgumentException('invalid_identifier');
            }

            return new Packet('login', $imei, 'login', "\x01");
        }
        $data = substr($frame, 8, -4);
        if (unpack('N', substr($frame, -4))[1] !== self::crc($data)) {
            throw new InvalidArgumentException('invalid_checksum');
        }
        $r = new BinaryReader($data);
        $codec = $r->number(1);
        if (! in_array($codec, [8, 0x8E], true)) {
            throw new InvalidArgumentException('unsupported_version');
        }
        $width = $codec === 8 ? 1 : 2;
        $count = $r->number(1);
        if ($count < 1 || $count > 128) {
            throw new InvalidArgumentException('invalid_count');
        }
        $locations = [];
        for ($i = 0; $i < $count; $i++) {
            $timestamp = $r->number(8);
            $priority = $r->number(1);
            if ($priority > 2) {
                throw new InvalidArgumentException('invalid_priority');
            }
            $lon = $r->signed32() / 10000000;
            $lat = $r->signed32() / 10000000;
            $altitude = $r->number(2);
            if ($altitude >= 32768) {
                $altitude -= 65536;
            }
            $heading = $r->number(2);
            $satellites = $r->number(1);
            $speed = $r->number(2);
            $eventId = $r->number($width);
            $total = $r->number($width);
            $seen = 0;
            // IO meanings vary by exact model. Preserve bounded hex values;
            // never assume an IO ID means fuel/ignition for every model.
            $io = [];
            foreach ([1, 2, 4, 8] as $size) {
                $n = $r->number($width);
                for ($j = 0; $j < $n; $j++) {
                    $id = $r->number($width);
                    if (isset($io[$id])) {
                        throw new InvalidArgumentException('duplicate_io');
                    }
                    $io[$id] = bin2hex($r->take($size));
                    $seen++;
                }
            }
            if ($codec === 0x8E) {
                $n = $r->number(2);
                for ($j = 0; $j < $n; $j++) {
                    $id = $r->number(2);
                    if (isset($io[$id])) {
                        throw new InvalidArgumentException('duplicate_io');
                    }
                    $io[$id] = bin2hex($r->take($r->number(2)));
                    $seen++;
                }
            }
            if ($seen !== $total) {
                throw new InvalidArgumentException('invalid_io_count');
            }
            $locations[] = new Location($lat, $lon, CarbonImmutable::createFromTimestampMs($timestamp, 'UTC'),
                $speed, $heading, $altitude, $satellites, $satellites > 0,
                ['event_id' => $eventId, 'priority' => $priority, 'io_hex' => $io]);
        }
        if ($r->number(1) !== $count || $r->offset !== strlen($data)) {
            throw new InvalidArgumentException('invalid_count');
        }

        return new Packet('location', null, $codec === 8 ? '8' : '8E', pack('N', $count), $locations);
    }
}
