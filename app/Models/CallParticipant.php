<?php

namespace App\Models;

use App\Enums\CallParticipantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallParticipant extends Model
{
    protected $fillable = [
        'call_session_id',
        'user_id',
        'status',
        'joined_at',
        'left_at',
        'is_muted',
        'is_video_enabled',
        'is_on_hold',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallParticipantStatus::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'is_muted' => 'boolean',
            'is_video_enabled' => 'boolean',
            'is_on_hold' => 'boolean',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
