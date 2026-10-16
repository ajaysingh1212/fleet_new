<?php

namespace App\Enums;

enum GeofenceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
