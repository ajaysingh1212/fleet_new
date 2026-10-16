<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLicense extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'assigned_tracked_user_id',
        'usage_type',
        'is_free_claim',
        'free_claimed_by_user_id',
        'license_plan_id',
        'license_number',
        'purchase_date',
        'activation_date',
        'expiry_date',
        'status',
        'payment_status',
        'invoice_number',
        'order_number',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'datetime',
            'activation_date' => 'datetime',
            'expiry_date' => 'datetime',
            'status' => LicenseStatus::class,
            'payment_status' => PaymentStatus::class,
            'is_free_claim' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class, 'license_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trackingRelation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TrackingRelation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LicenseTransaction::class);
    }

    public function assignedTrackedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_tracked_user_id');
    }

    public function freeClaimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'free_claimed_by_user_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(LicenseTransfer::class);
    }

    public function isUsable(): bool
    {
        return $this->payment_status === PaymentStatus::Paid
            && $this->status === LicenseStatus::Active
            && ($this->expiry_date === null || $this->expiry_date->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', LicenseStatus::Active)
            ->where(fn (Builder $query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>', now()));
    }

    public function scopeForTrackedUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn (Builder $binding) => $binding
            ->where('assigned_tracked_user_id', $userId)
            ->orWhere(fn (Builder $owned) => $owned->where('user_id', $userId)
                ->whereNull('assigned_tracked_user_id')));
    }
}
