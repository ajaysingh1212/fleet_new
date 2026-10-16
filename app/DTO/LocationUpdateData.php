<?php

namespace App\DTO;

use App\Enums\SourceType;
use App\Http\Requests\Api\V1\LocationUpdateRequest;
use Carbon\CarbonImmutable;

final readonly class LocationUpdateData
{
    public function __construct(
        public string $deviceId,
        public SourceType $sourceType,
        public float $latitude,
        public float $longitude,
        public ?float $accuracy,
        public ?float $speed,
        public ?float $bearing,
        public ?float $heading,
        public ?float $altitude,
        public ?int $batteryLevel,
        public ?string $networkType,
        public ?int $signalStrength,
        public ?string $provider,
        public bool $isMock,
        public CarbonImmutable $recordedAt,
        public ?string $trackingSessionUuid,
    ) {}

    public static function fromRequest(LocationUpdateRequest $request): self
    {
        $data = $request->validated();
        $data['device_id'] ??= $request->hasSession() ? $request->session()->getId() : null;

        return self::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data  Already-validated packet fields (a single
     *                                       item's slice of a batch, or a whole request).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deviceId: (string) ($data['device_id'] ?? ''),
            sourceType: SourceType::from($data['source_type']),
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            accuracy: isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            speed: isset($data['speed']) ? (float) $data['speed'] : null,
            bearing: isset($data['bearing']) ? (float) $data['bearing'] : null,
            heading: isset($data['heading']) ? (float) $data['heading'] : null,
            altitude: isset($data['altitude']) ? (float) $data['altitude'] : null,
            batteryLevel: isset($data['battery_level']) ? (int) $data['battery_level'] : null,
            networkType: $data['network_type'] ?? null,
            signalStrength: isset($data['signal_strength']) ? (int) $data['signal_strength'] : null,
            provider: $data['provider'] ?? null,
            isMock: (bool) ($data['is_mock'] ?? false),
            recordedAt: CarbonImmutable::parse($data['recorded_at']),
            trackingSessionUuid: $data['tracking_session_id'] ?? null,
        );
    }
}
