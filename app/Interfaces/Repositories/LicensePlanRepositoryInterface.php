<?php

namespace App\Interfaces\Repositories;

use App\Models\LicensePlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LicensePlanRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes): LicensePlan;
}
