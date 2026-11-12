<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouteDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];
        $departure = $this->joined('departure_date', 'departure_time');
        if ($departure !== null) {
            $merged['departure_at'] = $departure;
            if ($this->joined('boarding_date', 'boarding_time') === null) {
                $merged['boarding_starts_at'] = Carbon::parse($departure)->subMinutes(45)->format('Y-m-d H:i:s');
            }
            if ($this->joined('arrival_date', 'arrival_time') === null) {
                $merged['estimated_arrival_at'] = Carbon::parse($departure)->addHours(3)->format('Y-m-d H:i:s');
            }
        }
        foreach ([
            'boarding_starts_at' => ['boarding_date', 'boarding_time'],
            'estimated_arrival_at' => ['arrival_date', 'arrival_time'],
            'cargo_reception_starts_at' => ['cargo_date', 'cargo_time'],
        ] as $target => $parts) {
            $value = $this->joined($parts[0], $parts[1]);
            if ($value !== null) {
                $merged[$target] = $value;
            }
        }

        $this->merge($merged);
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'boarding_starts_at.before_or_equal' => 'El inicio de embarque debe ser antes o a la misma hora de la salida.',
            'estimated_arrival_at.after' => 'La llegada estimada debe ser después de la salida.',
            'cargo_reception_starts_at.before_or_equal' => 'La recepción de carga debe empezar antes o a la misma hora de la salida.',
            'fare.min' => 'El precio del pasaje debe ser mayor que cero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'master_route_id' => 'tramo',
            'vessel_id' => 'embarcación',
            'departure_at' => 'fecha y hora de salida',
            'boarding_starts_at' => 'inicio de embarque',
            'estimated_arrival_at' => 'llegada estimada',
            'cargo_reception_starts_at' => 'inicio de recepción de carga',
            'fare' => 'precio del pasaje',
            'notes' => 'nota para pasajeros',
        ];
    }

    private function joined(string $date, string $time): ?string
    {
        if (! $this->filled($date) || ! $this->filled($time)) {
            return null;
        }

        return Carbon::parse($this->input($date).' '.$this->input($time), 'America/Lima')->utc()->format('Y-m-d H:i:s');
    }
}
