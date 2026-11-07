<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use App\Services\CompanyProvisioningService;
use App\Support\AuditRecorder;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCompanyController extends Controller
{
    public function create(): View
    {
        return view('admin.companies.create');
    }

    public function store(Request $request, CompanyProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'ruc' => ['required', 'digits:11', 'unique:organizations,ruc'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'service_hours' => ['nullable', 'string', 'max:180'],
            'show_public_whatsapp' => ['nullable', 'boolean'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'modality' => ['required', 'in:fluvial,aereo,mixto'],
            'base_city' => ['required', 'string', 'max:100'],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $data['whatsapp'] = PhoneNumber::peru($data['whatsapp'] ?? null);
        $data['show_public_whatsapp'] = $request->boolean('show_public_whatsapp');

        $provisioning->provision($data, true);

        return redirect()->route('admin.companies.index', ['tab' => 'rendimiento'])
            ->with('success', 'Empresa y cuenta administradora creadas correctamente.');
    }

    public function index(Request $request): View
    {
        $today = today();
        $successfulPayments = Payment::query()
            ->whereIn('status', ['confirmed', 'succeeded'])
            ->whereDate('paid_at', $today)
            ->with([
                'reservation.seats',
                'reservation.departure.transportRoute',
                'reservation.airDeparture.airRoute',
            ])
            ->get();

        $paymentByOrganization = $successfulPayments->groupBy(function (Payment $payment) {
            $reservation = $payment->reservation;

            return $reservation?->airDeparture?->airRoute?->organization_id
                ?? $reservation?->departure?->transportRoute?->organization_id;
        });

        $gmvToday = (float) $successfulPayments->sum('amount');
        $commissionToday = (float) $successfulPayments->sum('commission_amount');
        $ticketsToday = $successfulPayments->sum(fn (Payment $payment) => $payment->reservation?->seats?->count() ?? 0);
        $fluvialTicketsToday = $successfulPayments
            ->filter(fn (Payment $payment) => $payment->reservation?->route_departure_id !== null)
            ->sum(fn (Payment $payment) => $payment->reservation?->seats?->count() ?? 0);
        $airTicketsToday = $successfulPayments
            ->filter(fn (Payment $payment) => $payment->reservation?->air_departure_id !== null)
            ->sum(fn (Payment $payment) => $payment->reservation?->seats?->count() ?? 0);

        $activeOperatorsToday = Organization::query()
            ->where('type', 'transport_company')
            ->where('status', 'active')
            ->where(function ($query) use ($today) {
                $query->whereHas('routeDepartures', fn ($departures) => $departures->whereDate('departure_at', $today))
                    ->orWhereHas('airDepartures', fn ($departures) => $departures->whereDate('departure_at', $today));
            })
            ->count();

        $companies = Organization::query()->where('type', 'transport_company')
            ->whereIn('status', ['active', 'suspended'])
            ->with([
                'users.roles',
                'routeDepartures' => fn ($query) => $query->whereDate('departure_at', $today)->with(['vessel', 'reservations.seats']),
                'airDepartures' => fn ($query) => $query->whereDate('departure_at', $today)->with(['aircraft', 'reservations.seats']),
            ])
            ->withCount([
                'vessels as registered_vessels_count',
                'aircraft as registered_aircraft_count',
                'vessels as active_vessels_count' => fn ($query) => $query->where('status', 'ready'),
                'aircraft as active_aircraft_count' => fn ($query) => $query->where('status', 'ready'),
                'vessels as pending_vessels_count' => fn ($query) => $query->where('status', 'pending_review'),
                'routeDepartures',
                'airDepartures',
            ])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('legal_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('commercial_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('ruc', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(15)->withQueryString();

        $companies->setCollection($companies->getCollection()->map(function (Organization $organization) use ($paymentByOrganization) {
            $payments = $paymentByOrganization->get($organization->id, collect());
            $departures = $organization->routeDepartures->concat($organization->airDepartures);
            $capacity = $organization->routeDepartures->sum(fn ($departure) => (int) ($departure->vessel?->seat_capacity ?? 0))
                + $organization->airDepartures->sum(fn ($departure) => (int) ($departure->aircraft?->seat_capacity ?? 0));
            $soldSeats = $payments->sum(fn (Payment $payment) => $payment->reservation?->seats?->count() ?? 0);

            $organization->setAttribute('today_departures_count', $departures->count());
            $organization->setAttribute('today_tickets_count', $soldSeats);
            $organization->setAttribute('today_gmv', (float) $payments->sum('amount'));
            $organization->setAttribute('today_commission', (float) $payments->sum('commission_amount'));
            $organization->setAttribute('today_occupancy', $capacity > 0 ? min(100, (int) round(($soldSeats / $capacity) * 100)) : 0);

            return $organization;
        }));

        $pendingAffiliations = Organization::query()
            ->where('type', 'transport_company')
            ->whereIn('status', ['pending', 'pending_verification'])
            ->with(['users.roles'])
            ->latest()
            ->take(8)
            ->get();

        $pendingVesselReviews = Vessel::query()
            ->with(['organization', 'seats'])
            ->where('status', 'pending_review')
            ->oldest('submitted_for_review_at')
            ->get();

        $commissionSummary = Organization::query()
            ->where('type', 'transport_company')
            ->where('status', 'active')
            ->selectRaw("CASE WHEN modality = 'aereo' THEN 'aereo' ELSE 'fluvial' END AS channel, AVG(commission_rate) AS average_rate, COUNT(*) AS operators_count")
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');
        $commissionCompanies = Organization::query()
            ->where('type', 'transport_company')
            ->when($request->filled('commission_search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('legal_name', 'like', '%'.$request->string('commission_search').'%')
                ->orWhere('commercial_name', 'like', '%'.$request->string('commission_search').'%')
                ->orWhere('ruc', 'like', '%'.$request->string('commission_search').'%')))
            ->orderBy('commercial_name')
            ->orderBy('legal_name')
            ->get();

        return view('admin.companies.index', [
            'companies' => $companies,
            'pendingAffiliations' => $pendingAffiliations,
            'pendingVesselReviews' => $pendingVesselReviews,
            'gmvToday' => $gmvToday,
            'commissionToday' => $commissionToday,
            'ticketsToday' => $ticketsToday,
            'fluvialTicketsToday' => $fluvialTicketsToday,
            'airTicketsToday' => $airTicketsToday,
            'activeOperatorsToday' => $activeOperatorsToday,
            'commissionSummary' => $commissionSummary,
            'commissionCompanies' => $commissionCompanies,
        ]);
    }

    public function edit(Organization $organization): View
    {
        $organization->load(['users.roles', 'documents']);

        return view('admin.companies.edit', compact('organization'));
    }

    public function downloadDocument(Organization $organization, OrganizationDocument $document): StreamedResponse
    {
        abort_unless($document->organization_id === $organization->id, 404);

        return Storage::download($document->path, $document->original_name);
    }

    public function show(Organization $organization): View
    {
        $organization->load([
            'vessels' => fn ($query) => $query->latest(),
            'aircraft' => fn ($query) => $query->latest(),
            'modalities', 'sites', 'documents', 'basePort',
        ]);

        $travelDocument = $organization->travelPolicies()->where('kind', 'company_travel')->first();

        return view('admin.companies.show', [
            'organization' => $organization,
            'ports' => Port::query()->where('is_active', true)->orderBy('name')->orderBy('city')->get(),
            'travelVersions' => $travelDocument?->versions()->orderByDesc('version')->limit(5)->get() ?? collect(),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $admin = $organization->users->first(fn ($user) => $user->roles->contains('code', 'company_admin'));
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'service_hours' => ['nullable', 'string', 'max:180'],
            'show_public_whatsapp' => ['nullable', 'boolean'],
            'base_city' => ['required', 'string', 'max:100'],
            'modality' => ['required', 'in:fluvial,aereo,mixto'],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            'commercial_plan' => ['required', 'in:initial,standard,strategic,custom'],
            'agreement_number' => ['nullable', 'string', 'max:80'],
            'commission_starts_on' => ['nullable', 'date'],
            'commission_ends_on' => ['nullable', 'date', 'after_or_equal:commission_starts_on'],
            'commission_notes' => ['nullable', 'string', 'max:2000'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($admin)],
            'admin_password' => [$admin ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
        $previousRate = (float) $organization->commission_rate;
        $rateChanged = round($previousRate, 2) !== round((float) $data['commission_rate'], 2);
        $reason = null;
        if ($rateChanged) {
            $reason = $request->validate([
                'commission_reason' => ['required', 'string', 'min:5', 'max:500'],
            ], [
                'commission_reason.required' => 'Indica el motivo del cambio de convenio.',
                'commission_reason.min' => 'El motivo del convenio debe tener al menos 5 caracteres.',
            ])['commission_reason'];
        }
        DB::transaction(function () use ($organization, $admin, $data): void {
            $organization->update(collect($data)->only([
                'legal_name', 'commercial_name', 'phone', 'service_hours', 'base_city', 'modality', 'commission_rate',
                'commercial_plan', 'agreement_number', 'commission_starts_on', 'commission_ends_on', 'commission_notes',
            ])->all());
            if ($admin) {
                $admin->update([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    ...($data['admin_password'] ? ['password' => Hash::make($data['admin_password'])] : []),
                ]);
            } else {
                $admin = User::create([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => Hash::make($data['admin_password']),
                    'email_verified_at' => now(),
                ]);
                $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador de empresa']);
                $admin->organizations()->attach($organization->id, ['status' => 'active']);
                $admin->roles()->attach($role->id, ['organization_id' => $organization->id]);
            }
        });
        if ($rateChanged) {
            AuditRecorder::record($request, 'company_commission_changed', $organization, [
                'commission_rate' => ['from' => $previousRate, 'to' => (float) $data['commission_rate']],
                'reason' => $reason,
            ]);
        }

        return redirect()->route('admin.companies.index')->with('success', 'Empresa actualizada correctamente.');
    }

    public function updateCommission(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->type === 'transport_company', 404);
        $data = $request->validate([
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'commission_rate.required' => 'Indica el porcentaje del convenio.',
            'commission_rate.numeric' => 'El porcentaje debe ser un número.',
            'commission_rate.between' => 'El porcentaje va de 0 a 100.',
            'reason.required' => 'Indica el motivo del cambio de convenio.',
            'reason.min' => 'El motivo del convenio debe tener al menos 5 caracteres.',
        ]);
        $previousRate = (float) $organization->commission_rate;
        $organization->update(['commission_rate' => $data['commission_rate']]);
        if (round($previousRate, 2) !== round((float) $data['commission_rate'], 2)) {
            AuditRecorder::record($request, 'company_commission_changed', $organization, [
                'commission_rate' => ['from' => $previousRate, 'to' => (float) $data['commission_rate']],
                'reason' => $data['reason'],
            ]);
        }
        $name = $organization->commercial_name ?: $organization->legal_name;
        $rate = (float) $data['commission_rate'];
        $message = $rate === 0.0
            ? 'Se quitó la comisión de '.$name.'. Sus pasajes web ya no suman ese cargo.'
            : 'La comisión de '.$name.' quedó en '.number_format($rate, 2).'%.';

        return redirect()->route('admin.companies.index', ['tab' => 'comisiones'])
            ->with('success', $message.'. Aplica solo a las ventas nuevas.');
    }

    public function updateBasePort(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->type === 'transport_company', 404);
        $data = $request->validate([
            'base_port_id' => ['required', 'integer', Rule::exists('ports', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'base_port_id.required' => 'Elige el puerto base.',
            'base_port_id.exists' => 'Elige un puerto activo del catálogo.',
            'reason.required' => 'Indica el motivo del cambio de puerto base.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);
        $previous = $organization->base_port_id;
        $organization->update(['base_port_id' => $data['base_port_id']]);
        AuditRecorder::record($request, 'company_base_port_changed', $organization, [
            'base_port_id' => ['from' => $previous, 'to' => (int) $data['base_port_id']],
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Puerto base actualizado. La empresa lo ve en su panel.');
    }

    public function updateIdentity(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->type === 'transport_company', 404);
        $data = $request->validate([
            'ruc' => ['required', 'digits:11', Rule::unique('organizations', 'ruc')->ignore($organization->id)],
            'legal_name' => ['required', 'string', 'max:180'],
            'commercial_name' => ['nullable', 'string', 'max:180'],
            'address' => ['required', 'string', 'max:255'],
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
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'ruc.required' => 'Indica el RUC.',
            'ruc.digits' => 'El RUC tiene 11 dígitos.',
            'ruc.unique' => 'Ese RUC ya está registrado.',
            'legal_name.required' => 'Indica la razón social.',
            'address.required' => 'Indica la dirección fiscal.',
            'contact_name.required' => 'Indica al representante legal.',
            'reason.required' => 'Indica el motivo de la corrección.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);
        $fields = ['ruc', 'legal_name', 'commercial_name', 'address', 'contact_name', 'representative_document_number', 'representative_email', 'representative_phone'];
        $changes = [];
        foreach ($fields as $field) {
            $before = $organization->{$field};
            $after = $data[$field] ?? null;
            if ((string) $before !== (string) $after) {
                $changes[$field] = ['from' => $before, 'to' => $after];
            }
        }
        $organization->update(collect($data)->only($fields)->all());
        $this->replaceDocument($request, $organization, 'ruc_document', 'ruc', $data['ruc_expires_on'] ?? null, $data['ruc_reference'] ?? null);
        $this->replaceDocument($request, $organization, 'representative_document', 'representative', $data['representative_expires_on'] ?? null, $data['representative_reference'] ?? null);
        AuditRecorder::record($request, 'company_identity_corrected', $organization, [
            'fields' => $changes,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Datos de la empresa corregidos. La empresa ve el cambio en su panel.');
    }

    public function toggleStatus(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => $organization->status === 'active' ? 'suspended' : 'active']);

        return back()->with('success', 'Estado de la empresa actualizado.');
    }

    private function replaceDocument(Request $request, Organization $organization, string $field, string $type, ?string $expiresOn, ?string $reference): void
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
        OrganizationDocument::query()->create([
            'organization_id' => $organization->id,
            'type' => $type,
            'path' => $file->store('organization-documents/'.$organization->id),
            'original_name' => $file->getClientOriginalName(),
            'expires_on' => $expiresOn,
            'reference_number' => $reference,
        ]);
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => 'suspended']);

        return redirect()->route('admin.companies.index')->with('success', 'La empresa fue suspendida y se conservaron sus datos comerciales.');
    }
}
