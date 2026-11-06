<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('modalities') && ! $this->filled('modality')) {
            $this->merge(['modality' => Organization::legacyFromCodes((array) $this->input('modalities'))]);
        }
        if ($this->filled('whatsapp')) {
            $this->merge(['whatsapp' => PhoneNumber::peru($this->input('whatsapp'))]);
        }
    }

    /**
     * @return list<string>
     */
    public function modalityCodes(): array
    {
        $codes = array_values(array_unique(array_filter((array) $this->input('modalities'))));
        if ($codes !== []) {
            return $codes;
        }

        return match ($this->input('modality')) {
            'aereo' => ['aereo'],
            'mixto' => ['fluvial', 'aereo'],
            'fluvial' => ['fluvial'],
            default => [],
        };
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
            'modalities' => ['nullable', 'array'],
            'modalities.*' => [Rule::in(['fluvial', 'aereo', 'aereo_fluvial'])],
            'base_city' => ['required', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'ruc_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'representative_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'operating_permit' => ['required_without:modalities', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'fluvial_permit_scope' => ['nullable', Rule::in(['regional', 'national'])],
            'fluvial_permit_number' => ['nullable', 'string', 'max:80'],
            'fluvial_permit_expires_on' => ['nullable', 'date'],
            'fluvial_permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aereo_permit_number' => ['nullable', 'string', 'max:80'],
            'aereo_permit_expires_on' => ['nullable', 'date'],
            'aereo_permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aereo_aoc_number' => ['nullable', 'string', 'max:80'],
            'aereo_aoc_expires_on' => ['nullable', 'date'],
            'aereo_aoc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aereo_fluvial_permit_number' => ['nullable', 'string', 'max:80'],
            'aereo_fluvial_permit_expires_on' => ['nullable', 'date'],
            'aereo_fluvial_permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aereo_fluvial_aoc_number' => ['nullable', 'string', 'max:80'],
            'aereo_fluvial_aoc_expires_on' => ['nullable', 'date'],
            'aereo_fluvial_aoc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'terms' => ['accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('modalities')) {
                return;
            }
            foreach ($this->modalityCodes() as $code) {
                $prefix = $code === 'fluvial' ? 'fluvial' : $code;
                $permit = $code === 'fluvial' ? ($this->file('fluvial_permit') ?: $this->file('operating_permit')) : $this->file($prefix.'_permit');
                if (! $permit || ! $this->filled($prefix.'_permit_number') || ! $this->filled($prefix.'_permit_expires_on')) {
                    $validator->errors()->add($prefix.'_permit', 'Completa el permiso de la modalidad '.$code.'.');
                }
                if ($code !== 'fluvial' && (! $this->file($prefix.'_aoc') || ! $this->filled($prefix.'_aoc_number') || ! $this->filled($prefix.'_aoc_expires_on'))) {
                    $validator->errors()->add($prefix.'_aoc', 'Completa el AOC de la modalidad '.$code.'.');
                }
            }
        });
    }
}
