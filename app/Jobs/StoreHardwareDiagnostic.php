<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class StoreHardwareDiagnostic implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $metadata) {}

    public function handle(): void
    {
        DB::table('gps_packet_diagnostics')->insert($this->metadata);
    }
}
