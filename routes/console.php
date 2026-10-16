<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('geofence:evaluate-assignments')->everyFiveMinutes();
Schedule::command('tasks:purge-expired')->dailyAt('02:20')->withoutOverlapping();

Artisan::command('presence:expire', function () {
    \App\Models\DeviceSession::where('is_current', true)->whereNull('logged_out_at')
        ->where('last_activity_at', '<', now()->subSeconds(90))
        ->select('user_id')->distinct()->get()->each(function ($session) {
            $presence = app(\App\Services\UserPresenceService::class);
            if (! $presence->isOnline($session->user_id)
                && \Illuminate\Support\Facades\Cache::add('presence.expired.'.$session->user_id, true, 30)) {
                $presence->broadcastChange($session->user_id);
            }
        });
})->purpose('Broadcast expired heartbeat presence to connected trackers');

Schedule::command('presence:expire')->everyTenSeconds()->withoutOverlapping();
