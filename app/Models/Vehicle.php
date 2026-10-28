<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public function gpsDevice(): HasOne
    {
        return $this->hasOne(GpsDevice::class);
    }

    protected $fillable = ['registration_number', 'name', 'status', 'details', 'owner_user_id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
