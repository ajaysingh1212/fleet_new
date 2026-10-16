<?php

namespace App\Enums;

enum LicenseType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
