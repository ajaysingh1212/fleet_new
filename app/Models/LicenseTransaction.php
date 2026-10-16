<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseTransaction extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_license_id',
        'license_plan_id',
        'type',
        'amount',
        'currency',
        'gateway',
        'environment',
        'status',
        'provider_order_id',
        'provider_payment_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(UserLicense::class, 'user_license_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class, 'license_plan_id');
    }
}