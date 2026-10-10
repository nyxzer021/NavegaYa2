<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\Payment;
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
                'vessels as active_vessels_count' => fn ($query) => $query->where('status', 'ready'),
                'aircraft as active_aircraft_count' => fn ($query) => $query->where('status', 'ready'),
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

        $commissionSummary = Organization::query()
            ->where('type', 'transport_company')
            ->where('status', 'active')
            ->selectRaw("CASE WHEN modality = 'aereo' THEN 'aereo' ELSE 'fluvial' END AS channel, AVG(commission_rate) AS average_rate, COUNT(*) AS operators_count")
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');

        return view('admin.companies.index', [
            'companies' => $companies,
            'pendingAffiliations' => $pendingAffiliations,
            'gmvToday' => $gmvToday,
            'commissionToday' => $commissionToday,
            'ticketsToday' => $ticketsToday,
            'fluvialTicketsToday' => $fluvialTicketsToday,
            'airTicketsToday' => $airTicketsToday,
            'activeOperatorsToday' => $activeOperatorsToday,
            'commissionSummary' => $commissionSummary,
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
        ]);

        return view('admin.companies.show', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $admin = $organization->users->first(fn ($user) => $user->roles->contains('code', 'company_admin'));
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
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
            'admin_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($organization, $admin, $data): void {
            $organization->update(collect($data)->only([
                'legal_name', 'commercial_name', 'phone', 'base_city', 'modality', 'commission_rate',
                'commercial_plan', 'agreement_number', 'commission_starts_on', 'commission_ends_on', 'commission_notes',
            ])->all());
            if ($admin) {
                $admin->update([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    ...($data['admin_password'] ? ['password' => Hash::make($data['admin_password'])] : []),
                ]);
            }
        });

        return redirect()->route('admin.companies.index')->with('success', 'Empresa actualizada correctamente.');
    }

    public function toggleStatus(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => $organization->status === 'active' ? 'suspended' : 'active']);

        return back()->with('success', 'Estado de la empresa actualizado.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => 'suspended']);

        return redirect()->route('admin.companies.index')->with('success', 'La empresa fue suspendida y se conservaron sus datos comerciales.');
    }
}
