<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\RouteDeparture;
use App\Models\Vessel;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CompanyDepartureController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organization();
        $organizationId = $organization->id;
        $organization->loadMissing('vessels.basePort');
        $filter = $request->string('filter')->toString();
        $river = RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->whereNotNull('master_route_id')
                ->whereColumn('origin_port_id', '<>', 'destination_port_id')
                ->whereHas('originPort')
                ->whereHas('destinationPort'))
            ->when($filter === 'today', fn ($query) => $query->whereDate('departure_at', today()))
            ->with(['transportRoute.masterRoute', 'transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])
            ->orderBy('departure_at')->get()->map(fn (RouteDeparture $departure) => $this->riverRow($departure));
        $air = AirDeparture::query()
            ->whereHas('airRoute', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->whereNotNull('master_route_id')
                ->whereColumn('origin_city', '<>', 'destination_city'))
            ->when($filter === 'today', fn ($query) => $query->whereDate('departure_at', today()))
            ->with(['airRoute.masterRoute', 'aircraft', 'reservations.seats'])
            ->orderBy('departure_at')->get()->map(fn (AirDeparture $departure) => $this->airRow($departure));
        $rows = $river->concat($air)->sortBy('departure_at')->values();
        $page = max(1, $request->integer('page', 1));
        $departures = new LengthAwarePaginator($rows->forPage($page, 15), $rows->count(), 15, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        $todayRiver = RouteDeparture::query()->whereHas('transportRoute', fn ($query) => $query
            ->where('organization_id', $organizationId)
            ->whereNotNull('master_route_id')
            ->whereColumn('origin_port_id', '<>', 'destination_port_id'))->whereDate('departure_at', today());
        $todayAir = AirDeparture::query()->whereHas('airRoute', fn ($query) => $query
            ->where('organization_id', $organizationId)
            ->whereNotNull('master_route_id')
            ->whereColumn('origin_city', '<>', 'destination_city'))->whereDate('departure_at', today());
        $reservationScope = $this->reservationScope($organization);
        $todayGrossRevenue = (float) (clone $reservationScope)->where('status', 'confirmed')->whereDate('updated_at', today())->sum('total_amount');
        $todayPassengersCount = (clone $reservationScope)->whereNotIn('status', ['cancelled', 'expired'])->whereDate('created_at', today())->withCount('seats')->get()->sum('seats_count');
        $commissionRate = (float) ($organization->commission_rate ?? 8);

        return view('company.departures.index', [
            'organization' => $organization,
            'departures' => $departures,
            'filter' => $filter,
            'todayDeparturesCount' => (clone $todayRiver)->count() + (clone $todayAir)->count(),
            'todayPassengersCount' => $todayPassengersCount,
            'todayGrossRevenue' => $todayGrossRevenue,
            'commissionRate' => $commissionRate,
            'todayCommissionRetained' => round(($todayGrossRevenue * $commissionRate) / 100, 2),
            'pendingCargoCount' => CargoShipment::query()->where(function ($query) use ($organization) {
                $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                    ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
            })->whereNotIn('status', ['delivered', 'cancelled'])->count(),
        ]);
    }

    private function organization(): Organization
    {
        return Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->whereIn('status', ['active', 'pending'])->firstOrFail();
    }

    private function reservationScope(Organization $organization): Builder
    {
        return Reservation::query()->where(function ($query) use ($organization) {
            $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
        });
    }

    private function riverRow(RouteDeparture $departure): array
    {
        return $this->row($departure, false, $departure->transportRoute?->originPort?->city, $departure->transportRoute?->destinationPort?->city, $departure->vessel, $departure->transportRoute?->masterRoute?->code);
    }

    private function airRow(AirDeparture $departure): array
    {
        return $this->row($departure, true, $departure->airRoute?->origin_city, $departure->airRoute?->destination_city, $departure->aircraft, $departure->airRoute?->masterRoute?->code);
    }

    private function row(RouteDeparture|AirDeparture $departure, bool $isAir, ?string $origin, ?string $destination, Vessel|Aircraft|null $unit, ?string $masterCode): array
    {
        $reservations = $departure->reservations->whereNotIn('status', ['cancelled', 'expired']);
        $booked = $reservations->sum(fn ($reservation) => $reservation->seats->count());
        $capacity = (int) ($unit?->seat_capacity ?? 0);

        return [
            'model' => $departure, 'is_air' => $isAir, 'departure_at' => $departure->departure_at,
            'origin' => $origin ?? 'Origen', 'destination' => $destination ?? 'Destino', 'master_code' => $masterCode,
            'unit' => $unit?->name ?? 'Unidad por asignar', 'registration' => $unit?->registration_number,
            'capacity' => $capacity, 'booked' => $booked, 'occupancy' => $capacity > 0 ? min(100, (int) round($booked / $capacity * 100)) : 0,
            'fare' => (float) ($departure->fare ?? 0), 'status' => $departure->status ?? 'scheduled',
        ];
    }
}
