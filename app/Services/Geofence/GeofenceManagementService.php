<?php

namespace App\Services\Geofence;

use App\Enums\GeofenceStatus;
use App\Models\Geofence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GeofenceManagementService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Geofence
    {
        return DB::transaction(function () use ($data, $creator) {
            $geofence = Geofence::create([
                ...$this->geofenceAttributes($data),
                'status' => GeofenceStatus::Active,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);

            $this->syncPoints($geofence, $data['points'] ?? []);

            return $geofence->load('points');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Geofence $geofence, array $data, User $updater): Geofence
    {
        return DB::transaction(function () use ($geofence, $data, $updater) {
            $geofence->update([
                ...$this->geofenceAttributes($data),
                'updated_by' => $updater->id,
            ]);

            $this->syncPoints($geofence, $data['points'] ?? []);

            return $geofence->load('points');
        });
    }

    public function duplicate(Geofence $geofence, User $creator): Geofence
    {
        return DB::transaction(function () use ($geofence, $creator) {
            $copy = Geofence::create([
                'name' => $geofence->name.' (Copy)',
                'description' => $geofence->description,
                'type' => $geofence->type,
                'category' => $geofence->category,
                'color' => $geofence->color,
                'status' => GeofenceStatus::Active,
                'center_lat' => $geofence->center_lat,
                'center_lng' => $geofence->center_lng,
                'radius_meters' => $geofence->radius_meters,
                'min_speed_kmh' => $geofence->min_speed_kmh,
                'max_speed_kmh' => $geofence->max_speed_kmh,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);

            $points = $geofence->points()->orderBy('sequence')->get()
                ->map(fn ($point) => [
                    'geofence_id' => $copy->id,
                    'sequence' => $point->sequence,
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                ])
                ->all();

            if ($points) {
                $copy->points()->insert($points);
            }

            return $copy->load('points');
        });
    }

    public function setStatus(Geofence $geofence, GeofenceStatus $status, User $updater): Geofence
    {
        $geofence->update(['status' => $status, 'updated_by' => $updater->id]);

        return $geofence;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, Geofence>
     */
    public function import(array $rows, User $creator): array
    {
        return array_map(fn (array $row) => $this->create($row, $creator), $rows);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function geofenceAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'category' => $data['category'],
            'color' => $data['color'] ?? '#38bdf8',
            'center_lat' => $data['center_lat'] ?? null,
            'center_lng' => $data['center_lng'] ?? null,
            'radius_meters' => $data['radius_meters'] ?? null,
            'min_speed_kmh' => $data['min_speed_kmh'] ?? null,
            'max_speed_kmh' => $data['max_speed_kmh'] ?? null,
        ];
    }

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $points
     */
    private function syncPoints(Geofence $geofence, array $points): void
    {
        $geofence->points()->delete();

        if (! $points) {
            return;
        }

        $rows = array_map(fn (array $point, int $index) => [
            'geofence_id' => $geofence->id,
            'sequence' => $index,
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
        ], $points, array_keys($points));

        $geofence->points()->insert($rows);
    }
}
