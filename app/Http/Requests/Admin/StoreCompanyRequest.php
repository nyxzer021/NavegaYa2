<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->roles()->where('code', 'super_admin')->exists() ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'ruc' => ['required', 'digits:11', Rule::unique('organizations', 'ruc')],
            'modality' => ['required', Rule::in(['fluvial', 'aereo', 'mixto'])],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'base_city' => ['required', 'string', 'max:100'],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
