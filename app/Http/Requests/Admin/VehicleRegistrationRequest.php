<?php

namespace App\Http\Requests\Admin;

use App\Models\GpsDeviceModel;
use Illuminate\Validation\Rule;

class VehicleRegistrationRequest extends VehicleRequest
{
    public function rules(): array
    {
        $device = $this->route('vehicle')?->gpsDevice ?? $this->route('gpsDevice');
        $rules = parent::rules();
        $rules['device_enabled'] = ['nullable', 'boolean'];
        $rules['details.icon'] = ['nullable', Rule::in(array_keys(config('fleet.icons')))];
        $rules['details.odometer_source'] = ['nullable', Rule::in(['gps', 'device', 'manual'])];
        $rules['details.engine_hours'] = ['nullable', 'numeric', 'min:0'];
        $rules['vehicle_images'] = ['nullable', 'array', 'max:5'];
        $rules['other_images'] = ['nullable', 'array', 'max:5'];
        foreach (['vehicle_images', 'other_images'] as $field) {
            $rules[$field.'.*'] = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
        }
        $rules['remove_documents'] = ['nullable', 'array', 'max:50'];
        $rules['remove_documents.*'] = ['integer', 'min:0'];
        if (! $this->boolean('device_enabled')) {
            return $rules;
        }
        $rules += [
            'device.device_model_id' => [$device ? 'nullable' : 'required', 'integer', 'exists:gps_device_models,id'],
            'device.identifier' => ['required', 'regex:/^[0-9]{15}$/D', Rule::unique('gps_devices', 'identifier')->ignore($device?->id), Rule::unique('gps_devices', 'imei')->ignore($device?->id)],
            'device.status' => ['required', Rule::in(['active', 'inactive', 'maintenance', 'blocked'])],
            'device.sim_number' => ['nullable', 'string', 'max:30', 'regex:/^[+0-9 ()-]+$/D'],
            'device.sim_iccid' => ['nullable', 'string', 'max:30'],
            'device.network_provider' => ['nullable', Rule::in(config('fleet.providers'))],
            'device.sim_generation' => ['nullable', Rule::in(config('fleet.networks'))],
            'device.serial_number' => ['nullable', 'string', 'max:100'],
            'device.vts' => ['nullable', 'string', 'max:100'],
            'device.firmware_version' => ['nullable', 'string', 'max:100'],
            'device.apn' => ['nullable', 'string', 'max:255'],
            'device.reporting_interval' => ['nullable', 'integer', 'between:1,86400'],
            'device.heartbeat_interval' => ['nullable', 'integer', 'between:1,86400'],
        ];

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator) {
            if (! $this->boolean('device_enabled')) {
                return;
            }
            if ($validator->errors()->has('device.device_model_id')) {
                return;
            }
            $installed = $this->route('vehicle')?->gpsDevice ?? $this->route('gpsDevice');
            $model = GpsDeviceModel::find($this->input('device.device_model_id'));
            if ($model && ! $model->active && $installed?->gps_device_model_id !== $model->id) {
                $validator->errors()->add('device.device_model_id', 'Select an active device model.');
            }
            if ($this->input('device.status') !== 'active') {
                return;
            }
            $protocol = $model?->protocol ?? $installed?->protocol;
            $version = $model?->protocol_version ?? $installed?->protocol_version;
            if (! in_array($version, config('gps.protocols.'.$protocol.'.versions', []), true)) {
                $validator->errors()->add('device.device_model_id', 'This model has no supported protocol version. Save the device inactive or select a supported model.');
            }
            if ($protocol === 'concox-x1' && strtoupper($model?->name ?? $installed?->model ?? '') !== 'X1') {
                $validator->errors()->add('device.device_model_id', 'The Concox adapter supports model X1 only.');
            }
        }];
    }
}
