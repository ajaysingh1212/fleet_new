<?php

namespace App\Enums;

enum GeofenceEventType: string
{
    case Entered = 'entered';
    case Exited = 'exited';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
