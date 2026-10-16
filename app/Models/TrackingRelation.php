<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackingRelation extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'tracker_user_id',
        'tracked_user_id',
        'user_license_id',
        'relationship_name',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['status' => UserStatus::class];
    }

    public function trackedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tracked_user_id');
    }

    public function trackerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tracker_user_id');
    }

    public function userLicense(): BelongsTo
    {
        return $this->belongsTo(UserLicense::class);
    }

    public function scopeUsableForTracking(Builder $query): Builder
    {
        return $query
            ->where('status', UserStatus::Active)
            ->whereHas('userLicense', fn (Builder $license) => $license->usable());
    }

    public function hasUsableLicense(): bool
    {
        return $this->status === UserStatus::Active && (bool) $this->userLicense?->isUsable();
    }
}
