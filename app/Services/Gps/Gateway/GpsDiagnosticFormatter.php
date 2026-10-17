<?php

namespace App\Services\Gps\Gateway;

final class GpsDiagnosticFormatter
{
    public function format(string $time, string $event, array $fields): string
    {
        $lines = ['', str_repeat('=', 76), '['.$time.'] '.str_replace('_', ' ', $event), str_repeat('-', 76)];
        foreach ($this->flatten($fields) as $key => $value) {
            $label = $this->label($key);
            if (in_array($key, ['hex', 'raw_hex', 'payload_hex'], true) && is_string($value)) {
                $lines[] = $label.':';
                foreach (str_split(implode(' ', str_split($value, 2)), 96) as $part) {
                    $lines[] = '  '.$part;
                }

                continue;
            }
            $lines[] = str_pad($label, 30).' : '.$this->value($key, $value);
        }
        if ($event === 'HEARTBEAT') {
            if (array_key_exists('gps_positioned_reported', $fields)) {
                $lines[] = 'GPS meaning                    : '.($fields['gps_positioned_reported'] ? 'Device reports positioned; this heartbeat has no coordinates.' : 'Device reports NO GPS FIX; this heartbeat has no coordinates.');
            }
            if (($fields['power_level'] ?? null) === 1) {
                $lines[] = 'Power meaning                  : EXTREMELY LOW (level 1/6); not a battery percentage';
            }
            if (($fields['gsm_level'] ?? null) === 4) {
                $lines[] = 'Mobile signal                  : STRONG (level 4/4); GSM signal is separate from GPS fix';
            }
        }
        if ($event === 'STATUS' && ($fields['locations'] ?? 0) === 0) {
            $lines[] = 'GPS location                   : Not received yet - coordinates unavailable';
        }
        if (in_array($event, ['HEARTBEAT', 'DEVICE_SIM_INFORMATION', 'DEVICE_SIM_INFORMATION_DETAILS', 'LBS_DETAILS', 'INFORMATION_DETAILS', 'UNSUPPORTED_DETAILS', 'COMMAND_RESPONSE_DETAILS'], true)) {
            $lines[] = 'Coordinates                    : Not included in this packet';
        }
        if ($event === 'GPS_LOCATION' && isset($fields['latitude'], $fields['longitude'])) {
            $lines[] = 'Map                            : https://maps.google.com/?q='.$fields['latitude'].','.$fields['longitude'];
        }
        if ($event === 'GPS_NO_FIX') {
            $lines[] = 'Location validity              : NO GPS FIX - reported coordinates are not a confirmed position';
        }
        $lines[] = str_repeat('=', 76);

        return implode(PHP_EOL, $lines);
    }

    private function flatten(array $fields, string $prefix = ''): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            $name = $prefix.$key;
            if (is_array($value)) {
                $result += $this->flatten($value, $name.'.');
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private function label(string $key): string
    {
        // Snapshot values have the same units and meanings as packet values.
        foreach (['device.' => '', 'last_reported_heartbeat.' => 'Last heartbeat / ', 'last_reported_sim.' => 'Last SIM / ', 'last_reported_position.' => 'Last position / ', 'telemetry.' => ''] as $prefix => $context) {
            if (str_starts_with($key, $prefix)) {
                return $context.$this->label(substr($key, strlen($prefix)));
            }
        }

        return match ($key) {
            'imei', 'reported_imei' => $key === 'imei' ? 'Device IMEI' : 'Packet IMEI',
            'peer' => 'Remote IP:port', 'type' => 'Protocol type', 'serial' => 'Serial number',
            'bytes' => 'Bytes', 'frame' => 'Frame header', 'checksum' => 'Checksum validation',
            'crc' => 'CRC-ITU (hex)', 'hex', 'raw_hex' => 'Raw packet HEX', 'payload_hex' => 'Payload HEX',
            'latitude' => 'Latitude', 'longitude' => 'Longitude', 'recorded_at' => 'GPS timestamp (UTC)',
            'speed' => 'Speed (km/h)', 'heading' => 'Direction (degrees)', 'altitude' => 'Altitude (metres)',
            'satellites' => 'Satellites', 'gps_fix' => 'GPS fix valid', 'acc' => 'ACC / ignition',
            'gps_tracking_enabled' => 'GPS tracking enabled', 'charging' => 'Charging', 'relay_disconnected' => 'Relay disconnected',
            'power_level' => 'Power level (0-6)', 'gsm_level' => 'GSM signal level (0-4)',
            'external_voltage' => 'External supply (volts)', 'gps_positioned_reported' => 'GPS positioned (status bit)',
            'power_description' => 'Power meaning', 'gsm_description' => 'GSM signal meaning',
            'terminal_alarm_type' => 'Terminal alarm', 'device_connected_now' => 'Device connected now',
            'telemetry.odometer_m', 'odometer_m' => 'Mileage counter (metres)',
            'imsi_encoded' => 'IMSI (encoded digits)', 'iccid_encoded' => 'SIM ICCID (encoded)',
            'connected_now' => 'Currently connected', 'connections' => 'Total TCP connections',
            'reads' => 'TCP reads', 'logins' => 'Login packets', 'heartbeats' => 'Heartbeat packets',
            'locations' => 'Location packets', 'valid_fixes' => 'Valid GPS fixes', 'last_data' => 'Last received data',
            default => ucfirst(str_replace(['_', '.'], [' ', ' / '], $key)),
        };
    }

    private function value(string $key, mixed $value): string
    {
        if ($value === null) {
            return 'Not provided';
        }
        if (is_bool($value)) {
            return $value ? 'Yes / ON' : 'No / OFF';
        }
        if (str_ends_with($key, 'latitude') || str_ends_with($key, 'longitude')) {
            return number_format((float) $value, 7, '.', '');
        }

        // Terminal payloads must never inject escape/control sequences.
        return preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $value);
    }
}
