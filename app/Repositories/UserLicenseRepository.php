<?php

namespace App\Repositories;

use App\Enums\LicenseStatus;
use App\Interfaces\Repositories\UserLicenseRepositoryInterface;
use App\Models\UserLicense;

class UserLicenseRepository implements UserLicenseRepositoryInterface
{
    public function create(array $attributes): UserLicense
    {
        return UserLicense::create($attributes);
    }

    public function activeForUser(int $userId): ?UserLicense
    {
        return UserLicense::query()
            ->where('user_id', $userId)
            ->where('status', LicenseStatus::Active)
            ->latest('expiry_date')
            ->first();
    }
}
