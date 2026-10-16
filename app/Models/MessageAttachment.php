<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageAttachment extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'type',
        'thumbnail_path',
        'duration',
        'width',
        'height',
        'view_once',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'size' => 'integer',
            'duration' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'view_once' => 'boolean',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(MessageAttachmentView::class);
    }

    public function openedBy(User $user, ?int $senderId = null): bool
    {
        $senderId ??= $this->message?->sender_id;

        if (! $this->view_once || $senderId === $user->id) {
            return false;
        }

        return $this->views()->where('user_id', $user->id)->exists();
    }
}
