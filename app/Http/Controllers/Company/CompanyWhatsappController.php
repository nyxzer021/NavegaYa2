<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Support\AdminScope;
use App\Support\AuditRecorder;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyWhatsappController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organization($request);

        return view('company.whatsapp', [
            'organization' => $organization,
            'lastChange' => AuditLog::query()
                ->where('subject_type', $organization->getMorphClass())
                ->where('subject_id', $organization->id)
                ->where('event', 'company_whatsapp_updated')
                ->with('user')
                ->orderByDesc('created_at')
                ->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'whatsapp' => ['required', 'string', 'max:30'],
            'whatsapp_cargo' => ['nullable', 'string', 'max:30'],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'whatsapp_hours_from' => ['nullable', 'date_format:H:i'],
            'whatsapp_hours_until' => ['nullable', 'date_format:H:i'],
            'show_public_whatsapp' => ['nullable', 'boolean'],
            'whatsapp_show_outside_hours' => ['nullable', 'boolean'],
        ], [
            'whatsapp.required' => 'Indica el WhatsApp de boletería.',
        ]);

        $ticket = PhoneNumber::peru($data['whatsapp']);
        if ($ticket === null) {
            return back()->withInput()->withErrors(['whatsapp' => 'Ingresa un celular peruano de 9 dígitos que empiece con 9.']);
        }
        $cargo = null;
        if (filled($data['whatsapp_cargo'] ?? null)) {
            $cargo = PhoneNumber::peru($data['whatsapp_cargo']);
            if ($cargo === null) {
                return back()->withInput()->withErrors(['whatsapp_cargo' => 'Ingresa un celular peruano de 9 dígitos que empiece con 9.']);
            }
        }

        $organization->update([
            'whatsapp' => $ticket,
            'whatsapp_cargo' => $cargo,
            'whatsapp_message' => $data['whatsapp_message'] ?? null,
            'whatsapp_hours_from' => $data['whatsapp_hours_from'] ?? null,
            'whatsapp_hours_until' => $data['whatsapp_hours_until'] ?? null,
            'show_public_whatsapp' => $request->boolean('show_public_whatsapp'),
            'whatsapp_show_outside_hours' => $request->boolean('whatsapp_show_outside_hours'),
        ]);
        AuditRecorder::record($request, 'company_whatsapp_updated', $organization, [
            'whatsapp' => $ticket,
            'whatsapp_cargo' => $cargo,
            'show_public_whatsapp' => $organization->show_public_whatsapp,
        ]);

        return back()->with('success', 'WhatsApp de atención guardado.');
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
