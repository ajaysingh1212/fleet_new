<?php

namespace App\Repositories;

use App\Interfaces\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function create(array $attributes): AuditLog
    {
        return AuditLog::create($attributes);
    }
}
