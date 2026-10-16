<?php

namespace App\Interfaces\Repositories;

use App\Models\UserLicense;

interface UserLicenseRepositoryInterface
{
    public function create(array $attributes): UserLicense;

    public function activeForUser(int $userId): ?UserLicense;
}
