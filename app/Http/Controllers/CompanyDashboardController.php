<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\CargoShipment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyDashboardController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization()->load(['vessels.basePort', 'aircraft']);

        $riverDepartures = RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereDate('departure_at', today())
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])
            ->orderBy('departure_at')->get();

        $airDepartures = AirDeparture::query()
            ->whereHas('airRoute', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereDate('departure_at', today())
            ->with(['airRoute', 'aircraft', 'reservations.seats'])
            ->orderBy('departure_at')->get();

        $todayDepartures = $riverDepartures->map(fn ($departure) => $this->departureRow($departure, false))
            ->concat($airDepartures->map(fn ($departure) => $this->departureRow($departure, true)))
            ->sortBy('departure_at')->values();

        $reservationScope = Reservation::query()->where(function ($query) use ($organization) {
            $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
        });

        $todayRevenue = (float) (clone $reservationScope)->where('status', 'confirmed')->whereDate('updated_at', today())->sum('total_amount');
        $todayRevenue += (float) CargoShipment::query()->where(function ($query) use ($organization) {
            $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
        })->whereIn('payment_status', ['paid', 'confirmed'])->whereDate('updated_at', today())->sum('amount');

        $todayTicketsCount = $todayDepartures->sum('booked_seats');
        $pendingCargoCount = CargoShipment::query()->where(function ($query) use ($organization) {
            $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
        })->whereNotIn('status', ['delivered', 'cancelled'])->count();

        $recentBookings = ReservationSeat::query()
            ->whereHas('reservation', function ($query) use ($organization) {
                $query->where(function ($scope) use ($organization) {
                    $scope->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                        ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
                });
            })
            ->with([
                'reservation.departure.transportRoute.originPort',
                'reservation.departure.transportRoute.destinationPort',
                'reservation.airDeparture.airRoute', 'reservation.seats', 'seat', 'aircraftSeat', 'ticket',
            ])->latest()->limit(5)->get();

        return view('company.dashboard', [
            'organization' => $organization,
            'company' => $organization,
            'todayRevenue' => $todayRevenue,
            'todayTicketsCount' => $todayTicketsCount,
            'activeDeparturesCount' => $todayDepartures->count(),
            'pendingCargoCount' => $pendingCargoCount,
            'todayDepartures' => $todayDepartures,
            'recentBookings' => $recentBookings,
            'counterStaff' => User::query()->whereHas('organizations', fn ($query) => $query->whereKey($organization->id))
                ->whereHas('roles', fn ($query) => $query->where('code', 'company_counter')->where('role_user.organization_id', $organization->id))
                ->with(['organizations' => fn ($query) => $query->whereKey($organization->id)])->orderBy('name')->get(),
            'metrics' => [
                'vessels' => Vessel::where('organization_id', $organization->id)->count(),
                'routes' => TransportRoute::where('organization_id', $organization->id)->count(),
                'departures' => RouteDeparture::whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))->count(),
                'cargo' => $pendingCargoCount,
            ],
        ]);
    }

    private function departureRow($departure, bool $isAir): array
    {
        $route = $isAir ? $departure->airRoute : $departure->transportRoute;
        $unit = $isAir ? $departure->aircraft : $departure->vessel;
        $reservations = $departure->reservations->whereNotIn('status', ['expired', 'cancelled']);

        return [
            'model' => $departure,
            'is_air' => $isAir,
            'departure_at' => $departure->departure_at,
            'origin' => $isAir ? $route->origin_city : $route->originPort->city,
            'destination' => $isAir ? $route->destination_city : $route->destinationPort->city,
            'unit' => $unit?->name ?? 'Unidad por asignar',
            'registration' => $unit?->registration_number,
            'capacity' => (int) ($unit?->seat_capacity ?? 0),
            'booked_seats' => $reservations->sum(fn ($reservation) => $reservation->seats->count()),
            'status' => $departure->status ?? 'scheduled',
        ];
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
        unset($data['logo'], $data['cover']);
        $organization->update($data);

        return back()->with('success', 'Tu perfil público se actualizó correctamente.');
    }

    private function organization()
    {
        return auth()->user()->organizations()->where('organizations.type', 'transport_company')->where('organizations.status', 'active')->firstOrFail();
    }
}
