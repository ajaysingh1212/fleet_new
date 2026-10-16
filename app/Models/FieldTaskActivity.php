<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldTaskActivity extends Model
{
    const UPDATED_AT = null;

    protected $table = 'field_task_activity';
    protected $fillable = ['field_task_id', 'field_task_stop_id', 'user_id', 'event_type', 'metadata', 'occurred_at'];
    protected function casts(): array { return ['metadata' => 'array', 'occurred_at' => 'datetime']; }
    public function task(): BelongsTo { return $this->belongsTo(FieldTask::class, 'field_task_id'); }
    public function stop(): BelongsTo { return $this->belongsTo(FieldTaskStop::class, 'field_task_stop_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
