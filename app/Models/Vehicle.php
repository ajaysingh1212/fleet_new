<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public function gpsDevice(): HasOne
    {
        return $this->hasOne(GpsDevice::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vehicle_user_assignments')->withPivot('assigned_by')->withTimestamps();
    }

    protected $fillable = ['registration_number', 'name', 'status', 'details', 'owner_user_id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
