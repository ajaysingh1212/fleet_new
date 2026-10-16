<?php

namespace App\Enums;

enum ConversationMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
