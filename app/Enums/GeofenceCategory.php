<?php

namespace App\Enums;

enum GeofenceCategory: string
{
    case Office = 'office';
    case Branch = 'branch';
    case Home = 'home';
    case Warehouse = 'warehouse';
    case ConstructionSite = 'construction_site';
    case CustomerLocation = 'customer_location';
    case DeliveryPoint = 'delivery_point';
    case School = 'school';
    case Hospital = 'hospital';
    case Factory = 'factory';
    case RestrictedArea = 'restricted_area';
    case Custom = 'custom';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
