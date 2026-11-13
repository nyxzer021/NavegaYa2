<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\DepartureSeries;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\Vessel;
use App\Support\AdminScope;
use App\Support\CrewRoster;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CompanyDepartureController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organization();
        $organizationId = $organization->id;
        $organization->load(['vessels' => fn ($query) => $query->with(['basePort', 'seats'])]);
        $filter = $request->string('filter')->toString();
        $river = in_array($organization->modality ?: 'fluvial', ['fluvial', 'mixto'], true) ? RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->whereNotNull('master_route_id')
                ->whereColumn('origin_port_id', '<>', 'destination_port_id')
                ->whereHas('originPort')
                ->whereHas('destinationPort'))
            ->when($filter === 'today', fn ($query) => $query->whereDate('departure_at', today()))
            ->with(['transportRoute.masterRoute', 'transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats', 'assignments.member'])
            ->orderBy('departure_at')->get()->map(fn (RouteDeparture $departure) => $this->riverRow($departure)) : collect();
        $air = in_array($organization->modality, ['aereo', 'mixto'], true) ? AirDeparture::query()
            ->whereHas('airRoute', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->whereNotNull('master_route_id')
                ->whereColumn('origin_city', '<>', 'destination_city'))
            ->when($filter === 'today', fn ($query) => $query->whereDate('departure_at', today()))
            ->with(['airRoute.masterRoute', 'aircraft', 'reservations.seats', 'assignments.member'])
            ->orderBy('departure_at')->get()->map(fn (AirDeparture $departure) => $this->airRow($departure)) : collect();
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
        $todayDepartures = (clone $todayRiver)->with(['vessel', 'reservations.seats'])->get()
            ->concat((clone $todayAir)->with(['aircraft', 'reservations.seats'])->get());
        $todaySeatCapacity = (int) $todayDepartures->sum(fn (RouteDeparture|AirDeparture $departure): int => (int) (($departure instanceof AirDeparture ? $departure->aircraft : $departure->vessel)?->seat_capacity ?? 0));
        $todayConfirmedSeats = (int) $todayDepartures->sum(fn (RouteDeparture|AirDeparture $departure): int => (int) $departure->reservations
            ->where('status', 'confirmed')
            ->sum(fn ($reservation) => $reservation->seats->count()));

        return view('company.departures.index', [
            'organization' => $organization,
            'departures' => $departures,
            'filter' => $filter,
            'todayDeparturesCount' => $todayDepartures->count(),
            'todayConfirmedSeats' => $todayConfirmedSeats,
            'todaySeatCapacity' => $todaySeatCapacity,
            'todayOccupancyLabel' => $todaySeatCapacity > 0
                ? (string) (int) round(($todayConfirmedSeats / $todaySeatCapacity) * 100)
                : '0',
            'series' => DepartureSeries::query()->where('organization_id', $organizationId)->latest()->get(),
        ]);
    }

    private function organization(): Organization
    {
        return Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->whereIn('status', ['active', 'pending'])->firstOrFail();
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
        $booked = (int) $departure->reservations
            ->where('status', 'confirmed')
            ->sum(fn ($reservation) => $reservation->seats->count());
        $capacity = (int) ($unit?->seat_capacity ?? 0);

        return [
            'model' => $departure, 'is_air' => $isAir, 'departure_at' => $departure->departure_at,
            'origin' => $origin ?? 'Origen', 'destination' => $destination ?? 'Destino', 'master_code' => $masterCode,
            'unit' => $unit?->name ?? 'Unidad por asignar', 'registration' => $unit?->registration_number,
            'capacity' => $capacity, 'booked' => $booked, 'occupancy' => $capacity > 0 ? (int) round($booked / $capacity * 100) : 0,
            'fare' => (float) ($departure->fare ?? 0), 'status' => $departure->status ?? 'scheduled',
            'can_board' => CrewRoster::commandReady($departure),
        ];
    }
}
