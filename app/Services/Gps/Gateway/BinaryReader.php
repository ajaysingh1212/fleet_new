<?php

namespace App\Services\Gps\Gateway;

use InvalidArgumentException;

final class BinaryReader
{
    public int $offset = 0;

    public function __construct(public readonly string $bytes) {}

    public function take(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->bytes)) {
            throw new InvalidArgumentException('truncated_packet');
        }
        $value = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;

        return $value;
    }

    public function number(int $width): int
    {
        return match ($width) {
            1 => ord($this->take(1)),
            2 => unpack('n', $this->take(2))[1],
            4 => unpack('N', $this->take(4))[1],
            8 => unpack('J', $this->take(8))[1],
            default => throw new InvalidArgumentException('invalid_width'),
        };
    }

    public function signed32(): int
    {
        $n = $this->number(4);

        return $n >= 0x80000000 ? $n - 0x100000000 : $n;
    }
}
