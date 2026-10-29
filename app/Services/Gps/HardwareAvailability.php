<?php

namespace App\Services\Gps;

/**
 * Availability is communication evidence, not GPS movement or power state.
 * Device configuration can lengthen the grace window for slow reporters but
 * can never make a silent device appear online indefinitely.
 */
final class HardwareAvailability
{
    public function seconds(?array $configuration = null): int
    {
        $minimum = max(30, (int) config('gps.idle_seconds', 180));
        $grace = max(0, (int) config('gps.offline_grace_seconds', 30));
        $intervals = [];
        foreach (['heartbeat_interval', 'reporting_interval'] as $key) {
            $value = $configuration[$key] ?? null;
            if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit($value))) {
                $seconds = (int) $value;
                if ($seconds >= 5 && $seconds <= 3600) {
                    $intervals[] = $seconds;
                }
            }
        }

        // Three missed expected reports plus a small network grace avoids a
        // rapid Online/Offline flip for trackers that intentionally sleep.
        return $intervals ? max($minimum, min(7200, (max($intervals) * 3) + $grace)) : $minimum;
    }
}
