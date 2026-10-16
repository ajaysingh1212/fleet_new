<?php

namespace App\Repositories;

use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()->latest()->paginate($perPage);
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()->with('roles')->withTrashed(($filters['status'] ?? null) === 'deleted' ? true : false);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'deleted') {
                $query->onlyTrashed();
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }
}
