<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->roles()->where('code', 'super_admin')->exists() ?? false;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:geo_departments,id'],
            'province_id' => ['required', 'integer', 'exists:geo_provinces,id'],
            'district_id' => ['required', 'integer', 'exists:geo_districts,id'],
            'name' => ['required', 'string', 'max:150'],
            'modality' => ['required', Rule::in(['fluvial', 'aereo'])],
            'port_type' => ['required', Rule::in(['terminal', 'embarcadero', 'muelle', 'muelle_flotante', 'privado', 'aerodromo', 'otro'])],
            'operator_name' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'operating_hours' => ['nullable', 'string', 'max:120'],
            'river' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'access_notes' => ['nullable', 'string', 'max:1000'],
            'available_services' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
