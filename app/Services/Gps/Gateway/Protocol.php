<?php

namespace App\Services\Gps\Gateway;

interface Protocol
{
    public function name(): string;

    // null = still ambiguous / incomplete; false = does not match.
    public function detect(string $buffer): ?bool;

    // Consume exactly one validated-length frame, or return null for fragments.
    public function extract(string &$buffer, bool $authenticated): ?string;

    public function decode(string $frame, bool $authenticated): Packet;
}
