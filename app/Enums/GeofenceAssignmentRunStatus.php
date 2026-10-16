<?php

namespace App\Enums;

enum GeofenceAssignmentRunStatus: string
{
    case Pending = 'pending';
    case Visited = 'visited';
    case Missed = 'missed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
