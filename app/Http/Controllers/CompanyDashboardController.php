<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\CargoShipment;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RouteDeparture;
use App\Models\SupportTicket;
use App\Support\CompanyBase;
use App\Support\DepartureSchedule;
use App\Support\FleetMaintenance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyDashboardController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization()->load(['vessels.basePort', 'aircraft']);
        $todayDepartures = $this->todayDepartures($organization);
        $todaySeatCapacity = (int) $todayDepartures->sum('capacity');
        $todayConfirmedSeats = (int) $todayDepartures->sum('confirmed_seats');
        $upcomingDepartures = $this->upcomingDepartures($organization);
        $month = $this->monthSnapshot($organization);

        return view('company.dashboard', [
            'organization' => $organization,
            'company' => $organization,
            'todayDepartureCount' => $todayDepartures->count(),
            'todayConfirmedSeats' => $todayConfirmedSeats,
            'todaySeatCapacity' => $todaySeatCapacity,
            'todayOccupancyLabel' => $this->occupancyLabel($todaySeatCapacity, $todayConfirmedSeats),
            'attention' => $this->attentionItems($organization, $todayDepartures, $month),
            'steps' => $this->firstSteps($organization, $todayDepartures->count() + $upcomingDepartures->count()),
            'fleetPhrase' => CompanyBase::fleetPhrase($organization),
            'baseLabel' => CompanyBase::label($organization),
            'todayDepartures' => $todayDepartures,
            'upcomingDepartures' => $upcomingDepartures,
            'month' => $month,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function todayDepartures($organization): Collection
    {
        $modality = $organization->modality ?: 'fluvial';
        $riverDepartures = in_array($modality, ['fluvial', 'mixto'], true)
            ? RouteDeparture::query()
                ->whereHas('transportRoute', fn (Builder $query) => $query->where('organization_id', $organization->id))
                ->tap(fn ($query) => DepartureSchedule::onThisLimaDay($query))
                ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])
                ->orderBy('departure_at')->get()
            : collect();
        $airDepartures = in_array($modality, ['aereo', 'mixto'], true)
            ? AirDeparture::query()
                ->whereHas('airRoute', fn (Builder $query) => $query->where('organization_id', $organization->id))
                ->tap(fn ($query) => DepartureSchedule::onThisLimaDay($query))
                ->with(['airRoute', 'aircraft', 'reservations.seats'])
                ->orderBy('departure_at')->get()
            : collect();

        return $riverDepartures->map(fn ($departure) => $this->departureRow($departure, false))
            ->concat($airDepartures->map(fn ($departure) => $this->departureRow($departure, true)))
            ->sortBy('departure_at')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function upcomingDepartures($organization): Collection
    {
        $from = DepartureSchedule::dayBounds()[1];
        $until = $from->copy()->addDays(14);
        $modality = $organization->modality ?: 'fluvial';
        $riverDepartures = in_array($modality, ['fluvial', 'mixto'], true)
            ? RouteDeparture::query()
                ->whereHas('transportRoute', fn (Builder $query) => $query->where('organization_id', $organization->id))
                ->where('departure_at', '>', $from)
                ->where('departure_at', '<=', $until)
                ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])
                ->orderBy('departure_at')->limit(6)->get()
            : collect();
        $airDepartures = in_array($modality, ['aereo', 'mixto'], true)
            ? AirDeparture::query()
                ->whereHas('airRoute', fn (Builder $query) => $query->where('organization_id', $organization->id))
                ->where('departure_at', '>', $from)
                ->where('departure_at', '<=', $until)
                ->with(['airRoute', 'aircraft', 'reservations.seats'])
                ->orderBy('departure_at')->limit(6)->get()
            : collect();

        return $riverDepartures->map(fn ($departure) => $this->departureRow($departure, false))
            ->concat($airDepartures->map(fn ($departure) => $this->departureRow($departure, true)))
            ->sortBy('departure_at')->take(6)->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function departureRow($departure, bool $isAir): array
    {
        $route = $isAir ? $departure->airRoute : $departure->transportRoute;
        $unit = $isAir ? $departure->aircraft : $departure->vessel;
        $confirmedSeats = $departure->reservations
            ->where('status', 'confirmed')
            ->sum(fn ($reservation) => $reservation->seats->count());

        return [
            'model' => $departure,
            'is_air' => $isAir,
            'departure_at' => $departure->departure_at,
            'origin' => $isAir ? $route->origin_city : $route->originPort->city,
            'destination' => $isAir ? $route->destination_city : $route->destinationPort->city,
            'unit' => $unit?->name ?? 'Unidad por asignar',
            'registration' => $unit?->registration_number,
            'capacity' => (int) ($unit?->seat_capacity ?? 0),
            'confirmed_seats' => (int) $confirmedSeats,
            'status' => $departure->status ?? 'scheduled',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function monthSnapshot($organization): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $payments = Payment::query()
            ->whereIn('status', ['confirmed', 'succeeded'])
            ->where(function (Builder $query): void {
                $query->whereNull('purpose')->orWhere('purpose', 'ticket');
            })
            ->whereBetween('paid_at', [$from, $to])
            ->where(function (Builder $query) use ($organization): void {
                $query->whereHas('reservation.departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                    ->orWhereHas('reservation.airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
            });
        $statuses = $organization->vessels->countBy('status');

        return [
            'ticket_count' => (clone $payments)->count(),
            'ticket_amount' => (float) (clone $payments)->sum('amount'),
            'vessel_count' => $organization->vessels->count(),
            'seat_capacity' => FleetMaintenance::enabledSeatCapacity($organization->id),
            'active_vessels' => (int) ($statuses['ready'] ?? 0),
            'maintenance_vessels' => (int) ($statuses['maintenance'] ?? 0),
            'rescheduled' => RouteDeparture::query()
                ->whereHas('transportRoute', fn (Builder $query) => $query->where('organization_id', $organization->id))
                ->whereBetween('departure_at', [$from, $to])
                ->where('status', 'rescheduled')
                ->count(),
            'pending_tickets' => Reservation::query()
                ->where('status', 'pending_payment')
                ->where(function (Builder $query) use ($organization): void {
                    $query->whereHas('departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                        ->orWhereHas('airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
                })
                ->count(),
            'claims' => SupportTicket::query()
                ->where('organization_id', $organization->id)
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'web_sales' => (float) (clone $payments)->whereHas('reservation', fn (Builder $query) => $query->where('sales_channel', 'web'))->sum('amount'),
            'counter_sales' => (float) (clone $payments)->whereHas('reservation', fn (Builder $query) => $query->where('sales_channel', 'counter'))->sum('amount'),
            'maintenance' => $organization->vessels
                ->filter(fn ($vessel): bool => $vessel->maintenance_scheduled_on !== null && $vessel->maintenance_scheduled_on->gte(today()))
                ->sortBy('maintenance_scheduled_on')
                ->map(fn ($vessel): array => [
                    'name' => $vessel->name,
                    'on' => $vessel->maintenance_scheduled_on,
                    'kind' => $vessel->maintenance_kind === 'reparacion' ? 'Reparación' : 'Mantenimiento',
                ])
                ->values(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $todayDepartures
     * @param  array<string, mixed>  $month
     * @return Collection<int, array{title: string, detail: string, href: string}>
     */
    private function attentionItems($organization, Collection $todayDepartures, array $month): Collection
    {
        $items = collect();
        foreach ($todayDepartures->whereIn('status', ['delayed', 'rescheduled', 'suspended', 'cancelled']) as $row) {
            $items->push([
                'title' => $row['origin'].' → '.$row['destination'],
                'detail' => DepartureSchedule::show($row['departure_at'], 'H:i'),
                'href' => route('company.departures.index'),
            ]);
        }
        $cargo = $this->pendingCargoCount($organization);
        if ($cargo > 0) {
            $items->push([
                'title' => __('company_portal.home.cargo'),
                'detail' => (string) $cargo,
                'href' => route('company.departures.index'),
            ]);
        }
        if (($month['pending_tickets'] ?? 0) > 0) {
            $items->push([
                'title' => __('company_portal.home.pending_tickets'),
                'detail' => (string) $month['pending_tickets'],
                'href' => route('company.sales.index'),
            ]);
        }
        if (($month['claims'] ?? 0) > 0) {
            $items->push([
                'title' => __('company_portal.home.claims'),
                'detail' => (string) $month['claims'],
                'href' => route('support.index'),
            ]);
        }

        return $items->values();
    }

    /**
     * @return list<array{title: string, detail: string, href: string|null}>
     */
    private function firstSteps($organization, int $scheduledDepartures): array
    {
        $steps = [];
        $organization->loadMissing(['vessels', 'aircraft']);
        if ($organization->vessels->isEmpty() && $organization->aircraft->isEmpty()) {
            $steps[] = [
                'title' => __('company_portal.home.step_fleet'),
                'detail' => __('company_portal.home.step_fleet_detail'),
                'href' => route('company.fleet.index'),
            ];
        }
        if (CompanyBase::label($organization) === null) {
            $steps[] = [
                'title' => __('company_portal.home.step_base'),
                'detail' => __('company_portal.home.step_base_detail'),
                'href' => route('company.fleet.index'),
            ];
        }
        if (blank($organization->public_description)) {
            $steps[] = [
                'title' => __('company_portal.home.step_profile'),
                'detail' => __('company_portal.home.step_profile_detail'),
                'href' => route('company.profile.edit'),
            ];
        }
        if ($scheduledDepartures === 0 && $organization->status === 'active') {
            $steps[] = [
                'title' => __('company_portal.home.step_departure'),
                'detail' => __('company_portal.home.step_departure_detail'),
                'href' => route('company.departures.create'),
            ];
        }
        if ($organization->status !== 'active') {
            $steps[] = [
                'title' => __('company_portal.home.step_review'),
                'detail' => __('company_portal.home.step_review_detail'),
                'href' => route('support.index'),
            ];
        }

        return $steps;
    }

    private function occupancyLabel(int $capacity, int $confirmedSeats): string
    {
        if ($capacity < 1) {
            return '0';
        }

        $rate = round(($confirmedSeats / $capacity) * 100, 1);

        return fmod($rate, 1.0) === 0.0 ? (string) (int) $rate : number_format($rate, 1, '.', '');
    }

    private function pendingCargoCount($organization): int
    {
        return CargoShipment::query()->where(function (Builder $query) use ($organization): void {
            $query->whereHas('departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                ->orWhereHas('airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
        })->whereNotIn('status', ['delivered', 'cancelled'])->count();
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'commercial_name' => ['nullable', 'string', 'max:150'], 'whatsapp' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'], 'website' => ['nullable', 'url', 'max:500'],
            'address' => ['nullable', 'string', 'max:250'], 'public_description' => ['nullable', 'string', 'max:1500'],
            'logo' => ['nullable', 'image', 'max:5120'], 'cover' => ['nullable', 'image', 'max:5120'],
        ]);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = Storage::url($request->file('logo')->store('company-profiles', 'public'));
        }
        if ($request->hasFile('cover')) {
            $data['cover_image_path'] = Storage::url($request->file('cover')->store('company-profiles', 'public'));
        }
        if ($request->hasFile('logo') || $request->hasFile('cover')) {
            $data['profile_content_status'] = 'pending';
        }
        unset($data['logo'], $data['cover']);
        $organization->update($data);

        return back()->with('success', $request->hasFile('logo') || $request->hasFile('cover') ? 'Perfil actualizado. Las nuevas imágenes están pendientes de revisión.' : 'Tu perfil público se actualizó correctamente.');
    }

    private function organization()
    {
        return auth()->user()->organizations()
            ->where('organizations.type', 'transport_company')
            ->whereIn('organizations.status', ['active', 'pending'])
            ->firstOrFail();
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'bank_account' => ['required', 'string', 'max:40'],
            'bank_cci' => ['nullable', 'regex:/^[0-9]{20}$/'],
        ]);
        $this->organization()->update($data);

        return back()->with('success', 'Cuenta bancaria actualizada de forma segura.');
    }
}
