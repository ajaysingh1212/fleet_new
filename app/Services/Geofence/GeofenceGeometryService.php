<?php

namespace App\Services\Geofence;

use App\Models\Geofence;
use App\Enums\GeofenceType;

class GeofenceGeometryService
{
    public function containsPoint(Geofence $geofence, float $latitude, float $longitude): bool
    {
        return match ($geofence->type) {
            GeofenceType::Circle => $this->pointInCircle(
                $latitude,
                $longitude,
                (float) $geofence->center_lat,
                (float) $geofence->center_lng,
                (float) $geofence->radius_meters,
            ),
            GeofenceType::Polygon, GeofenceType::Rectangle => $this->pointInPolygon(
                $latitude,
                $longitude,
                $geofence->points->map(fn ($point) => [(float) $point->latitude, (float) $point->longitude])->all(),
            ),
        };
    }

    public function pointInCircle(float $lat, float $lng, float $centerLat, float $centerLng, float $radiusMeters): bool
    {
        return $this->haversineMeters($lat, $lng, $centerLat, $centerLng) <= $radiusMeters;
    }

    /**
     * Ray-casting algorithm — works for both polygon and rectangle geofences
     * since a rectangle is just stored as a 4-point polygon.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function pointInPolygon(float $lat, float $lng, array $points): bool
    {
        $vertexCount = count($points);

        if ($vertexCount < 3) {
            return false;
        }

        $inside = false;
        $j = $vertexCount - 1;

        for ($i = 0; $i < $vertexCount; $i++) {
            [$latI, $lngI] = $points[$i];
            [$latJ, $lngJ] = $points[$j];

            $intersects = ($lngI > $lng) !== ($lngJ > $lng)
                && $lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI;

            if ($intersects) {
                $inside = ! $inside;
            }

            $j = $i;
        }

        return $inside;
    }

    public function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
