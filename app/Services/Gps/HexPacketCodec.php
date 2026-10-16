<?php

namespace App\Services\Gps;

use InvalidArgumentException;

class HexPacketCodec
{
    public const MAX_HEX_LENGTH = 32792;

    public function encode(int $code, array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 16384 || $code < 0 || $code > 255) {
            throw new InvalidArgumentException('packet_size');
        }
        $body = 'TG'.chr(1).chr($code).pack('N', strlen($json)).$json;

        return strtoupper(bin2hex($body.hash('crc32b', $body, true))).chr(10);
    }

    public function decode(string $hex): array
    {
        $hex = rtrim($hex, chr(13).chr(10));
        if (strlen($hex) > self::MAX_HEX_LENGTH || strlen($hex) < 24
            || strlen($hex) % 2 !== 0 || ! ctype_xdigit($hex)) {
            throw new InvalidArgumentException('invalid_hex');
        }
        $bytes = hex2bin($hex);
        if (substr($bytes, 0, 3) !== 'TG'.chr(1)) {
            throw new InvalidArgumentException('invalid_header');
        }
        $length = unpack('Nlength', substr($bytes, 4, 4))['length'];
        if ($length > 16384 || strlen($bytes) !== 12 + $length) {
            throw new InvalidArgumentException('invalid_length');
        }
        $body = substr($bytes, 0, -4);
        if (! hash_equals(hash('crc32b', $body, true), substr($bytes, -4))) {
            throw new InvalidArgumentException('invalid_checksum');
        }
        $payload = json_decode(substr($bytes, 8, $length), true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('invalid_payload');
        }

        return ['code' => ord($bytes[3]), 'payload' => $payload];
    }
}
