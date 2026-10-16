<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        return $user->can('manage support tickets') || $user->id === $supportTicket->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SupportTicket $supportTicket): bool
    {
        return $user->can('manage support tickets');
    }

    public function respond(User $user, SupportTicket $supportTicket): bool
    {
        return $user->can('manage support tickets');
    }
}
