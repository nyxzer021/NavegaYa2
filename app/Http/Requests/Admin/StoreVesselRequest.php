<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVesselRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vesselId = $this->route('vessel')?->id;

        return [
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('type', 'transport_company')->where('status', 'active')],
            'base_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'name' => ['required', 'string', 'max:120'],
            'registration_number' => ['required', 'string', 'max:80', Rule::unique('vessels', 'registration_number')->ignore($vesselId)],
            'vessel_type' => ['required', Rule::in(['lancha', 'motonave', 'rapido', 'ferry', 'otro'])],
            'hull_material' => ['nullable', 'string', 'max:80'],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:2000'],
            'crew_capacity' => ['nullable', 'integer', 'min:0', 'max:200'],
            'gross_tonnage' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'length_m' => ['nullable', 'numeric', 'gt:0', 'max:99999.99'],
            'beam_m' => ['nullable', 'numeric', 'gt:0', 'max:99999.99'],
            'draft_m' => ['nullable', 'numeric', 'gt:0', 'max:9999.99'],
            'engine_description' => ['nullable', 'string', 'max:180'],
            'manufacture_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'insurance_policy' => ['nullable', 'string', 'max:120'],
            'insurance_expires_at' => ['nullable', 'date'],
            'inspection_expires_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
