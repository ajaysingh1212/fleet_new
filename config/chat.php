<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Max attachment upload size
    |--------------------------------------------------------------------------
    |
    | In kilobytes, matching Laravel's validation `max:` rule for files.
    */

    'max_upload_kb' => (int) env('CHAT_MAX_UPLOAD_KB', 20480),

    /*
    |--------------------------------------------------------------------------
    | Allowed attachment extensions, grouped by MessageType
    |--------------------------------------------------------------------------
    |
    | Single source of truth for both the upload validation allowlist and
    | classifying an uploaded file into a MessageType case.
    */

    'mimes' => [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'mov', 'webm', 'avi'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'aac'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'csv'],
    ],

    'thumbnail' => [
        'max_dimension' => 320,
        'quality' => 75,
    ],

];
