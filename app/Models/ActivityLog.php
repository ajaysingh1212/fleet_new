<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasUuid;

    public $timestamps = true;

    protected $fillable = [
        'uuid',
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'device',
        'country',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
