<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Escribe solo números en el teléfono (por ejemplo 965 111 222).',
            'document_number.digits' => 'El DNI tiene 8 dígitos.',
            'document_type.required_with' => 'Elige el tipo de documento.',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+ ]{6,20}$/'],
            'document_type' => ['nullable', 'required_with:document_number', Rule::in(['DNI', 'CE', 'Pasaporte'])],
            'document_number' => ['nullable', 'string', 'max:20', Rule::when($this->input('document_type') === 'DNI', ['digits:8'])],
        ];
    }
}
