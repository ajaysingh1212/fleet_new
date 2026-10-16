<?php

namespace App\Models;

use App\Enums\TrackingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTrackingPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'distance_filter_meters',
        'tracking_source',
        'self_tracking_enabled',
    ];

    protected function casts(): array
    {
        return [
            'distance_filter_meters' => 'integer',
            'tracking_source' => TrackingSource::class,
            'self_tracking_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
