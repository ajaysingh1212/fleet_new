<?php

namespace App\Policies;

use App\Models\TrackingRelation;
use App\Models\User;

class TrackingRelationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage tracking relations');
    }

    public function view(User $user, TrackingRelation $trackingRelation): bool
    {
        return $user->can('manage tracking relations')
            || in_array($user->id, [$trackingRelation->tracker_user_id, $trackingRelation->tracked_user_id], true);
    }

    public function create(User $user): bool
    {
        return $user->can('manage tracking relations');
    }

    public function update(User $user, TrackingRelation $trackingRelation): bool
    {
        return $user->can('manage tracking relations');
    }

    public function delete(User $user, TrackingRelation $trackingRelation): bool
    {
        return $user->can('manage tracking relations');
    }
}
