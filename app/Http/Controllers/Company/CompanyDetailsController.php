<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\Port;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyDetailsController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organization($request);
        $organization->load(['modalities', 'documents', 'basePort']);

        return view('company.details', [
            'organization' => $organization,
            'ports' => Port::query()->where('is_active', true)->orderBy('name')->orderBy('city')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:180'],
            'commercial_name' => ['nullable', 'string', 'max:180'],
            'base_port_id' => ['nullable', 'integer', Rule::exists('ports', 'id')],
            'address' => ['required', 'string', 'max:255'],
            'base_city' => ['required', 'string', 'max:100'],
            'contact_name' => ['required', 'string', 'max:180'],
            'representative_document_number' => ['nullable', 'string', 'max:20'],
            'representative_email' => ['nullable', 'email', 'max:255'],
            'representative_phone' => ['nullable', 'string', 'max:30'],
            'ruc_expires_on' => ['nullable', 'date'],
            'representative_expires_on' => ['nullable', 'date'],
            'ruc_reference' => ['nullable', 'string', 'max:80'],
            'representative_reference' => ['nullable', 'string', 'max:80'],
            'ruc_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'representative_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'legal_name.required' => 'Indica la razón social.',
            'address.required' => 'Indica la dirección fiscal.',
            'base_city.required' => 'Indica la ciudad.',
            'contact_name.required' => 'Indica al representante legal.',
        ]);

        $organization->update(collect($data)->only([
            'legal_name', 'commercial_name', 'base_port_id', 'address', 'base_city',
            'contact_name', 'representative_document_number', 'representative_email', 'representative_phone',
        ])->all());

        $this->storeDocument($request, $organization, 'ruc_document', 'ruc', $data['ruc_expires_on'] ?? null, $data['ruc_reference'] ?? null);
        $this->storeDocument($request, $organization, 'representative_document', 'representative', $data['representative_expires_on'] ?? null, $data['representative_reference'] ?? null);

        return back()->with('success', 'Datos de la empresa guardados.');
    }

    public function storeModality(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'code' => ['required', Rule::in(['fluvial', 'aereo', 'aereo_fluvial'])],
            'permit_scope' => ['nullable', Rule::in(['regional', 'national'])],
            'permit_number' => ['required', 'string', 'max:80'],
            'permit_expires_on' => ['required', 'date'],
            'permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aoc_number' => ['nullable', 'required_unless:code,fluvial', 'string', 'max:80'],
            'aoc_expires_on' => ['nullable', 'required_unless:code,fluvial', 'date'],
            'aoc' => ['nullable', 'required_unless:code,fluvial', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'code.required' => 'Elige la modalidad.',
            'permit_number.required' => 'Indica el número del permiso.',
            'permit_expires_on.required' => 'Indica el vencimiento del permiso.',
            'permit.required' => 'Adjunta el permiso.',
            'aoc_number.required_unless' => 'Indica el número del AOC.',
            'aoc_expires_on.required_unless' => 'Indica el vencimiento del AOC.',
            'aoc.required_unless' => 'Adjunta el AOC.',
        ]);

        $existing = $organization->modalities()->where('code', $data['code'])->first();
        if ($existing && $existing->status === 'approved') {
            return back()->withErrors(['code' => 'Esa modalidad ya está aprobada.']);
        }

        $permit = $request->file('permit');
        $payload = [
            'status' => 'requested',
            'permit_scope' => $data['code'] === 'fluvial' ? ($data['permit_scope'] ?? null) : null,
            'permit_number' => $data['permit_number'],
            'permit_expires_on' => $data['permit_expires_on'],
            'permit_path' => $permit->store('organization-documents/'.$organization->id),
            'permit_original_name' => $permit->getClientOriginalName(),
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
        if ($data['code'] !== 'fluvial') {
            $aoc = $request->file('aoc');
            $payload['aoc_number'] = $data['aoc_number'];
            $payload['aoc_expires_on'] = $data['aoc_expires_on'];
            $payload['aoc_path'] = $aoc->store('organization-documents/'.$organization->id);
            $payload['aoc_original_name'] = $aoc->getClientOriginalName();
        }

        $organization->modalities()->updateOrCreate(['code' => $data['code']], $payload);

        return back()->with('success', 'Solicitud de modalidad enviada. NavegaYA la revisará.');
    }

    private function storeDocument(Request $request, Organization $organization, string $field, string $type, ?string $expiresOn, ?string $reference): void
    {
        $document = $organization->documents()->where('type', $type)->latest('id')->first();
        if (! $request->hasFile($field) && $expiresOn === null && $reference === null) {
            return;
        }
        if ($document && ! $request->hasFile($field)) {
            $document->update(array_filter([
                'expires_on' => $expiresOn,
                'reference_number' => $reference,
            ], fn ($value) => $value !== null));

            return;
        }
        if (! $request->hasFile($field)) {
            return;
        }
        $file = $request->file($field);
        OrganizationDocument::create([
            'organization_id' => $organization->id,
            'type' => $type,
            'path' => $file->store('organization-documents/'.$organization->id),
            'original_name' => $file->getClientOriginalName(),
            'expires_on' => $expiresOn,
            'reference_number' => $reference,
        ]);
    }

    private function organization(Request $request): Organization
    {
        abort_unless($request->user()->roles()->where('code', 'company_admin')->exists(), 403);

        return Organization::query()
            ->whereKey(AdminScope::organizationId($request->user()))
            ->where('type', 'transport_company')
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();
    }
}
