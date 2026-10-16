<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'type',
        'body',
        'reply_to_message_id',
        'forwarded_from_message_id',
        'is_edited',
        'edited_at',
        'is_pinned',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
            'is_pinned' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forwarded_from_message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function userDeletes(): HasMany
    {
        return $this->hasMany(MessageUserDelete::class);
    }

    public function stars(): HasMany
    {
        return $this->hasMany(MessageStar::class);
    }

    public static function unreadCountFor(User $user, ?int $conversationId = null): int
    {
        return static::query()
            ->when(
                $conversationId,
                fn ($query, $id) => $query->where('conversation_id', $id),
                fn ($query) => $query->whereIn('conversation_id', $user->conversationMemberships()->pluck('conversation_id')),
            )
            ->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id)->whereNotNull('read_at'))
            ->whereDoesntHave('userDeletes', fn ($query) => $query->where('user_id', $user->id))
            ->count();
    }
}
