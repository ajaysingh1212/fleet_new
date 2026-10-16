<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldTask extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'creator_id', 'assignee_id', 'tracking_relation_id', 'title', 'description',
        'priority', 'schedule_type', 'starts_at', 'due_at', 'repeat_until', 'repeat_days',
        'status', 'arrival_radius_meters', 'customer_name', 'customer_phone', 'reference_code',
        'instructions', 'tags', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'due_at' => 'datetime', 'repeat_until' => 'date',
            'repeat_days' => 'array', 'tags' => 'array', 'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function trackingRelation(): BelongsTo { return $this->belongsTo(TrackingRelation::class); }
    public function stops(): HasMany { return $this->hasMany(FieldTaskStop::class)->orderBy('sequence'); }
    public function activity(): HasMany { return $this->hasMany(FieldTaskActivity::class)->orderByDesc('occurred_at'); }

    public function involves(User $user): bool
    {
        return $user->id === $this->creator_id || $user->id === $this->assignee_id;
    }
}
