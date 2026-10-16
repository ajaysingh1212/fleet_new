<?php

namespace App\Listeners;

use App\Events\LocationStored;
use App\Services\Geofence\GeofenceEvaluationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class EvaluateGeofenceListener implements ShouldQueue
{
    public function handle(LocationStored $event): void
    {
        app(GeofenceEvaluationService::class)->evaluate($event->location);
    }
}
