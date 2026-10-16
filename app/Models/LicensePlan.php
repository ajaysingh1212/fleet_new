<?php

namespace App\Models;

use App\Enums\LicenseType;
use App\Enums\UserStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicensePlan extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'duration_in_days',
        'price',
        'renewal_price',
        'is_free',
        'status',
        'description',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => LicenseType::class,
            'status' => UserStatus::class,
            'price' => 'decimal:2',
            'renewal_price' => 'decimal:2',
            'is_free' => 'boolean',
        ];
    }

    public function userLicenses(): HasMany
    {
        return $this->hasMany(UserLicense::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LicenseTransaction::class);
    }
}
