<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerateVesselSeatLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'integer', 'min:1', 'max:100'],
            'left_seats' => ['required', 'integer', 'min:1', 'max:6'],
            'right_seats' => ['required', 'integer', 'min:1', 'max:6'],
            'deck' => ['required', 'in:principal,superior'],
            'seat_class' => ['required', 'in:standard,premium,business'],
            'replace_existing' => ['nullable', 'boolean'],
        ];
    }
}
