<?php

namespace App\Models;

use App\Enums\RouteProposalStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteProposal extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'conversation_id',
        'proposed_by_id',
        'accepted_by_id',
        'target_lat',
        'target_lng',
        'label',
        'status',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'target_lat' => 'float',
            'target_lng' => 'float',
            'status' => RouteProposalStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }
}
