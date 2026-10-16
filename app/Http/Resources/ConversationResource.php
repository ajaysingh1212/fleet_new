<?php

namespace App\Http\Resources;

use App\Enums\ConversationType;
use App\Models\Message;
use App\Services\ChatAuthorizationService;
use App\Services\UserPresenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isGroup = $this->type === ConversationType::Group;
        $other = $isGroup ? null : $this->otherParticipant($user);
        $presence = app(UserPresenceService::class);
        $canCommunicate = app(ChatAuthorizationService::class)->canUseConversation($user, $this->resource);

        return [
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            // Uniform list-row contract for both types — name/avatar always
            // reflect who/what this conversation is with, from $user's POV.
            'name' => $isGroup ? $this->name : $other?->name,
            'avatar' => $isGroup ? $this->avatar : $other?->avatar,
            'avatar_url' => $isGroup
                ? ($this->avatar ? asset('storage/'.$this->avatar) : null)
                : ($other?->avatar ? asset('storage/'.$other->avatar) : null),
            'member_count' => $isGroup ? ($this->member_count ?? $this->members()->count()) : null,
            'other_user' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'avatar' => $other->avatar,
                'avatar_url' => $other->avatar ? asset('storage/'.$other->avatar) : null,
                'department' => $other->department,
                'designation' => $other->designation,
                'company' => $other->company,
                'is_online' => $presence->isOnline($other->id),
                'last_activity_at' => $presence->lastActivityAt($other->id),
            ] : null,
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body' => $this->latestMessage->trashed() ? 'This message was deleted' : str($this->latestMessage->body)->limit(80)->toString(),
                'sender_id' => $this->latestMessage->sender_id,
                'created_at' => $this->latestMessage->created_at->toIso8601String(),
            ] : null),
            'unread_count' => $this->unread_count ?? Message::unreadCountFor($user, $this->id),
            'can_communicate' => $canCommunicate,
            'blocked_reason' => $canCommunicate || $isGroup ? null : 'The tracking license for this private chat has expired.',
            'license_actions' => $canCommunicate || $isGroup ? null : [
                'renew_url' => route('my-licenses.index'),
                'upgrade_url' => route('my-licenses.plans'),
            ],
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
