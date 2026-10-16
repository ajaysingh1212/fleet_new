<?php

namespace App\Policies;

use App\Models\LicensePlan;
use App\Models\User;

class LicensePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage license plans');
    }

    public function view(User $user, LicensePlan $licensePlan): bool
    {
        return $user->can('manage license plans');
    }

    public function create(User $user): bool
    {
        return $user->can('manage license plans');
    }

    public function update(User $user, LicensePlan $licensePlan): bool
    {
        return $user->can('manage license plans');
    }

    public function delete(User $user, LicensePlan $licensePlan): bool
    {
        return $user->can('manage license plans') && ! $licensePlan->userLicenses()->exists();
    }
}
