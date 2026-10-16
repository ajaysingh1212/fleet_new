<?php

namespace App\Console\Commands;

use App\Models\GpsDevice;
use App\Services\Gps\Gateway\DeviceAllowlist;
use Illuminate\Console\Command;
use Throwable;

class SyncGpsDevices extends Command
{
    protected $signature = 'gps:sync-devices';

    protected $description = 'Refresh the Redis hardware allowlist from current database registrations (scheduled every minute)';

    public function handle(DeviceAllowlist $allowlist): int
    {
        try {
            GpsDevice::chunkById(200, function ($devices) use ($allowlist) {
                foreach ($devices as $device) {
                    $allowlist->syncCurrent($device->id);
                }
            });
        } catch (Throwable) {
            $this->error('Allowlist synchronization failed; check MySQL and Redis.');

            return self::FAILURE;
        }
        $this->info('Hardware allowlist refreshed; entries expire after 24 hours without refresh.');

        return self::SUCCESS;
    }
}
