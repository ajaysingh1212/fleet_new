<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedLogin extends Model
{
    public $timestamps = true;

    protected $fillable = ['user_id', 'email', 'ip_address', 'user_agent', 'reason', 'attempted_at'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
