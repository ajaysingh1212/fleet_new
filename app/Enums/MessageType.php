<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case VoiceNote = 'voice_note';
    case Location = 'location';
    case System = 'system';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
