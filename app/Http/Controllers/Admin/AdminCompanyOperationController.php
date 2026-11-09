<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\OrganizationModality;
use App\Models\OrganizationSite;
use App\Support\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCompanyOperationController extends Controller
{
    public function modalities(Request $request): View
    {
        $modalities = OrganizationModality::query()
            ->with('organization')
            ->where('status', 'requested')
            ->latest('updated_at')
            ->get();

        return view('admin.companies.modalities', [
            'modalities' => $modalities,
        ]);
    }

    public function expiringDocuments(): View
    {
        $until = now()->addDays(30)->toDateString();
        $today = now()->toDateString();

        return view('admin.companies.expiring-documents', [
            'documents' => OrganizationDocument::query()->with('organization')->whereBetween('expires_on', [$today, $until])->orderBy('expires_on')->get(),
            'modalities' => OrganizationModality::query()->with('organization')->where(function ($query) use ($today, $until): void {
                $query->whereBetween('permit_expires_on', [$today, $until])->orWhereBetween('aoc_expires_on', [$today, $until]);
            })->orderBy('permit_expires_on')->get(),
        ]);
    }

    public function reviewModality(Request $request, Organization $organization, OrganizationModality $modality): RedirectResponse
    {
        abort_unless($modality->organization_id === $organization->id, 404);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Escribe el motivo de la decisión.',
            'decision.required' => 'Elige aprobar o rechazar.',
        ]);

        $modality->update([
            'status' => $data['decision'],
            'rejection_reason' => $data['decision'] === 'rejected' ? $data['reason'] : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $organization->syncLegacyModality();
        AuditRecorder::record($request, 'modality_reviewed', $modality, [
            'organization_id' => $organization->id,
            'code' => $modality->code,
            'decision' => $data['decision'],
            'reason' => $data['reason'],
        ]);

        return back()->with('success', $data['decision'] === 'approved' ? 'Modalidad aprobada.' : 'Modalidad rechazada.');
    }

    public function hideSite(Request $request, Organization $organization, OrganizationSite $site): RedirectResponse
    {
        abort_unless($site->organization_id === $organization->id, 404);
        $data = $request->validate([
            'hidden' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Escribe el motivo.',
        ]);
        $site->update([
            'hidden_by_admin' => $request->boolean('hidden'),
            'hidden_reason' => $data['reason'],
            'hidden_by' => $request->user()->id,
            'hidden_at' => now(),
        ]);
        AuditRecorder::record($request, 'site_visibility_changed', $site, [
            'organization_id' => $organization->id,
            'hidden' => $site->hidden_by_admin,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', $site->hidden_by_admin ? 'La sede quedó oculta en Agencias.' : 'La sede volvió a poder mostrarse.');
    }

    public function hideWhatsapp(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'hidden' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Escribe el motivo. Un reporte de fraude también va aquí.',
        ]);
        $organization->update([
            'whatsapp_hidden_by_admin' => $request->boolean('hidden'),
            'whatsapp_hidden_reason' => $data['reason'],
            'whatsapp_hidden_by' => $request->user()->id,
            'whatsapp_hidden_at' => now(),
        ]);
        AuditRecorder::record($request, 'whatsapp_public_hidden', $organization, [
            'hidden' => $organization->whatsapp_hidden_by_admin,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', $organization->whatsapp_hidden_by_admin ? 'El botón público de WhatsApp quedó oculto.' : 'El botón público de WhatsApp volvió a depender de la empresa.');
    }

    public function downloadPermit(Organization $organization, OrganizationModality $modality, string $kind): StreamedResponse
    {
        abort_unless($modality->organization_id === $organization->id, 404);
        $path = $kind === 'aoc' ? $modality->aoc_path : $modality->permit_path;
        $name = $kind === 'aoc' ? $modality->aoc_original_name : $modality->permit_original_name;
        abort_unless(is_string($path) && $path !== '', 404);

        return Storage::download($path, $name ?: 'documento');
    }
}
