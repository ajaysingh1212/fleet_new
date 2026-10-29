<?php

namespace App\Services\Gps\Gateway\Protocols;

use InvalidArgumentException;

final class Pt06CommandEncoder
{
    public static function encode(string $command, int $flag, int $serial, bool $includeLanguage = false): string
    {
        if ($flag < 1 || $flag > 0xFFFFFFFF || $serial < 0 || $serial > 65535
            || strlen($command) > 180 || ! preg_match('/^[\x20-\x7e]+#$/D', $command)) {
            throw new InvalidArgumentException('Invalid PT06 command frame.');
        }
        // The tracker uses a legacy 13-byte login. Its matching PT06 command
        // fixture has no language field; the newer variant is still available
        // explicitly for firmware that documents it.
        $body = "\x80".chr(strlen($command) + 4).pack('N', $flag).$command
            .($includeLanguage ? "\x00\x02" : '').pack('n', $serial);
        $checked = chr(strlen($body) + 2).$body;

        return "\x78\x78".$checked.pack('n', ConcoxX1::crc($checked))."\r\n";
    }
}
