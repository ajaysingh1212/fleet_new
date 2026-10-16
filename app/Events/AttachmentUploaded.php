<?php

namespace App\Events;

use App\Models\MessageAttachment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Extension point for a future virus-scanning/moderation listener — the spec
 * asks for "hooks", not an actual scanner. Plain dispatched event, not
 * broadcast: nothing client-facing needs this yet.
 */
class AttachmentUploaded
{
    use Dispatchable;

    public function __construct(
        public readonly MessageAttachment $attachment,
    ) {}
}
