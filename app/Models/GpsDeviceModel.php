<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsDeviceModel extends Model
{
    protected $fillable = ['name', 'manufacturer', 'protocol', 'protocol_version', 'network_generation', 'active', 'notes'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
