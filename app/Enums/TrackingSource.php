<?php

namespace App\Enums;

enum TrackingSource: string
{
    case Automatic = 'automatic';
    case Android = 'android';
    case Browser = 'browser';
    case Iphone = 'iphone';
    case Desktop = 'desktop';
    case Manual = 'manual';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
