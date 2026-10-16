<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSyncLog extends Model
{
    use HasUuid;

    const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'user_id',
        'device_session_id',
        'batch_size',
        'oldest_recorded_at',
        'newest_recorded_at',
        'accepted_count',
        'rejected_count',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'batch_size' => 'integer',
            'oldest_recorded_at' => 'datetime',
            'newest_recorded_at' => 'datetime',
            'accepted_count' => 'integer',
            'rejected_count' => 'integer',
            'synced_at' => 'datetime',
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
