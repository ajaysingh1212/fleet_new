<?php

namespace App\Events;

use App\Models\GpsLocation;
use Illuminate\Foundation\Events\Dispatchable;

class LocationStored
{
    use Dispatchable;

    public function __construct(
        public readonly GpsLocation $location,
    ) {}
}
