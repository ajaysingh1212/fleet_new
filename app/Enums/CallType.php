<?php

namespace App\Enums;

enum CallType: string
{
    case Voice = 'voice';
    case Video = 'video';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
