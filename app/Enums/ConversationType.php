<?php

namespace App\Enums;

enum ConversationType: string
{
    case Private = 'private';
    case Group = 'group';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
