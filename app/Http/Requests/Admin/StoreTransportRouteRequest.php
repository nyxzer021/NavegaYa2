<?php

namespace App\Http\Requests\Admin;

use App\Models\TransportRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransportRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var TransportRoute|null $route */
        $route = $this->route('transportRoute');

        return [
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('type', 'transport_company')->where('status', 'active')],
            'origin_city' => ['required', 'string', 'max:100', 'different:destination_city'],
            'origin_port_id' => ['required', 'integer', 'different:destination_port_id', 'exists:ports,id'],
            'destination_city' => ['required', 'string', 'max:100'],
            'destination_port_id' => ['required', 'integer', 'exists:ports,id'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'distance_km' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
