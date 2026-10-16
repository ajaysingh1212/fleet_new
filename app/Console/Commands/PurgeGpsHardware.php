<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeGpsHardware extends Command
{
    protected $signature = 'gps:purge';

    protected $description = 'Delete expired hardware diagnostics, history, events and connection sessions in bounded batches';

    public function handle(): int
    {
        foreach (['gps_packet_diagnostics' => ['received_at', config('gps.diagnostic_days')],
            'gps_device_locations' => ['received_at', config('gps.location_days')],
            'gps_device_events' => ['received_at', config('gps.location_days')],
            'gps_connection_sessions' => ['last_packet_at', config('gps.location_days')]] as $table => [$column, $days]) {
            do {
                $query = DB::table($table)->where($column, '<', now()->subDays($days));
                if ($table === 'gps_device_locations') {
                    $query->whereNotIn('id', DB::table('gps_devices')->whereNotNull('latest_location_id')->select('latest_location_id'));
                }
                $ids = $query->limit(1000)->pluck('id');
                if ($ids->isNotEmpty()) {
                    DB::table($table)->whereIn('id', $ids)->delete();
                }
            } while ($ids->count() === 1000);
        }
        DB::table('gps_connection_sessions')->whereNull('disconnected_at')->where('last_packet_at', '<', now()->subSeconds(config('gps.idle_seconds')))
            ->update(['disconnected_at' => DB::raw('last_packet_at')]);
        $this->info('Hardware retention applied. Latest valid locations and assignment history retained.');

        return self::SUCCESS;
    }
}
