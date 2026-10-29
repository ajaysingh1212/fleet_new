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
        $this->line('TG1 mobile: preserved separately via gps:listen-mobile / gps:listen --mobile. UDP is not implemented. PT06 commands are available through the local persistent diagnostic receiver; see docs/GPS-DEVICE-COMMANDS.md.');

        return self::SUCCESS;
    }
}
