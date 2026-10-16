<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'uuid' => $this->uuid,
            'conversation_uuid' => $this->whenLoaded('conversation', fn () => $this->conversation?->uuid),
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar,
            ],
            'type' => $this->type?->value,
            'body' => $this->trashed() ? null : $this->body,
            'is_deleted' => $this->trashed(),
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo ? [
                'uuid' => $this->replyTo->uuid,
                'sender_name' => $this->replyTo->sender->name,
                'body' => str($this->replyTo->body)->limit(80)->toString(),
            ] : null),
            'forwarded_from' => $this->whenLoaded('forwardedFrom', fn () => $this->forwardedFrom ? [
                'uuid' => $this->forwardedFrom->uuid,
                'sender_name' => $this->forwardedFrom->sender->name,
            ] : null),
            'is_edited' => $this->is_edited,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'is_pinned' => $this->is_pinned,
            'is_starred' => (bool) (($this->resource->getAttributes()['is_starred'] ?? null) ?? false),
            'reactions' => $this->whenLoaded('reactions', fn () => $this->reactions->map(fn ($reaction) => [
                'user_id' => $reaction->user_id,
                'emoji' => $reaction->emoji,
            ])),
            'reads' => $this->whenLoaded('reads', fn () => $this->reads->map(fn ($read) => [
                'user_id' => $read->user_id,
                'delivered_at' => $read->delivered_at?->toIso8601String(),
                'read_at' => $read->read_at?->toIso8601String(),
            ])),
            'metadata' => $this->metadata,
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(function ($attachment) use ($user) {
                $opened = $user ? $attachment->openedBy($user, $this->sender_id) : false;

                return [
                    'uuid' => $attachment->uuid,
                    'type' => $attachment->type?->value,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'width' => $attachment->width,
                    'height' => $attachment->height,
                    'duration' => $attachment->duration,
                    'view_once' => $attachment->view_once,
                    'opened' => $opened,
                    'url' => $opened ? null : route('api.v1.attachments.show', $attachment->uuid),
                    'thumbnail_url' => $opened || ! $attachment->thumbnail_path ? null : route('api.v1.attachments.thumbnail', $attachment->uuid),
                ];
            })),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
