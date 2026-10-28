<?php

namespace App\Services\Gps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Receiver-owned presence. Queued history/replayed GPS cannot reopen a socket. */
final class HardwareConnectionState
{
    public function touch(int $deviceId, string $sessionId): void
    {
        $this->change($deviceId, $sessionId, true);
    }

    public function close(int $deviceId, string $sessionId): void
    {
        $this->change($deviceId, $sessionId, false);
    }

    public function keepAlive(int $deviceId, string $sessionId): void
    {
        $this->change($deviceId, $sessionId, true, false);
    }

    private function change(int $deviceId, string $sessionId, bool $connected, bool $packet = true): void
    {
        $store = Cache::store(config('gps.live_cache_store'));
        $key = 'gps.connections.'.$deviceId;
        $store->lock($key.'.lock', 10)->block(3, function () use ($store, $key, $sessionId, $connected, $packet) {
            $state = $store->get($key, ['sessions' => []]);
            $state['sessions'] = array_filter($state['sessions'], fn ($time) => $time >= now()->timestamp - config('gps.idle_seconds'));
            if ($connected) {
                $state['sessions'][$sessionId] = now()->timestamp;
            } else {
                unset($state['sessions'][$sessionId]);
            }
            if ($connected && $packet) {
                $state['last_packet_at'] = now()->utc()->format('Y-m-d\TH:i:s.u\Z');
            }
            $state['observed_at'] = now()->utc()->format('Y-m-d\TH:i:s.u\Z');
            $store->put($key, $state, now()->addDay());
        });
    }

    public function snapshot(int $deviceId): array
    {
        $state = Cache::store(config('gps.live_cache_store'))->get('gps.connections.'.$deviceId);
        if ($state !== null) {
            return ['connection_connected' => (bool) array_filter($state['sessions'], fn ($time) => $time >= now()->timestamp - config('gps.idle_seconds')),
                'connection_observed_at' => $state['observed_at'], 'connection_last_packet_at' => $state['last_packet_at'] ?? null];
        }
        // Existing installations have persisted sessions but no presence cache.
        // Seed a read result only; never let delayed SQL overwrite receiver state.
        $sessions = DB::table('gps_connection_sessions')->where('gps_device_id', $deviceId)
            ->selectRaw('COUNT(*) as total, MAX(CASE WHEN disconnected_at IS NULL AND last_packet_at >= ? THEN 1 ELSE 0 END) as connected',
                [now()->utc()->subSeconds(config('gps.idle_seconds'))->format('Y-m-d H:i:s')])->first();

        return $sessions->total ? ['connection_connected' => (bool) $sessions->connected,
            'connection_observed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z')] : [];
    }
}
