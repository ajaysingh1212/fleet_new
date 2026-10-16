<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
