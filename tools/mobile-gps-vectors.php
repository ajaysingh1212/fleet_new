<?php

require __DIR__.'/../vendor/autoload.php';

use App\Services\Gps\HexPacketCodec;

$codec = new HexPacketCodec;
$common = [
    'imei' => '123456789012345',
    'license_number' => 'DEMO-LICENSE',
    'token' => 'DEMO-TOKEN-NOT-A-REAL-CREDENTIAL',
    'packet_id' => '2d222222-3333-4444-8555-666666666666',
    'recorded_at' => '2026-10-03T12:00:00Z',
];
$vectors = [];
foreach ([1 => 'handshake', 2 => 'location', 3 => 'heartbeat', 4 => 'logout'] as $code => $name) {
    $payload = $common;
    $payload['packet_id'] = sprintf('2d222222-3333-4444-8555-%012d', $code);
    if ($code === 2) {
        $payload = [...$payload, 'source_type' => 'android', 'latitude' => 28.6139,
            'longitude' => 77.209, 'accuracy' => 8, 'speed' => 4.2, 'bearing' => 90,
            'heading' => 90, 'altitude' => 220, 'battery_level' => 85,
            'network_type' => '4g', 'signal_strength' => 70, 'provider' => 'gps', 'is_mock' => false];
    } elseif ($code !== 4) {
        $payload = [...$payload, 'battery_level' => 85, 'network_type' => '4g', 'is_gps_enabled' => true];
    }
    $line = $codec->encode($code, $payload);
    $hex = rtrim($line);
    $vectors[$name] = [
        'code' => sprintf('%02X', $code), 'json' => $payload,
        'payload_bytes' => (strlen($hex) / 2) - 12,
        'header_hex' => substr($hex, 0, 16),
        'crc32_hex' => substr($hex, -8),
        'hex_without_lf' => $hex,
        'roundtrip_ok' => $codec->decode($line)['payload'] === $payload,
    ];
}
echo json_encode($vectors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
