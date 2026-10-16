<?php

namespace App\Enums;

enum CallParticipantStatus: string
{
    case Invited = 'invited';
    case Ringing = 'ringing';
    case Joined = 'joined';
    case Declined = 'declined';
    case Missed = 'missed';
    case Left = 'left';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
