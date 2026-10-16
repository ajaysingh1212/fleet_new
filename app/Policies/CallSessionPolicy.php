<?php

namespace App\Policies;

use App\Models\CallSession;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatAuthorizationService;

class CallSessionPolicy
{
    public function view(User $user, CallSession $callSession): bool
    {
        return $callSession->loadMissing('conversation')->conversation->hasMember($user);
    }

    public function create(User $user, Conversation $conversation): bool
    {
        return app(ChatAuthorizationService::class)->canUseConversation($user, $conversation);
    }

    public function join(User $user, CallSession $callSession): bool
    {
        return app(ChatAuthorizationService::class)->canUseConversation(
            $user,
            $callSession->loadMissing('conversation')->conversation,
        );
    }
}
