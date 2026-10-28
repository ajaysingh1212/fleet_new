<?php

namespace App\Console\Commands;

use App\Models\GpsDevice;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareLiveState;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class GpsHealth extends Command
{
    protected $signature = 'gps:health {--imei=} {--probe-broadcast : Send an empty diagnostic to an isolated private health channel}';

    protected $description = 'Read bounded GPS receiver, queue, latest-state and latency diagnostics without secrets';

    public function handle(): int
    {
        if ($this->option('imei') && ! preg_match('/^[0-9]{15}$/D', $this->option('imei'))) {
            $this->error('IMEI must be 15 digits.');

            return self::FAILURE;
        }
        $result = ['at' => now()->utc()->toIso8601String(), 'last_seen_seconds' => config('gps.idle_seconds'),
            'socket_idle_seconds' => config('gps.socket_idle_seconds'), 'php_pcntl' => extension_loaded('pcntl'),
            'latest_cache_driver' => config('cache.stores.'.config('gps.live_cache_store').'.driver')];
        try {
            $health = Cache::store(config('gps.live_cache_store'))->get('gps.receiver.health');
            $result['receiver'] = ['heartbeat_fresh' => $health && CarbonImmutable::parse($health['updated_at'])->gte(now()->subSeconds(15)), ...($health ?? [])];
        } catch (\Throwable) {
            $result['receiver'] = ['available' => false];
        }
        $result['queues'] = [];
        foreach ([[config('gps.live_connection'), config('gps.live_queue')],
            [config('gps.live_connection'), config('gps.persistence_queue')],
            [config('gps.broadcast_connection'), config('gps.broadcast_queue')]] as [$connection, $queue]) {
            try {
                $row = ['queue' => $queue, 'connection' => $connection, 'total' => Queue::connection($connection)->size($queue)];
                if (config('queue.connections.'.$connection.'.driver') === 'database') {
                    $c = config('queue.connections.'.$connection);
                    $q = DB::connection($c['connection'] ?? null)->table($c['table'] ?? 'jobs')->where('queue', $queue);
                    $row['reserved'] = (clone $q)->whereNotNull('reserved_at')->count();
                    $row['pending'] = (clone $q)->whereNull('reserved_at')->count();
                    $oldest = (clone $q)->min('created_at');
                    $row['oldest_seconds'] = $oldest ? time() - $oldest : 0;
                }
                $result['queues'][] = $row;
            } catch (\Throwable) {
                $result['queues'][] = ['queue' => $queue, 'available' => false];
            }
        }
        try {
            $result['failed_gps_jobs'] = DB::table('failed_jobs')->whereIn('queue', ['gps-live', 'gps-persist', 'gps-broadcasts', 'gps-hardware'])->count();
            $result['devices'] = GpsDevice::with(['vehicle', 'latestLocation', 'latestHeartbeat'])
                ->when($this->option('imei'), fn ($q) => $q->where('identifier', $this->option('imei')))->orderBy('id')->limit(25)->get()
                ->map(function ($device) {
                    $p = app(HardwareLiveState::class)->overlay(app(HardwareFleet::class)->device($device));

                    return ['stored_last_packet_at' => $device->last_packet_at?->utc()->toIso8601String(),
                        'stored_gps_at' => $device->latestLocation?->recorded_at?->utc()->toIso8601String(),
                        'stored_updated_at' => $device->updated_at?->utc()->toIso8601String(),
                        ...array_intersect_key($p, array_flip(['device_id', 'imei', 'online', 'state', 'connection_connected',
                            'last_packet_at', 'heartbeat_reported_at', 'recorded_at', 'received_at', 'projected_at', 'queue_delay_ms', 'trace_id', 'gps_fresh']))];
                });
        } catch (\Throwable) {
            $result['database_available'] = false;
        }
        $pool = storage_path('framework/gps-workers-status.json');
        $result['worker_pool'] = is_file($pool) ? json_decode(file_get_contents($pool), true) : null;
        $result['broadcast_probe'] = 'not_requested';
        if ($this->option('probe-broadcast')) {
            try {
                $driver = app(Factory::class)->connection();
                if (! method_exists($driver, 'getPusher')) {
                    throw new \RuntimeException('Not a Pusher-compatible transport');
                }
                $driver->getPusher()->trigger('private-gps-health-'.bin2hex(random_bytes(8)), 'gps.health', ['probe' => true]);
                $result['broadcast_probe'] = 'publish_accepted';
            } catch (\Throwable) {
                $result['broadcast_probe'] = 'unavailable';
            }
        }
        $result['browser_measurements'] = 'Client-only: window.__gpsPipelineMetrics; timings require synchronized clocks.';
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
