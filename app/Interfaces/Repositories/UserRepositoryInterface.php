<?php

namespace App\Interfaces\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes): User;
}
