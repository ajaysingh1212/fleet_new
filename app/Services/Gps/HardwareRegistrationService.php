<?php

namespace App\Services\Gps;

use App\Http\Requests\Admin\VehicleRequest;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use App\Services\Gps\Gateway\DeviceAllowlist;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Predis\PredisException;

class HardwareRegistrationService
{
    public function save(array $data, ?GpsDevice $device = null): GpsDevice
    {
        $oldIdentifier = $device?->identifier;
        // SQL is authoritative. Unique constraints and assignment row locks
        // protect registration independently of gateway/cache availability.
        $saved = $this->persist($data, $device);
        try {
            app(DeviceAllowlist::class)->syncCurrent($saved->id, $oldIdentifier);
        } catch (PredisException|\RedisException|LockTimeoutException) {
            // The durable registration remains saved. Workers recheck SQL
            // authorization before storing/broadcasting any cached-device data.
            $saved->allowlistSyncPending = true;
        }

        return $saved;
    }

    private function persist(array $data, ?GpsDevice $device): GpsDevice
    {
        return DB::transaction(function () use ($data, $device) {
            $device = $device ? GpsDevice::lockForUpdate()->findOrFail($device->id) : new GpsDevice;
            $vehicleId = $data['vehicle_id'] ?? null;
            if (! empty($data['new_vehicle']['registration_number'])) {
                $new = $data['new_vehicle'];
                $new['details'] = collect($new['details'] ?? [])->only(array_map(fn ($key) => substr($key, 8), array_keys(VehicleRequest::detailRules())))->all();
                $vehicle = Vehicle::create(['registration_number' => $new['registration_number'], 'name' => $new['name'],
                    'status' => $new['status'] ?? 'active', 'details' => $new['details'] ?? []]);
                $vehicleId = $vehicle->id;
            }
            if ($vehicleId) {
                Vehicle::lockForUpdate()->findOrFail($vehicleId);
                if (GpsDevice::where('vehicle_id', $vehicleId)->when($device->exists, fn ($q) => $q->where('id', '!=', $device->id))->exists()) {
                    throw ValidationException::withMessages(['vehicle_id' => 'This vehicle already has an assigned tracker. Unassign it first.']);
                }
            }
            $oldVehicle = $device->vehicle_id;
            $device->fill(collect($data)->only(['name', 'identifier', 'imei', 'manufacturer', 'model', 'protocol', 'protocol_version', 'status', 'details', 'configuration'])->all());
            $device->vehicle_id = $vehicleId;
            if (! $device->exists || $device->isDirty('configuration')) {
                $device->configuration_updated_at = now();
                $device->configuration_status = 'recorded_only';
            }
            $device->save();
            if ((int) $oldVehicle !== (int) $vehicleId) {
                DB::table('gps_device_assignments')->where('gps_device_id', $device->id)->whereNull('removed_at')->update(['removed_at' => now()]);
                if ($vehicleId) {
                    DB::table('gps_device_assignments')->insert(['gps_device_id' => $device->id,
                        'vehicle_id' => $vehicleId, 'installed_at' => now()]);
                }
                // A location measured before reassignment must not appear on the new vehicle.
                $device->forceFill(['latest_location_id' => null])->saveQuietly();
            }

            return $device;
        });
    }
}
