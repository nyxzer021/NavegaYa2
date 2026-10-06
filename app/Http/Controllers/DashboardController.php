<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\CheckoutOrder;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $roles = auth()->user()->roles->pluck('code');
        if ($roles->contains('company_counter')) {
            return redirect()->route('company.counter.index');
        }
        if ($roles->contains('company_admin')) {
            return redirect()->route('company.departures.index');
        }
        if ($roles->intersect(['customer', 'traveler', 'passenger'])->isNotEmpty()) {
            return redirect()->route('travels.index');
        }

        if ($roles->contains('super_admin')) {
            return view('dashboard', $this->superAdminData($roles));
        }

        return view('dashboard', [
            'title' => 'Mis viajes',
            'roles' => $roles,
            'travelerReservations' => Reservation::query()->where('user_id', auth()->id())
                ->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel'])
                ->latest()->take(8)->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function superAdminData(Collection $roles): array
    {
        $confirmedOrdersToday = CheckoutOrder::query()->where('status', 'confirmed')->whereDate('updated_at', today());
        $todayTickets = Ticket::query()->whereDate('issued_at', today());
        $pendingApplications = Organization::query()->where('type', 'transport_company')->where('status', 'pending')
            ->withCount(['vessels', 'aircraft'])->latest()->limit(6)->get();
        $operators = Organization::query()->where('type', 'transport_company')->where('status', 'active')
            ->whereNotNull('verified_at')->with(['vessels', 'aircraft'])->orderBy('commercial_name')->limit(30)->get();

        $operatorSummaries = $operators->map(function (Organization $organization): array {
            $riverDepartures = RouteDeparture::query()->whereDate('departure_at', today())
                ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
                ->with(['vessel', 'reservations.seats'])->get();
            $airDepartures = AirDeparture::query()->whereDate('departure_at', today())
                ->whereHas('airRoute', fn ($query) => $query->where('organization_id', $organization->id))
                ->with(['aircraft', 'reservations.seats'])->get();
            $booked = $riverDepartures->sum(fn ($departure) => $departure->reservations->sum(fn ($reservation) => $reservation->seats->count()))
                + $airDepartures->sum(fn ($departure) => $departure->reservations->sum(fn ($reservation) => $reservation->seats->count()));
            $capacity = $riverDepartures->sum(fn ($departure) => (int) ($departure->vessel?->seat_capacity ?? 0))
                + $airDepartures->sum(fn ($departure) => (int) ($departure->aircraft?->seat_capacity ?? 0));
            $gross = Reservation::query()->where('status', 'confirmed')->whereDate('updated_at', today())
                ->where(function ($query) use ($organization) {
                    $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                        ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
                })->sum('total_amount');

            return [
                'organization' => $organization,
                'modality' => $riverDepartures->isNotEmpty() && $airDepartures->isNotEmpty() ? 'Bimodal' : ($airDepartures->isNotEmpty() || ($organization->aircraft->isNotEmpty() && $organization->vessels->isEmpty()) ? 'Aéreo' : 'Fluvial'),
                'departures' => $riverDepartures->count() + $airDepartures->count(),
                'occupancy' => $capacity > 0 ? min(100, (int) round(($booked / $capacity) * 100)) : 0,
                'gross' => (float) $gross,
            ];
        })->filter(fn (array $summary) => $summary['departures'] > 0)->values();

        return [
            'title' => 'Comando Regional NavegaYA',
            'roles' => $roles,
            'todayGmv' => (float) (clone $confirmedOrdersToday)->sum('total_amount'),
            'todayCommission' => (float) (clone $confirmedOrdersToday)->sum('platform_fee_amount'),
            'todayTicketsTotal' => (clone $todayTickets)->count(),
            'todayRiverTickets' => (clone $todayTickets)->whereHas('reservationSeat', fn ($query) => $query->whereNotNull('route_departure_id'))->count(),
            'todayAirTickets' => (clone $todayTickets)->whereHas('reservationSeat', fn ($query) => $query->whereNotNull('air_departure_id'))->count(),
            'activeOperatorsToday' => $operatorSummaries->count(),
            'pendingApplications' => $pendingApplications,
            'operatorSummaries' => $operatorSummaries,
            'portStatuses' => $this->portStatuses(),
            'systemAlerts' => [
                'river' => SystemSetting::value('river_status', 'Normal'),
                'ports' => SystemSetting::value('port_status', 'Operativos'),
                'air' => SystemSetting::value('air_status', 'Operativo'),
            ],
        ];
    }

    /** @return Collection<int, array{name: string, status: string, tone: string}> */
    private function portStatuses(): Collection
    {
        return collect(['Masusa', 'Silico', 'Nauta', 'Yurimaguas', 'Hangar FAP'])->map(function (string $name): array {
            $port = Port::query()->where('name', 'like', "%{$name}%")->first();
            $masterStatus = $port ? MasterRoute::query()->where(function ($query) use ($port) {
                $query->where('origin_port_id', $port->id)->orWhere('destination_port_id', $port->id);
            })->where('status', '!=', 'active')->value('status') : null;
            $status = ! $port || ! $port->is_active ? 'Zarpes suspendidos' : match ($masterStatus) {
                'suspended_river_level' => 'Precaución por vaciante',
                'maintenance' => 'Mantenimiento',
                default => 'Normal',
            };

            return ['name' => $port?->name ?? $name, 'status' => $status, 'tone' => $status === 'Normal' ? 'emerald' : ($status === 'Mantenimiento' ? 'rose' : 'amber')];
        });
    }
}
