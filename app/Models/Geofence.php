<?php

namespace App\Models;

use App\Enums\GeofenceCategory;
use App\Enums\GeofenceStatus;
use App\Enums\GeofenceType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Geofence extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'type',
        'category',
        'color',
        'status',
        'center_lat',
        'center_lng',
        'radius_meters',
        'min_speed_kmh',
        'max_speed_kmh',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => GeofenceType::class,
            'category' => GeofenceCategory::class,
            'status' => GeofenceStatus::class,
            'center_lat' => 'float',
            'center_lng' => 'float',
            'radius_meters' => 'integer',
            'min_speed_kmh' => 'integer',
            'max_speed_kmh' => 'integer',
        ];
    }

    public function points(): HasMany
    {
        return $this->hasMany(GeofencePoint::class)->orderBy('sequence');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GeofenceAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GeofenceStatus::Active);
    }
}
