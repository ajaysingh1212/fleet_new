<?php

namespace App\Events;

use App\Models\GpsLocation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the location has gone out over the WebSocket, for any future
 * listener that wants to react to "this point is now live" (analytics,
 * webhooks) without coupling to the broadcast itself.
 */
class LocationBroadcasted
{
    use Dispatchable;

    public function __construct(
        public readonly GpsLocation $location,
    ) {}
}
