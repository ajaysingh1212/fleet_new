<?php

namespace App\Enums;

enum LocationShareStopRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
