<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofencePoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'geofence_id',
        'sequence',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }
}
