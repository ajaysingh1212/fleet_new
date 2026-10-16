<?php

namespace App\Enums;

enum GeofenceScheduleType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case CustomDate = 'custom_date';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
