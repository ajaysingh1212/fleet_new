<?php

namespace App\Models;

use App\Enums\ConversationMemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMember extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'joined_at',
        'last_read_message_id',
        'muted_until',
    ];

    protected function casts(): array
    {
        return [
            'role' => ConversationMemberRole::class,
            'joined_at' => 'datetime',
            'muted_until' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
