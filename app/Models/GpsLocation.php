<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsLocation extends Model
{
    use HasUuid;

    const UPDATED_AT = null;

    protected $table = 'gps_locations';

    protected $fillable = [
        'uuid',
        'user_id',
        'device_session_id',
        'tracking_session_id',
        'source_type',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'bearing',
        'heading',
        'altitude',
        'battery_level',
        'network_type',
        'signal_strength',
        'provider',
        'is_mock',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'speed' => 'decimal:2',
            'bearing' => 'decimal:2',
            'heading' => 'decimal:2',
            'altitude' => 'decimal:2',
            'battery_level' => 'integer',
            'signal_strength' => 'integer',
            'is_mock' => 'boolean',
            'recorded_at' => 'datetime',
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

    public function trackingSession(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class);
    }
}
