<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouteDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_route_id' => ['required', 'integer', 'exists:master_routes,id'],
            'vessel_id' => ['required', 'integer', 'exists:vessels,id'],
            'departure_at' => ['required', 'date'],
            'boarding_starts_at' => ['required', 'date', 'before_or_equal:departure_at'],
            'cargo_reception_starts_at' => ['nullable', 'date', 'before_or_equal:departure_at'],
            'estimated_arrival_at' => ['nullable', 'date', 'after:departure_at'],
            'fare' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'cargo_enabled' => ['nullable', 'boolean'],
            'cargo_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
