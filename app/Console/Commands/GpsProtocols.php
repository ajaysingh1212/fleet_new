<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GpsProtocols extends Command
{
    protected $signature = 'gps:protocols';

    protected $description = 'Show exact implemented protocol versions and compatibility limits';

    public function handle(): int
    {
        $rows = [];
        foreach (config('gps.protocols') as $name => $info) {
            $rows[] = [$name, implode(', ', $info['versions']) ?: 'none', $info['status']];
        }
        $this->table(['Protocol', 'Versions', 'Verification'], $rows);
        $this->line('TG1 mobile: preserved separately via gps:listen-mobile / gps:listen --mobile. UDP and remote commands are not implemented.');

        return self::SUCCESS;
    }
}
