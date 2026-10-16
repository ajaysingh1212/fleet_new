<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseTransfer extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_license_id',
        'from_user_id',
        'to_user_id',
        'transferred_by_user_id',
        'admin_transfer',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'admin_transfer' => 'boolean',
            'transferred_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(UserLicense::class, 'user_license_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }
}