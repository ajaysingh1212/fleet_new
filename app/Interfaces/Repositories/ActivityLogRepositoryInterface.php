<?php

namespace App\Interfaces\Repositories;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog;

    public function paginateForUser(?int $userId = null, int $perPage = 15): LengthAwarePaginator;
}
