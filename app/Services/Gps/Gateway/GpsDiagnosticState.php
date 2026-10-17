<?php

namespace App\Services\Gps\Gateway;

/** Last reported observations for the single explicitly allowlisted device. */
final class GpsDiagnosticState
{
    private ?string $imei = null;

    private array $counts = [];

    private array $heartbeat = [];

    private array $sim = [];

    private ?array $position = null;

    private ?string $lastReport = null;

    public function observe(Packet $packet, string $imei, string $receivedAt): void
    {
        $this->imei = $imei;
        $this->lastReport = $receivedAt;
        $this->counts[$packet->type] = ($this->counts[$packet->type] ?? 0) + 1;
        if ($packet->type === 'heartbeat') {
            $this->heartbeat = ['received_at' => $receivedAt, ...$packet->events];
        }
        if ($packet->type === 'device_sim_information') {
            $this->sim = array_intersect_key($packet->events, array_flip(['reported_imei', 'imsi_encoded', 'iccid_encoded', 'door_open', 'air_conditioning_on']));
        }
        foreach ($packet->locations as $location) {
            $this->position = $location->toArray();
        }
    }

    public function snapshot(bool $connected): array
    {
        $reason = $this->position === null
            ? 'No coordinate-bearing packet received. Login, heartbeat and SIM packets contain no latitude/longitude.'
            : ($this->position['gps_fix'] ? 'Last reported GPS fix available; see GPS timestamp for age.' : 'Coordinates received but device reports NO GPS FIX.');
        if ($this->position === null && ($this->heartbeat['gps_positioned_reported'] ?? null) === false) {
            $reason .= ' Last heartbeat reports GPS NOT positioned.';
        }

        return ['imei' => $this->imei, 'device_connected_now' => $connected,
            'last_device_report' => $this->lastReport, 'packet_counts' => $this->counts,
            'location_status' => $reason, 'last_reported_heartbeat' => $this->heartbeat,
            'last_reported_sim' => $this->sim, 'last_reported_position' => $this->position];
    }
}
