<?php

namespace App\Services\Gps\Gateway;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class Location
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public CarbonImmutable $recordedAt,
        public ?float $speed = null, // km/h (hardware dashboard only)
        public ?float $heading = null,
        public ?float $altitude = null,
        public ?int $satellites = null,
        public bool $gpsFix = false,
        public array $telemetry = [],
    ) {
        if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180
            || $recordedAt->year < 2000 || $recordedAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))
            || ($speed !== null && (! is_finite($speed) || $speed < 0 || $speed > 1000))
            || ($heading !== null && ($heading < 0 || $heading > 360))) {
            throw new InvalidArgumentException('invalid_location');
        }
    }

    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude,
            'recorded_at' => $this->recordedAt->utc()->format('Y-m-d H:i:s'), 'speed' => $this->speed,
            'heading' => $this->heading, 'altitude' => $this->altitude, 'satellites' => $this->satellites,
            'gps_fix' => $this->gpsFix, 'telemetry' => $this->telemetry];
    }
}
