<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAirBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'contact_document' => ['nullable', 'string', 'max:40'],
            'seats' => ['required', 'array', 'min:1', 'max:10'],
            'seats.*' => ['required', 'integer', 'distinct', 'exists:aircraft_seats,id'],
            'passengers' => ['required', 'array'],
            'passengers.*.name' => ['required', 'string', 'max:150'],
            'passengers.*.document' => ['required', 'string', 'max:40'],
        ];
    }
}
