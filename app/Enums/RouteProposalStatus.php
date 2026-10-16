<?php

namespace App\Enums;

enum RouteProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
