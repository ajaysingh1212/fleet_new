<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatAuthorizationService;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->hasMember($user);
    }

    public function create(User $user, Conversation $conversation): bool
    {
        return app(ChatAuthorizationService::class)->canUseConversation($user, $conversation);
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id;
    }

    public function react(User $user, Message $message): bool
    {
        return $message->conversation->hasMember($user);
    }
}
