<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\RelationshipAuthorizationService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    if ((int) $user->id === (int) $id) {
        return true;
    }

    if (Conversation::query()
        ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))
        ->whereHas('members', fn ($query) => $query->where('user_id', $id))
        ->exists()) {
        return true;
    }

    $target = User::find($id);

    return $target && app(RelationshipAuthorizationService::class)->canViewUser($user, $target);
});

Broadcast::channel('conversation.{uuid}', function ($user, $uuid) {
    return Conversation::where('uuid', $uuid)->first()?->hasMember($user) ?? false;
});

Broadcast::channel('diagnostics.{id}', function ($user, $id) {
    $target = User::find($id);

    return $target && app(RelationshipAuthorizationService::class)->canViewDiagnostics($user, $target);
});

Broadcast::channel('admin.diagnostics', function ($user) {
    return app(RelationshipAuthorizationService::class)->canAdminViewUser($user, $user);
});
