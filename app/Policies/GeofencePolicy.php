<?php

namespace App\Policies;

use App\Models\Geofence;
use App\Models\User;

class GeofencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['manage geofences', 'manage tracked geofences', 'use tracking workspace']);
    }

    public function view(User $user, Geofence $geofence): bool
    {
        return $user->can('manage geofences') || (int) $geofence->created_by === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->canAny(['manage geofences', 'manage tracked geofences', 'use tracking workspace']);
    }

    public function update(User $user, Geofence $geofence): bool
    {
        return $user->can('manage geofences') || (int) $geofence->created_by === (int) $user->id;
    }

    public function delete(User $user, Geofence $geofence): bool
    {
        return $user->can('manage geofences') || (int) $geofence->created_by === (int) $user->id;
    }
}
