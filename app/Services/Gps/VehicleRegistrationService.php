<?php

namespace App\Services\Gps;

use App\Http\Requests\Admin\VehicleRegistrationRequest;
use App\Http\Requests\Admin\VehicleRequest;
use App\Models\GpsDevice;
use App\Models\GpsDeviceModel;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VehicleRegistrationService
{
    public function save(VehicleRegistrationRequest $request, ?Vehicle $vehicle = null): Vehicle
    {
        $uploaded = [];
        try {
            return DB::transaction(function () use ($request, $vehicle, &$uploaded) {
                $vehicle = $vehicle ? Vehicle::lockForUpdate()->findOrFail($vehicle->id) : new Vehicle;
                $sourceDevice = $request->route('gpsDevice');
                if ($sourceDevice) {
                    $sourceDevice = GpsDevice::lockForUpdate()->findOrFail($sourceDevice->id);
                    if ($sourceDevice->vehicle_id !== null) {
                        throw ValidationException::withMessages(['device' => 'This tracker has already been assigned. Open its vehicle to edit the registration.']);
                    }
                }
                $data = $request->validated();
                $keys = [...array_map(fn ($key) => substr($key, 8), array_keys(VehicleRequest::detailRules())), 'icon', 'odometer_source', 'engine_hours'];
                $details = array_replace($vehicle->details ?? [], collect($data['details'] ?? [])->only($keys)->all());
                if (! empty($details['icon'])) {
                    $details['type'] = $details['icon'];
                }
                $documents = $vehicle->details['documents'] ?? [];
                $remove = array_map('intval', $data['remove_documents'] ?? []);
                $removed = array_filter($documents, fn ($key) => in_array($key, $remove, true), ARRAY_FILTER_USE_KEY);
                $documents = array_values(array_filter($documents, fn ($key) => ! in_array($key, $remove, true), ARRAY_FILTER_USE_KEY));
                DB::afterCommit(fn () => Storage::disk('local')->delete(array_column($removed, 'path')));
                foreach (['documents' => 'document', 'vehicle_images' => 'vehicle', 'other_images' => 'other'] as $field => $kind) {
                    foreach ($request->file($field, []) as $file) {
                        $path = $file->store('gps-documents', 'local');
                        if (! $path) {
                            throw new \RuntimeException('Private upload failed.');
                        }
                        $uploaded[] = $path;
                        $documents[] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'kind' => $kind,
                            'mime' => $file->getMimeType()];
                    }
                }
                // Keep a bounded attachment roster, independent of per-request limits.
                if (count($documents) > 50) {
                    throw ValidationException::withMessages(['documents' => 'Maximum 50 attachments per vehicle. Remove older attachments first.']);
                }
                $details['documents'] = $documents;
                $vehicle->fill(collect($data)->only(['registration_number', 'name', 'status', 'owner_user_id'])->all());
                $vehicle->details = $details;
                $vehicle->save();
                $device = $sourceDevice ?? $vehicle->gpsDevice;
                if ($request->boolean('device_enabled')) {
                    $input = $data['device'];
                    $model = GpsDeviceModel::find($input['device_model_id'] ?? $device?->gps_device_model_id);
                    $deviceDetails = array_replace($device?->details ?? [], collect($input)->only(['sim_number', 'sim_iccid', 'network_provider', 'sim_generation', 'serial_number', 'vts', 'firmware_version'])->all());
                    $deviceDetails['network_generation'] = $model?->network_generation ?? ($deviceDetails['network_generation'] ?? null);
                    $configuration = array_replace($device?->configuration ?? [], [
                        'transport' => 'tcp', 'server_port' => 9000, 'timezone' => 'UTC',
                        ...collect($input)->only(['apn', 'reporting_interval', 'heartbeat_interval'])->all()]);
                    $saved = app(HardwareRegistrationService::class)->save([
                        'name' => $vehicle->name.' GPS', 'vehicle_id' => $vehicle->id,
                        'identifier' => $input['identifier'], 'imei' => $input['identifier'],
                        'status' => $input['status'], 'gps_device_model_id' => $model?->id,
                        'manufacturer' => $model?->manufacturer ?? $device?->manufacturer,
                        'model' => $model?->name ?? $device?->model,
                        'protocol' => $model?->protocol ?? $device?->protocol,
                        'protocol_version' => $model?->protocol_version ?? $device?->protocol_version,
                        'details' => $deviceDetails, 'configuration' => $configuration,
                    ], $device, $sourceDevice !== null);
                    $vehicle->setRelation('gpsDevice', $saved);
                } elseif ($device) {
                    if ($sourceDevice) {
                        $device = app(HardwareRegistrationService::class)->save([
                            ...$device->only(['name', 'identifier', 'imei', 'manufacturer', 'model', 'gps_device_model_id', 'protocol', 'protocol_version', 'status', 'details', 'configuration']),
                            'vehicle_id' => $vehicle->id,
                        ], $device, true);
                    }
                    // Unchecking does not silently detach or erase an installed tracker.
                    $vehicle->setRelation('gpsDevice', $device);
                }

                return $vehicle;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($uploaded);
            throw $error;
        }
    }
}
