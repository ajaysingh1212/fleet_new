<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'channel', 'subject', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
