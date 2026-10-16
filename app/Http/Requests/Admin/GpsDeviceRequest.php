<?php

namespace App\Http\Requests\Admin;

use App\Models\GpsDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GpsDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', GpsDevice::class);
    }

    public function rules(): array
    {
        $device = $this->route('gpsDevice');
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['required', 'regex:/^[0-9]{15}$/D', Rule::unique('gps_devices')->ignore($device?->id)],
            'imei' => ['nullable', 'same:identifier', 'regex:/^[0-9]{15}$/D', Rule::unique('gps_devices')->ignore($device?->id)],
            'manufacturer' => ['nullable', 'string', 'max:255'], 'model' => ['nullable', 'string', 'max:255'],
            'protocol' => ['required', Rule::in(array_keys(config('gps.protocols')))],
            'protocol_version' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive', 'maintenance', 'blocked'])],
            'vehicle_id' => ['nullable', 'exists:vehicles,id', Rule::unique('gps_devices', 'vehicle_id')->ignore($device?->id)],
            'new_vehicle.registration_number' => ['required_with:new_vehicle.name', 'nullable', 'string', 'max:255', 'unique:vehicles,registration_number'],
            'new_vehicle.name' => ['required_with:new_vehicle.registration_number', 'nullable', 'string', 'max:255'],
            'new_vehicle.status' => ['nullable', Rule::in(['active', 'inactive', 'maintenance'])],
            'details' => ['nullable', 'array'], 'configuration' => ['required', 'array'],
            'configuration.transport' => ['required', Rule::in(['tcp', 'udp'])],
            'configuration.server_host' => ['nullable', 'string', 'max:253', 'regex:/^[a-zA-Z0-9.:\-]+$/D'],
            'configuration.server_port' => ['required', 'integer', 'between:1,65535'],
            'configuration.reporting_interval' => ['nullable', 'integer', 'between:1,86400'],
            'configuration.heartbeat_interval' => ['nullable', 'integer', 'between:1,86400'],
            'configuration.timezone' => ['required', 'timezone:all'],
            'configuration.apn' => ['nullable', 'string', 'max:255'],
            // No remote command is supported without a model-specific command adapter.
            'configuration.commands' => ['prohibited'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
        foreach (['serial_number', 'category', 'sim_number', 'sim_iccid', 'network_provider', 'firmware_version'] as $field) {
            $rules['details.'.$field] = ['nullable', 'string', 'max:255'];
        }
        $rules['details.installation_date'] = ['nullable', 'date_format:Y-m-d'];
        $rules['details.notes'] = ['nullable', 'string', 'max:10000'];
        foreach (VehicleRequest::detailRules() as $field => $rule) {
            $rules['new_vehicle.'.$field] = $rule;
        }

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->input('vehicle_id') && $this->filled('new_vehicle.registration_number')) {
                $validator->errors()->add('vehicle_id', 'Select an existing vehicle or create one, not both.');
            }
            if ($this->input('status') === 'active') {
                $versions = config('gps.protocols.'.$this->input('protocol').'.versions', []);
                if (! in_array($this->input('protocol_version'), $versions, true)) {
                    $validator->errors()->add('protocol_version', 'An active tracker requires a supported, verified protocol version.');
                }
                if ($this->input('configuration.transport') !== 'tcp') {
                    $validator->errors()->add('configuration.transport', 'UDP is not implemented.');
                }
                if ((int) $this->input('configuration.server_port') !== 9000) {
                    $validator->errors()->add('configuration.server_port', 'The shared hardware gateway uses TCP port 9000.');
                }
                if (! $this->filled('model')) {
                    $validator->errors()->add('model', 'Record the exact model before activation.');
                }
                if ($this->input('protocol') === 'concox-x1' && strtoupper((string) $this->input('model')) !== 'X1') {
                    $validator->errors()->add('model', 'This adapter supports Concox X1 only.');
                }
                if ($this->input('protocol') === 'concox-x1' && $this->input('configuration.timezone') !== 'UTC') {
                    $validator->errors()->add('configuration.timezone', 'The X1 decoder currently requires UTC device timestamps.');
                }
            }
        }];
    }
}
