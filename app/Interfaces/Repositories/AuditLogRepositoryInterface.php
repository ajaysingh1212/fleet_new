<?php

namespace App\Interfaces\Repositories;

use App\Models\AuditLog;

interface AuditLogRepositoryInterface
{
    public function create(array $attributes): AuditLog;
}
