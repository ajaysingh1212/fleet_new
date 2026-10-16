<?php

namespace App\Enums;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case Ongoing = 'ongoing';
    case Ended = 'ended';
    case Missed = 'missed';
    case Rejected = 'rejected';
    case Busy = 'busy';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
