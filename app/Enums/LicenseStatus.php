<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}
