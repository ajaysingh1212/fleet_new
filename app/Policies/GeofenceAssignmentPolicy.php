<?php

namespace App\Policies;

use App\Models\GeofenceAssignment;
use App\Models\User;

class GeofenceAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['manage geofences', 'manage tracked geofences', 'use tracking workspace']);
    }

    public function view(User $user, GeofenceAssignment $geofenceAssignment): bool
    {
        return $user->can('manage geofences')
            || $user->id === $geofenceAssignment->user_id
            || $user->id === $geofenceAssignment->assigned_by;
    }

    public function create(User $user): bool
    {
        return $user->canAny(['manage geofences', 'manage tracked geofences', 'use tracking workspace']);
    }

    public function update(User $user, GeofenceAssignment $geofenceAssignment): bool
    {
        return $user->can('manage geofences') || $user->id === $geofenceAssignment->assigned_by;
    }

    public function delete(User $user, GeofenceAssignment $geofenceAssignment): bool
    {
        return $user->can('manage geofences') || $user->id === $geofenceAssignment->assigned_by;
    }
}
