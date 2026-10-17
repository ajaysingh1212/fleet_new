<?php

namespace App\Services\Gps\Gateway;

use App\Services\Gps\Gateway\Protocols\ConcoxX1;
use InvalidArgumentException;

/** Shared CRC-checked short/extended framing; adapters decide payload semantics. */
final class Gt06DiagnosticFrames
{
    public function extract(string &$buffer): ?string
    {
        if (strlen($buffer) < 2) {
            return null;
        }
        $magic = substr($buffer, 0, 2);
        $width = match ($magic) {
            "\x78\x78" => 1,
            "\x79\x79" => 2,
            default => throw new InvalidArgumentException('unknown_framing'),
        };
        if (strlen($buffer) < 2 + $width) {
            return null;
        }
        $size = $width === 1 ? ord($buffer[2]) : unpack('n', substr($buffer, 2, 2))[1];
        if ($size < 5 || $size > 4096) {
            throw new InvalidArgumentException('invalid_length');
        }
        $total = $size + $width + 4;
        if (strlen($buffer) < $total) {
            return null;
        }
        $frame = substr($buffer, 0, $total);
        $buffer = substr($buffer, $total);
        if (substr($frame, -2) !== "\r\n" || unpack('n', substr($frame, -4, 2))[1] !== ConcoxX1::crc(substr($frame, 2, -4))) {
            throw new InvalidArgumentException('invalid_checksum_or_frame');
        }

        return $frame;
    }

    public function type(string $frame): string
    {
        return sprintf('%02X', ord($frame[substr($frame, 0, 2) === "\x79\x79" ? 4 : 3]));
    }
}
