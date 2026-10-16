<?php

namespace App\Services;

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Events\ConversationDeleted;
use App\Events\ConversationStarted;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\GroupInviteNotification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(
        protected ChatAuthorizationService $chatAuthorization,
        protected ActivityLogService $activityLogService,
        protected MessageService $messageService,
    ) {}

    /**
     * @return Collection<int, Conversation>
     */
    public function myConversations(User $user): Collection
    {
        return Conversation::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'latestMessage',
                'participants.currentActiveSession',
            ])
            ->withCount([
                'members as member_count',
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('reads', fn ($reads) => $reads->where('user_id', $user->id)->whereNotNull('read_at'))
                    ->whereDoesntHave('userDeletes', fn ($deletes) => $deletes->where('user_id', $user->id)),
            ])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => $conversation->latestMessage?->created_at ?? $conversation->updated_at)
            ->values();
    }

    public function createPrivateConversation(User $a, User $b): Conversation
    {
        if (! $this->chatAuthorization->canCommunicate($a, $b)) {
            throw ValidationException::withMessages([
                'user' => 'You do not have a tracking relationship with this user.',
            ]);
        }

        $existing = Conversation::query()
            ->where('type', ConversationType::Private)
            ->whereHas('members', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $b->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create([
            'type' => ConversationType::Private,
            'created_by' => $a->id,
        ]);

        $conversation->members()->createMany([
            ['user_id' => $a->id, 'role' => ConversationMemberRole::Member, 'joined_at' => now()],
            ['user_id' => $b->id, 'role' => ConversationMemberRole::Member, 'joined_at' => now()],
        ]);

        broadcast(new ConversationStarted($conversation, $b->id, $a));

        $this->activityLogService->log($a, 'conversation.private.created', $conversation, [
            'with' => $b->name,
        ]);

        return $conversation;
    }

    public function createGroup(User $owner, array $attributes, array $memberIds): Conversation
    {
        $members = User::query()->whereIn('id', array_diff(array_unique($memberIds), [$owner->id]))->get();

        foreach ($members as $member) {
            if (! $this->chatAuthorization->canCommunicate($owner, $member)) {
                throw ValidationException::withMessages([
                    'member_ids' => "You can only add users you have a tracking relationship with ({$member->name} is not connected).",
                ]);
            }
        }

        $conversation = Conversation::create([
            'type' => ConversationType::Group,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'avatar' => $attributes['avatar'] ?? null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        $conversation->members()->create([
            'user_id' => $owner->id,
            'role' => ConversationMemberRole::Owner,
            'joined_at' => now(),
        ]);

        foreach ($members as $member) {
            $conversation->members()->create([
                'user_id' => $member->id,
                'role' => ConversationMemberRole::Member,
                'joined_at' => now(),
            ]);

            $member->notify(new GroupInviteNotification($conversation, $owner));
        }

        $this->activityLogService->log($owner, 'conversation.group.created', $conversation, [
            'name' => $conversation->name,
            'member_count' => $members->count() + 1,
        ]);

        $this->messageService->system($conversation, $owner, "{$owner->name} created the group");

        return $conversation;
    }

    public function addMember(Conversation $group, User $actor, User $newMember): void
    {
        $this->ensureCanManage($group, $actor);

        if (! $this->chatAuthorization->canCommunicate($group->owner, $newMember)) {
            throw ValidationException::withMessages([
                'user' => 'This user has no tracking relationship with the group owner.',
            ]);
        }

        $group->members()->firstOrCreate(
            ['user_id' => $newMember->id],
            ['role' => ConversationMemberRole::Member, 'joined_at' => now()],
        );

        $this->activityLogService->log($actor, 'conversation.group.member_added', $group, [
            'member' => $newMember->name,
        ]);

        $this->messageService->system($group, $actor, "{$actor->name} added {$newMember->name}");
        broadcast(new ConversationUpdated($group));

        $newMember->notify(new GroupInviteNotification($group, $actor));
    }

    public function removeMember(Conversation $group, User $actor, User $member): void
    {
        $this->ensureCanManage($group, $actor);

        if ($group->owner_id === $member->id) {
            throw ValidationException::withMessages([
                'user' => 'The group owner cannot be removed. Delete the group instead.',
            ]);
        }

        $group->members()->where('user_id', $member->id)->delete();

        $this->activityLogService->log($actor, 'conversation.group.member_removed', $group, [
            'member' => $member->name,
        ]);

        $this->messageService->system($group, $actor, "{$actor->name} removed {$member->name}");
        broadcast(new ConversationUpdated($group));
    }

    public function promoteToAdmin(Conversation $group, User $actor, User $member): void
    {
        $this->ensureIsOwner($group, $actor);

        $group->members()->where('user_id', $member->id)->update(['role' => ConversationMemberRole::Admin]);

        $this->activityLogService->log($actor, 'conversation.group.member_promoted', $group, [
            'member' => $member->name,
        ]);

        $this->messageService->system($group, $actor, "{$actor->name} made {$member->name} an admin");
        broadcast(new ConversationUpdated($group));
    }

    public function demoteToMember(Conversation $group, User $actor, User $member): void
    {
        $this->ensureIsOwner($group, $actor);

        $group->members()->where('user_id', $member->id)->update(['role' => ConversationMemberRole::Member]);

        $this->activityLogService->log($actor, 'conversation.group.member_demoted', $group, [
            'member' => $member->name,
        ]);

        $this->messageService->system($group, $actor, "{$actor->name} removed {$member->name} as admin");
        broadcast(new ConversationUpdated($group));
    }

    public function renameGroup(Conversation $group, User $actor, string $name): void
    {
        $this->ensureCanManage($group, $actor);

        $group->update(['name' => $name, 'updated_by' => $actor->id]);

        $this->activityLogService->log($actor, 'conversation.group.renamed', $group, ['name' => $name]);

        $this->messageService->system($group, $actor, "{$actor->name} renamed the group to \"{$name}\"");
        broadcast(new ConversationUpdated($group));
    }

    public function updateDescription(Conversation $group, User $actor, string $description): void
    {
        $this->ensureCanManage($group, $actor);

        $group->update(['description' => $description, 'updated_by' => $actor->id]);

        $this->activityLogService->log($actor, 'conversation.group.description_updated', $group, []);

        $this->messageService->system($group, $actor, "{$actor->name} updated the group description");
        broadcast(new ConversationUpdated($group));
    }

    public function leaveGroup(Conversation $group, User $actor): void
    {
        if ($group->owner_id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'The group owner cannot leave — delete the group instead.',
            ]);
        }

        $group->members()->where('user_id', $actor->id)->delete();

        $this->activityLogService->log($actor, 'conversation.group.member_left', $group, []);

        $this->messageService->system($group, $actor, "{$actor->name} left the group");
        broadcast(new ConversationUpdated($group));
    }

    public function deleteGroup(Conversation $group, User $actor): void
    {
        $this->ensureIsOwner($group, $actor);

        broadcast(new ConversationDeleted($group));

        $group->delete();

        $this->activityLogService->log($actor, 'conversation.group.deleted', null, ['name' => $group->name]);
    }

    private function ensureCanManage(Conversation $group, User $actor): void
    {
        $role = $group->members()->where('user_id', $actor->id)->value('role');

        if (! in_array($role, [ConversationMemberRole::Owner, ConversationMemberRole::Admin], true)) {
            throw ValidationException::withMessages([
                'user' => 'Only the group owner or an admin can perform this action.',
            ]);
        }
    }

    private function ensureIsOwner(Conversation $group, User $actor): void
    {
        if ($group->owner_id !== $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'Only the group owner can perform this action.',
            ]);
        }
    }
}
