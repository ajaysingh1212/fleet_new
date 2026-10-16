<?php

namespace App\Models;

use App\Enums\CallStatus;
use App\Enums\CallType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallSession extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'conversation_id',
        'type',
        'status',
        'initiator_id',
        'started_at',
        'ended_at',
        'ended_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => CallType::class,
            'status' => CallStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }
}
