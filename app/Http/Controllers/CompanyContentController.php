<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationPageSection;
use App\Models\PolicyVersion;
use App\Support\AdminScope;
use App\Support\AuditRecorder;
use App\Support\BaggageAllowance;
use App\Support\PolicyHtml;
use App\Support\TravelPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyContentController extends Controller
{
    public function sections(Request $request): View
    {
        $organization = $this->organization($request);
        $this->ensureSections($organization);

        return view('company.content.sections', [
            'organization' => $organization,
            'sections' => $organization->pageSections()->orderBy('position')->get(),
        ]);
    }

    public function updateSections(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $this->ensureSections($organization);
        $data = $request->validate([
            'keys' => ['required', 'array'],
            'keys.*' => ['string'],
            'labels' => ['required', 'array'],
            'enabled' => ['nullable', 'array'],
        ]);
        foreach (array_values($data['keys']) as $position => $key) {
            $section = $organization->pageSections()->where('key', $key)->first();
            if (! $section) {
                continue;
            }
            $section->update([
                'position' => $position,
                'label' => $data['labels'][$key] ?? $section->label,
                'is_enabled' => $section->isMandatory() || $request->boolean('enabled.'.$key),
            ]);
        }

        return back()->with('success', 'Las secciones de la página quedaron guardadas.');
    }

    public function gallery(Request $request): View
    {
        $organization = $this->organization($request)->load(['photos', 'banners', 'albums']);

        return view('company.content.gallery', compact('organization'));
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:80']]);
        $organization->albums()->create([
            'title' => $data['title'],
            'position' => (int) $organization->albums()->max('position') + 1,
        ]);

        return back()->with('success', 'El álbum quedó creado.');
    }

    public function policies(Request $request): View
    {
        $organization = $this->organization($request);
        $document = TravelPolicy::ensureCompany($organization);
        $notice = AuditLog::query()
            ->where('event', 'company_travel_policy_updated')
            ->where('subject_type', $organization->getMorphClass())
            ->where('subject_id', $organization->id)
            ->latest('id')
            ->first();

        return view('company.content.policies', [
            'organization' => $organization,
            'document' => $document,
            'current' => $document->current(),
            'versions' => $document->versions()->orderByDesc('version')->get(),
            'baggage' => BaggageAllowance::for($organization),
            'notice' => $notice,
        ]);
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $document = TravelPolicy::ensureCompany($organization);
        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:50000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && PolicyHtml::hasPlaceholders($value)) {
                    $fail(trans('company_portal.policy.brackets', [], 'es'));
                }
            }],
            'summary' => ['nullable', 'string', 'max:300'],
            'baggage_hand_kg' => ['nullable', 'integer', 'min:0', 'max:100'],
            'baggage_hold_kg' => ['nullable', 'integer', 'min:0', 'max:200'],
            'baggage_air_kg' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cargo_threshold_kg' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);
        TravelPolicy::publish($document, $data['body_html'], $data['summary'] ?? null, $request->user(), 'Publicada por la empresa');
        $organization->update([
            'baggage_hand_kg' => $data['baggage_hand_kg'] ?? null,
            'baggage_hold_kg' => $data['baggage_hold_kg'] ?? null,
            'baggage_air_kg' => $data['baggage_air_kg'] ?? null,
            'cargo_threshold_kg' => $data['cargo_threshold_kg'] ?? null,
        ]);

        return back()->with('success', 'La política de viaje quedó publicada como una versión nueva.');
    }

    public function restorePolicy(Request $request, PolicyVersion $version): RedirectResponse
    {
        $organization = $this->organization($request);
        $document = TravelPolicy::ensureCompany($organization);
        abort_unless((int) $version->policy_document_id === (int) $document->id, 404);
        TravelPolicy::publish($document, $version->body_html, 'Restauración de la versión '.$version->version, $request->user(), 'Restauración de la versión '.$version->version);

        return back()->with('success', 'La versión '.$version->version.' volvió a publicarse como una versión nueva.');
    }

    public function appearance(Request $request): View
    {
        return view('company.content.appearance', [
            'organization' => $this->organization($request),
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        if ($request->boolean('reset')) {
            $organization->update(['public_primary_color' => null, 'public_accent_color' => null]);

            return back()->with('success', 'La página volvió a los colores de NavegaYA.');
        }
        $data = $request->validate([
            'public_primary_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            'public_accent_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
        ], [
            'public_primary_color.regex' => 'El color principal debe ser un código hexadecimal.',
            'public_accent_color.regex' => 'El color de acento debe ser un código hexadecimal.',
        ]);
        $organization->update($data);
        AuditRecorder::record($request, 'company_appearance_updated', $organization, $data);

        return back()->with('success', 'Los colores quedaron aplicados solo a la página de esta empresa.');
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

    private function ensureSections(Organization $organization): void
    {
        $this->renameSection($organization, 'experiencias', 'galeria', 'Galería');
        $this->renameSection($organization, 'donde-comprar', 'agencias', 'Agencias');
        $organization->pageSections()->whereIn('key', ['politicas', 'opiniones', 'testimonios'])->delete();
        $defaults = [
            ['key' => 'salidas', 'label' => 'Salidas y tarifas'],
            ['key' => 'informacion', 'label' => 'Información'],
            ['key' => 'flota', 'label' => 'Flota'],
            ['key' => 'galeria', 'label' => 'Galería'],
            ['key' => 'agencias', 'label' => 'Agencias'],
            ['key' => 'carga', 'label' => 'Encomiendas y carga'],
        ];
        foreach ($defaults as $position => $section) {
            OrganizationPageSection::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'key' => $section['key']],
                ['label' => $section['label'], 'position' => $position, 'is_enabled' => true],
            );
        }
    }

    private function renameSection(Organization $organization, string $from, string $to, string $label): void
    {
        $legacy = $organization->pageSections()->where('key', $from)->first();
        if (! $legacy) {
            return;
        }
        $current = $organization->pageSections()->where('key', $to)->first();
        if ($current) {
            $legacy->delete();

            return;
        }
        $legacy->update(['key' => $to, 'label' => $label]);
    }
}
