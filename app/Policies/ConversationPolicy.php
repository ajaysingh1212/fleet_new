<?php

namespace App\Policies;

use App\Enums\ConversationMemberRole;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        $role = $conversation->members()->where('user_id', $user->id)->value('role');

        return in_array($role, [ConversationMemberRole::Owner, ConversationMemberRole::Admin], true);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $conversation->owner_id === $user->id;
    }
}
