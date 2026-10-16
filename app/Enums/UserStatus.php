<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Blocked = 'blocked';
    case Deleted = 'deleted';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
