<?php

namespace App\Http\Requests\Admin;

use App\Models\GpsDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', GpsDevice::class);
    }

    public static function detailRules(): array
    {
        $rules = [];
        foreach (['type', 'manufacturer', 'model', 'color', 'fuel_type', 'vin', 'engine_number', 'owner_name',
            'owner_phone', 'driver_name', 'driver_phone', 'driver_licence', 'fleet', 'branch', 'company'] as $key) {
            $rules['details.'.$key] = ['nullable', 'string', 'max:255'];
        }
        $rules['details.year'] = ['nullable', 'integer', 'between:1900,'.(now()->year + 1)];
        $rules['details.installation_date'] = ['nullable', 'date_format:Y-m-d'];
        $rules['details.initial_odometer'] = ['nullable', 'numeric', 'min:0'];
        $rules['details.notes'] = ['nullable', 'string', 'max:10000'];

        return $rules;
    }

    public function rules(): array
    {
        return ['registration_number' => ['required', 'string', 'max:255', Rule::unique('vehicles')->ignore($this->route('vehicle')?->id)],
            'name' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in(['active', 'inactive', 'maintenance'])],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'details' => ['nullable', 'array'], 'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], ...self::detailRules()];
    }
}
