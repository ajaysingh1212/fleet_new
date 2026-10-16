<?php

namespace App\Enums;

enum SourceType: string
{
    case Android = 'android';
    case Ios = 'ios';
    case Browser = 'browser';
    case Desktop = 'desktop';
    case Api = 'api';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
