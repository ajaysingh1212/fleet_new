<?php

namespace App\Services\Gps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Short-lived, bounded diagnostic data; never written to SQL or log files. */
final class LiveDiagnosticStream
{
    private function store()
    {
        return Cache::store(config('gps.live_cache_store'));
    }

    private function key(?string $imei): string
    {
        return 'gps.live.diagnostics.'.($imei ?: 'all');
    }

    public function append(?string $imei, string $event, array $fields = [], string $severity = 'info'): void
    {
        $item = ['id' => (string) Str::uuid(), 'at' => HardwareTelemetry::stamp(), 'imei' => $imei,
            'event' => $event, 'severity' => $severity, 'fields' => $this->sanitize($fields)];
        foreach (array_unique([$this->key(null), $this->key($imei)]) as $key) {
            try {
                $this->store()->lock($key.'.lock', 3)->block(1, function () use ($key, $item): void {
                    $events = $this->store()->get($key, []);
                    $events[] = $item;
                    $limit = max(100, min(1000, (int) config('gps.live_diagnostic_buffer', 500)));
                    if (count($events) > $limit) {
                        $events = array_slice($events, -$limit);
                    }
                    // Cache expiry is intentional: this is a transient viewer,
                    // not a packet/archive store.
                    $this->store()->put($key, $events, now()->addMinutes(15));
                });
            } catch (\Throwable) {
                // Diagnostics must never delay acknowledgements or telemetry.
            }
        }
    }

    public function read(?string $imei, ?string $after = null): array
    {
        try {
            $events = $this->store()->get($this->key($imei), []);
        } catch (\Throwable) {
            return [];
        }
        if ($after) {
            $index = array_search($after, array_column($events, 'id'), true);
            if ($index !== false) {
                return array_slice($events, $index + 1);
            }
        }

        return array_slice($events, -max(100, min(1000, (int) config('gps.live_diagnostic_buffer', 500))));
    }

    private function sanitize(array $fields): array
    {
        $safe = [];
        foreach ($fields as $key => $value) {
            if (preg_match('/password|secret|token|authorization|imsi|iccid|phone/i', (string) $key)) {
                continue;
            }
            if (is_string($value)) {
                $safe[$key] = substr($value, 0, in_array($key, ['raw_hex', 'ack_hex'], true) ? 4096 : 1024);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
            }
        }

        return $safe;
    }
}
