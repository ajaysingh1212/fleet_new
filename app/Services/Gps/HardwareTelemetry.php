<?php

namespace App\Services\Gps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class HardwareTelemetry
{
    public static function stamp(): string
    {
        return now()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    public static function phase(string $phase, array $fields): void
    {
        if (! config('gps.trace_enabled')) {
            return;
        }
        // This log contains correlation metadata only, never packet bodies,
        // coordinates, SIM identifiers, credentials or arbitrary exceptions.
        $safe = array_intersect_key($fields, array_flip(['trace_id', 'device_id', 'session_id', 'type',
            'bytes', 'received_at', 'parsed_at', 'duration_ms', 'queue_delay_ms', 'recorded_at',
            'gps_valid', 'new_location', 'reason', 'broadcast_at']));
        try {
            Log::channel('gps_pipeline')->info($phase, ['at' => self::stamp(), ...$safe]);
        } catch (\Throwable) { /* Telemetry failure must not stop ingestion. */
        }
    }

    public static function receiver(int $port, int $connections, int $authenticated): void
    {
        try {
            Cache::store(config('gps.live_cache_store'))->put('gps.receiver.health', [
                'pid' => getmypid(), 'port' => $port, 'active_connections' => $connections,
                'authenticated_connections' => $authenticated, 'updated_at' => self::stamp(),
            ], 15);
        } catch (\Throwable) {
        }
    }

    public static function redact(array $fields): array
    {
        foreach ($fields as $key => $value) {
            if (preg_match('/password|secret|token|authorization|imsi|iccid|phone|raw|payload|response|command|^hex$|last_reported_sim/i', (string) $key)) {
                unset($fields[$key]);
            } elseif (is_array($value)) {
                $fields[$key] = self::redact($value);
            }
        }

        return $fields;
    }
}
