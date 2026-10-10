<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:180'],
            'contact_name' => ['required', 'string', 'max:180'],
            'commercial_name' => ['nullable', 'string', 'max:180'],
            'ruc' => ['required', 'regex:/^[0-9]{11}$/', 'unique:organizations,ruc'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'modality' => ['required', Rule::in(['fluvial', 'aereo', 'mixto'])],
            'base_city' => ['required', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'ruc_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'representative_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'operating_permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'terms' => ['accepted'],
        ];
    }
}
