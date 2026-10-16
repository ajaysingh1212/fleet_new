<?php

namespace App\Models;

use App\Enums\DiagnosticEventType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticLog extends Model
{
    use HasUuid;

    const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'user_id',
        'device_session_id',
        'event_type',
        'latitude',
        'longitude',
        'reason',
        'duration_seconds',
        'network_type',
        'battery_level',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => DiagnosticEventType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'duration_seconds' => 'integer',
            'battery_level' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceSession(): BelongsTo
    {
        return $this->belongsTo(DeviceSession::class);
    }
}
