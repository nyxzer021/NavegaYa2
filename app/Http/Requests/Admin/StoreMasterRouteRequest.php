<?php

namespace App\Http\Requests\Admin;

use App\Models\Port;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMasterRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->roles()->where('code', 'super_admin')->exists() ?? false;
    }

    public function rules(): array
    {
        $masterRoute = $this->route('master_route');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('master_routes')->ignore($masterRoute)],
            'modality' => ['required', Rule::in(['fluvial', 'aereo'])],
            'origin_city' => ['required', 'string', 'max:120', 'different:destination_city'],
            'destination_city' => ['required', 'string', 'max:120', 'different:origin_city'],
            'origin_port_id' => ['required', 'integer', 'exists:ports,id', 'different:destination_port_id'],
            'destination_port_id' => ['required', 'integer', 'exists:ports,id', 'different:origin_port_id'],
            'river_basin' => ['nullable', 'string', 'max:160'],
            'corridor' => ['nullable', 'string', 'max:160'],
            'estimated_duration_text' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['active', 'suspended_river_level', 'maintenance'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['origin_port_id', 'destination_port_id'])) {
                return;
            }

            $origin = Port::find($this->integer('origin_port_id'));
            $destination = Port::find($this->integer('destination_port_id'));
            if ($origin && mb_strtolower(trim($origin->city)) !== mb_strtolower(trim((string) $this->input('origin_city')))) {
                $validator->errors()->add('origin_port_id', 'El terminal de origen debe pertenecer a la ciudad de origen.');
            }
            if ($destination && mb_strtolower(trim($destination->city)) !== mb_strtolower(trim((string) $this->input('destination_city')))) {
                $validator->errors()->add('destination_port_id', 'El terminal de destino debe pertenecer a la ciudad de destino.');
            }
        }];
    }
}
