<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tracking_session_id',
        'event_type',
        'latitude',
        'longitude',
        'speed',
        'bearing',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed' => 'decimal:2',
            'bearing' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function trackingSession(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class);
    }
}
