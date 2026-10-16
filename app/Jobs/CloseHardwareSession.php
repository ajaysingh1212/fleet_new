<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CloseHardwareSession implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $session) {}

    public function handle(): void
    {
        // Close can be consumed before the login packet by another worker.
        // Insert the terminal state first; later packets never clear it.
        DB::table('gps_connection_sessions')->insertOrIgnore($this->session);
        DB::table('gps_connection_sessions')->where('id', $this->session['id'])
            ->update(['disconnected_at' => $this->session['disconnected_at']]);
    }
}
