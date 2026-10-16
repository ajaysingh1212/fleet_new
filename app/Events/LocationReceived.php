<?php

namespace App\Events;

use App\DTO\LocationUpdateData;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Observability event fired the moment a packet clears field validation.
 * Does not drive control flow — see the Increment 2 plan's "event chain,
 * adjusted for HTTP reality" note.
 */
class LocationReceived
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly LocationUpdateData $location,
    ) {}
}
