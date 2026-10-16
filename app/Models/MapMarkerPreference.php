<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapMarkerPreference extends Model
{
    protected $fillable = ['viewer_user_id', 'tracked_user_id', 'kind', 'image_path'];

    public const OPTIONS = [
        'person' => ['Person', 'fa-person'],
        'car' => ['Car / Taxi', 'fa-car'],
        'suv' => ['SUV / Jeep', 'fa-car-side'],
        'van' => ['Van / Tempo Traveller', 'fa-van-shuttle'],
        'bike' => ['Motorcycle', 'fa-motorcycle'],
        'scooter' => ['Scooter', 'fa-motorcycle'],
        'cycle' => ['Bicycle', 'fa-bicycle'],
        'auto' => ['Auto rickshaw', 'fa-taxi'],
        'erickshaw' => ['E-rickshaw', 'fa-taxi'],
        'rickshaw' => ['Cycle rickshaw', 'fa-bicycle'],
        'bus' => ['Bus / Minibus', 'fa-bus'],
        'truck' => ['Truck / Lorry', 'fa-truck'],
        'pickup' => ['Pickup / Goods tempo', 'fa-truck-pickup'],
        'tractor' => ['Tractor', 'fa-tractor'],
        'ambulance' => ['Ambulance', 'fa-truck-medical'],
        'fire-engine' => ['Fire engine', 'fa-truck'],
        'construction' => ['JCB / Construction vehicle', 'fa-truck-monster'],
        'train' => ['Train / Metro', 'fa-train'],
        'boat' => ['Boat / Ferry', 'fa-ship'],
        'other' => ['Other', 'fa-image'],
    ];

    public function markerData(): array
    {
        return ['kind' => $this->kind, 'imageUrl' => $this->image_path
            ? route('live-map.marker.image', $this->tracked_user_id) : null];
    }
}
