<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeviceSession extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'session_id',
        'device_name',
        'platform',
        'browser',
        'ip_address',
        'country',
        'last_login_at',
        'last_activity_at',
        'logged_out_at',
        'is_current',
        'tracking_license_id',
        'tracking_key_hash',
        'tracking_registered_at',
        'tracking_revoked_at',
    ];

    protected $hidden = ['tracking_key_hash'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'is_current' => 'boolean',
            'tracking_registered_at' => 'datetime',
            'tracking_revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): HasOne
    {
        return $this->hasOne(DeviceStatus::class);
    }

    public function gpsLocations(): HasMany
    {
        return $this->hasMany(GpsLocation::class);
    }

    public function trackingSessions(): HasMany
    {
        return $this->hasMany(TrackingSession::class);
    }
}
