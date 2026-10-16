<?php

namespace App\Console\Commands;

use App\Models\GpsDevice;
use App\Services\Gps\Gateway\DeviceAllowlist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Throwable;

class GpsOperations extends Command
{
    protected $signature = 'gps:status';

    protected $description = 'Check hardware gateway heartbeat, private database, Redis queue and device status';

    public function handle(DeviceAllowlist $allowlist): int
    {
        try {
            $heartbeat = $allowlist->store()->get('gps.hardware.health');
            $this->line('Gateway heartbeat: '.($heartbeat && time() - $heartbeat < 15 ? 'healthy' : 'missing/stale'));
            $this->line('Queue depth: '.Queue::connection(config('gps.queue_connection'))->size(config('gps.queue')));
            $this->line('Registered hardware devices: '.GpsDevice::count());
            $this->line('Online devices: '.GpsDevice::where('status', 'active')->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds')))->count());
            if (! $heartbeat || time() - $heartbeat >= 15) {
                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('GPS health check failed. Verify migrations, MySQL and Redis connectivity.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
