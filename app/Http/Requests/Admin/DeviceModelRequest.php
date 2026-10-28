<?php

namespace App\Http\Requests\Admin;

use App\Models\GpsDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', GpsDevice::class);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255', Rule::unique('gps_device_models')->ignore($this->route('deviceModel')?->id)],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'protocol' => ['required', Rule::in([...array_keys(config('gps.protocols')), 'gt05', 'tk103'])],
            'protocol_version' => ['nullable', 'string', 'max:20'],
            'network_generation' => ['nullable', Rule::in(config('fleet.networks'))],
            'active' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
