<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachmentView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_attachment_id',
        'user_id',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
        ];
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(MessageAttachment::class, 'message_attachment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
