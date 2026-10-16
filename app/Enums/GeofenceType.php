<?php

namespace App\Enums;

enum GeofenceType: string
{
    case Circle = 'circle';
    case Polygon = 'polygon';
    case Rectangle = 'rectangle';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
