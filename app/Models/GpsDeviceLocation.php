<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsDeviceLocation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime', 'telemetry' => 'array',
            'latitude' => 'float', 'longitude' => 'float', 'speed' => 'float', 'heading' => 'float', 'gps_fix' => 'boolean'];
    }
}
