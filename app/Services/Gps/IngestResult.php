<?php

namespace App\Services\Gps;

final readonly class IngestResult
{
    private function __construct(
        public bool $accepted,
        public ?string $reason,
        public int $deviceSessionId,
        public ?int $trackingSessionId,
        public ?string $trackingSessionUuid,
    ) {}

    public static function accepted(int $deviceSessionId, int $trackingSessionId, string $trackingSessionUuid): self
    {
        return new self(true, null, $deviceSessionId, $trackingSessionId, $trackingSessionUuid);
    }

    public static function rejected(int $deviceSessionId, string $reason): self
    {
        return new self(false, $reason, $deviceSessionId, null, null);
    }
}
