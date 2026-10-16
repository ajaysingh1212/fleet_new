<?php

namespace App\Enums;

enum TrackingSessionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
