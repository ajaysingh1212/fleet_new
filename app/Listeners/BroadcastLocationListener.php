<?php

namespace App\Listeners;

use App\Events\GpsLocationUpdated;
use App\Events\LocationBroadcasted;
use App\Events\LocationStored;

class BroadcastLocationListener
{
    public function handle(LocationStored $event): void
    {
        broadcast(new GpsLocationUpdated($event->location));

        LocationBroadcasted::dispatch($event->location);
    }
}
