<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'type',
        'name',
        'description',
        'avatar',
        'owner_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'status' => ConversationStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot(['role', 'joined_at', 'last_read_message_id', 'muted_until'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->withTrashed()->latestOfMany();
    }

    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class);
    }

    public function routeProposals(): HasMany
    {
        return $this->hasMany(RouteProposal::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function otherParticipant(User $user): ?User
    {
        return $this->participants()->where('users.id', '!=', $user->id)->first();
    }
}
