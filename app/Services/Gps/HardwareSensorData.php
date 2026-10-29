<?php

namespace App\Services\Gps;

final class HardwareSensorData
{
    // Only decoded measurements may reach browsers; raw packets and arbitrary
    // protocol extension fields remain internal to the gateway/history.
    public static function forDisplay(array $telemetry): array
    {
        return array_intersect_key($telemetry, array_flip([
            'acc', 'external_power', 'external_voltage', 'battery_voltage', 'power_level', 'gsm_signal',
            'gsm_signal_level', 'odometer_m', 'gps_positioned_reported', 'charging',
            'gsm_level', 'gsm_description', 'power_description', 'relay_disconnected',
            'defense_active', 'sos', 'alarm_code',
            'gps_retransmission',
            'defense', 'oil_electricity_disconnected', 'terminal_alarm_type',
            'alarm', 'alarm_type', 'temperature', 'fuel_level', 'door_open',
        ]));
    }
}
