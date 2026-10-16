<?php

namespace App\Repositories;

use App\Interfaces\Repositories\ActivityLogRepositoryInterface;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog
    {
        return ActivityLog::create($attributes);
    }

    public function paginateForUser(?int $userId = null, int $perPage = 15): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->latest('logged_at')
            ->paginate($perPage);
    }
}
