<?php

namespace App\Repositories;

use App\Interfaces\Repositories\LicensePlanRepositoryInterface;
use App\Models\LicensePlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LicensePlanRepository implements LicensePlanRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return LicensePlan::query()->orderBy('display_order')->paginate($perPage);
    }

    public function create(array $attributes): LicensePlan
    {
        return LicensePlan::create($attributes);
    }
}
