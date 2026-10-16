<?php

namespace App\Services\Gps\Gateway;

final readonly class Packet
{
    /** @param array<Location> $locations */
    public function __construct(
        public string $type,
        public ?string $identifier,
        public string $version,
        public string $ack = '',
        public array $locations = [],
        public array $events = [],
    ) {}
}
