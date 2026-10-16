<?php

namespace App\Events;

use App\DTO\LocationUpdateData;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Observability event fired once business-level checks (not just field
 * validation) pass and the packet is about to go through the optimizer.
 */
class LocationValidated
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly LocationUpdateData $location,
    ) {}
}
