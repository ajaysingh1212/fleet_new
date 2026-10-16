<?php

namespace App\Services\Gps;

final readonly class OptimizerResult
{
    private function __construct(
        public bool $accepted,
        public ?string $reason,
    ) {}

    public static function accept(): self
    {
        return new self(true, null);
    }

    public static function reject(string $reason): self
    {
        return new self(false, $reason);
    }
}
