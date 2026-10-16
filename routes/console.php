<?php

use App\Models\DeviceSession;
use App\Services\UserPresenceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('geofence:evaluate-assignments')->everyFiveMinutes();
Schedule::command('tasks:purge-expired')->dailyAt('02:20')->withoutOverlapping();
Schedule::command('gps:sync-devices')->everyMinute()->withoutOverlapping();
Schedule::command('gps:purge')->dailyAt('02:40')->withoutOverlapping();

Artisan::command('presence:expire', function () {
    DeviceSession::where('is_current', true)->whereNull('logged_out_at')
        ->where('last_activity_at', '<', now()->subSeconds(90))
        ->select('user_id')->distinct()->get()->each(function ($session) {
            $presence = app(UserPresenceService::class);
            if (! $presence->isOnline($session->user_id)
                && Cache::add('presence.expired.'.$session->user_id, true, 30)) {
                $presence->broadcastChange($session->user_id);
            }
        });
})->purpose('Broadcast expired heartbeat presence to connected trackers');

Schedule::command('presence:expire')->everyTenSeconds()->withoutOverlapping();
