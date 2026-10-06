<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSupervisionController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = $request->filled('organization_id') ? $request->integer('organization_id') : null;
        $date = $request->filled('date') ? $request->date('date') : null;
        $marketplaceStatus = $request->string('marketplace_status')->toString();

        $riverDepartures = RouteDeparture::query()
            ->with(['transportRoute.organization', 'transportRoute.masterRoute', 'transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization', 'reservations.seats.ticket'])
            ->when($organizationId, fn ($query, $id) => $query->where(fn ($scope) => $scope
                ->whereHas('transportRoute', fn ($route) => $route->where('organization_id', $id))
                ->orWhereHas('vessel', fn ($vehicle) => $vehicle->where('organization_id', $id))))
            ->when($date, fn ($query, $value) => $query->whereDate('departure_at', $value))
            ->when($marketplaceStatus === 'selling', fn ($query) => $query->where('is_published', true)->whereIn('status', ['scheduled', 'boarding']))
            ->when($marketplaceStatus === 'paused', fn ($query) => $query->where(fn ($scope) => $scope->where('is_published', false)->orWhereIn('status', ['cancelled', 'completed'])))
            ->get()->map(fn (RouteDeparture $departure): array => [
                'id' => $departure->id,
                'type' => 'fluvial',
                'departure_at' => $departure->departure_at,
                // The registered unit is the strongest ownership signal when legacy
                // route records were imported with the wrong organization_id.
                'company' => $departure->vessel?->organization ?? $departure->transportRoute->organization,
                'route' => ($departure->transportRoute->originPort->city ?? '—').' ➔ '.($departure->transportRoute->destinationPort->city ?? '—'),
                'route_code' => $departure->transportRoute->masterRoute->code ?? $departure->transportRoute->code,
                'vehicle' => $departure->vessel,
                'reservations' => $departure->reservations,
                'status' => $departure->status,
                'is_published' => $departure->is_published,
                'public_url' => route('bookings.create', $departure),
            ]);

        $airDepartures = AirDeparture::query()
            ->with(['airRoute.organization', 'airRoute.masterRoute', 'aircraft.organization', 'reservations.seats.ticket'])
            ->when($organizationId, fn ($query, $id) => $query->where(fn ($scope) => $scope
                ->whereHas('airRoute', fn ($route) => $route->where('organization_id', $id))
                ->orWhereHas('aircraft', fn ($vehicle) => $vehicle->where('organization_id', $id))))
            ->when($date, fn ($query, $value) => $query->whereDate('departure_at', $value))
            ->when($marketplaceStatus === 'selling', fn ($query) => $query->where('is_published', true)->whereIn('status', ['scheduled', 'boarding']))
            ->when($marketplaceStatus === 'paused', fn ($query) => $query->where(fn ($scope) => $scope->where('is_published', false)->orWhereIn('status', ['cancelled', 'completed'])))
            ->get()->map(fn (AirDeparture $departure): array => [
                'id' => $departure->id,
                'type' => 'aereo',
                'departure_at' => $departure->departure_at,
                'company' => $departure->aircraft?->organization ?? $departure->airRoute->organization,
                'route' => $departure->airRoute->origin_city.' ➔ '.$departure->airRoute->destination_city,
                'route_code' => $departure->airRoute->masterRoute->code ?? $departure->airRoute->code,
                'vehicle' => $departure->aircraft,
                'reservations' => $departure->reservations,
                'status' => $departure->status,
                'is_published' => $departure->is_published,
                'public_url' => route('air-bookings.create', $departure),
            ]);

        $departures = $this->paginate($riverDepartures->concat($airDepartures)->sortByDesc('departure_at')->values(), $request);
        $companies = Organization::where('type', 'transport_company')->whereIn('status', ['active', 'pending'])->orderBy('commercial_name')->get();
        $selectedCompany = $organizationId ? $companies->firstWhere('id', $organizationId) : null;

        return view('admin.supervision.index', [
            'departures' => $departures,
            'companies' => $companies,
            'filterLabel' => $selectedCompany
                ? ($selectedCompany->commercial_name ?: $selectedCompany->legal_name)
                : 'Todas las empresas ('.$companies->count().' registradas)',
            ...$this->marketplaceMetrics(),
        ]);
    }

    public function togglePublication(Request $request, int $departure): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['fluvial', 'aereo'])]]);
        $record = $data['type'] === 'aereo'
            ? AirDeparture::findOrFail($departure)
            : RouteDeparture::findOrFail($departure);

        $record->update(['is_published' => ! $record->is_published]);

        return back()->with('success', $record->is_published
            ? 'La salida volvió a publicarse en el marketplace.'
            : 'La salida fue pausada y ya no aparece en la búsqueda pública.');
    }

    private function marketplaceMetrics(): array
    {
        $river = RouteDeparture::with(['vessel', 'reservations.seats', 'transportRoute'])
            ->whereDate('departure_at', today())->get();
        $air = AirDeparture::with(['aircraft', 'reservations.seats', 'airRoute'])
            ->whereDate('departure_at', today())->get();
        $all = $river->map(fn ($departure) => [
            'capacity' => (int) ($departure->vessel?->seat_capacity ?? 0),
            'sold' => $departure->reservations->where('status', 'confirmed')->sum(fn ($reservation) => $reservation->seats->count()),
            'open' => $departure->is_published && in_array($departure->status, ['scheduled', 'boarding'], true),
            'route' => 'river:'.$departure->transport_route_id,
        ])->concat($air->map(fn ($departure) => [
            'capacity' => (int) ($departure->aircraft?->seat_capacity ?? 0),
            'sold' => $departure->reservations->where('status', 'confirmed')->sum(fn ($reservation) => $reservation->seats->count()),
            'open' => $departure->is_published && in_array($departure->status, ['scheduled', 'boarding'], true),
            'route' => 'air:'.$departure->air_route_id,
        ]));
        $open = $all->where('open', true);

        return [
            'availableSeatsToday' => $open->sum(fn ($item) => max(0, $item['capacity'] - $item['sold'])),
            'ticketsSoldToday' => $all->sum('sold'),
            'openRoutesCount' => $open->pluck('route')->unique()->count(),
            'marketplaceOperational' => true,
        ];
    }

    public function edit(Request $request, int $departure): View
    {
        $type = $request->validate([
            'type' => ['required', Rule::in(['fluvial', 'aereo'])],
        ])['type'];

        if ($type === 'aereo') {
            $record = AirDeparture::with(['airRoute.organization', 'airRoute.masterRoute', 'aircraft'])->findOrFail($departure);
            $organization = $record->airRoute->organization;
            $vehicles = Aircraft::where('organization_id', $organization->id)->orderBy('name')->get();
            $selectedMasterRouteId = $record->airRoute->master_route_id;
            $selectedVehicleId = $record->aircraft_id;
        } else {
            $record = RouteDeparture::with(['transportRoute.organization', 'transportRoute.masterRoute', 'vessel'])->findOrFail($departure);
            $organization = $record->transportRoute->organization;
            $vehicles = Vessel::where('organization_id', $organization->id)->orderBy('name')->get();
            $selectedMasterRouteId = $record->transportRoute->master_route_id;
            $selectedVehicleId = $record->vessel_id;
        }

        return view('admin.supervision.edit', [
            'departure' => $record,
            'type' => $type,
            'organization' => $organization,
            'masterRoutes' => MasterRoute::with(['originPort', 'destinationPort'])
                ->where('modality', $type)
                ->where('status', 'active')
                ->orderBy('origin_city')
                ->orderBy('destination_city')
                ->get(),
            'vehicles' => $vehicles,
            'selectedMasterRouteId' => $selectedMasterRouteId,
            'selectedVehicleId' => $selectedVehicleId,
            'statusOptions' => [
                'scheduled' => 'Programada',
                'boarding' => 'En embarque',
                'in_transit' => 'En tránsito',
                'completed' => 'Completada',
                'cancelled' => 'Cancelada',
            ],
        ]);
    }

    public function update(Request $request, int $departure): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['fluvial', 'aereo'])],
            'master_route_id' => ['required', 'integer', 'exists:master_routes,id'],
            'vessel_id' => ['required', 'integer'],
            'departure_time' => ['required', 'date'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'status' => ['required', Rule::in(['scheduled', 'boarding', 'in_transit', 'completed', 'cancelled'])],
        ]);

        DB::transaction(function () use ($data, $departure): void {
            $masterRoute = MasterRoute::where('modality', $data['type'])->findOrFail($data['master_route_id']);
            $duration = $this->durationMinutes($masterRoute->estimated_duration_text);

            if ($data['type'] === 'aereo') {
                $record = AirDeparture::with('airRoute')->lockForUpdate()->findOrFail($departure);
                $organizationId = $record->airRoute->organization_id;
                $vehicle = Aircraft::where('organization_id', $organizationId)->findOrFail($data['vessel_id']);
                $route = AirRoute::firstOrCreate(
                    ['organization_id' => $organizationId, 'master_route_id' => $masterRoute->id],
                    ['origin_city' => $masterRoute->origin_city, 'destination_city' => $masterRoute->destination_city, 'estimated_duration_minutes' => $duration, 'status' => 'active']
                );
                $departureAt = now()->parse($data['departure_time']);
                $record->update([
                    'air_route_id' => $route->id,
                    'aircraft_id' => $vehicle->id,
                    'departure_at' => $departureAt,
                    'boarding_starts_at' => $departureAt->copy()->subMinutes(45),
                    'estimated_arrival_at' => $duration ? $departureAt->copy()->addMinutes($duration) : null,
                    'fare' => $data['price'],
                    'status' => $data['status'],
                ]);

                return;
            }

            $record = RouteDeparture::with('transportRoute')->lockForUpdate()->findOrFail($departure);
            $organizationId = $record->transportRoute->organization_id;
            $vehicle = Vessel::where('organization_id', $organizationId)->findOrFail($data['vessel_id']);
            $route = TransportRoute::firstOrCreate(
                ['organization_id' => $organizationId, 'master_route_id' => $masterRoute->id],
                ['origin_port_id' => $masterRoute->origin_port_id, 'destination_port_id' => $masterRoute->destination_port_id, 'estimated_duration_minutes' => $duration, 'status' => 'active']
            );
            $departureAt = now()->parse($data['departure_time']);
            $record->update([
                'transport_route_id' => $route->id,
                'vessel_id' => $vehicle->id,
                'departure_at' => $departureAt,
                'boarding_starts_at' => $departureAt->copy()->subMinutes(45),
                'estimated_arrival_at' => $duration ? $departureAt->copy()->addMinutes($duration) : null,
                'fare' => $data['price'],
                'status' => $data['status'],
            ]);
        });

        return redirect()->route('admin.supervision.index')->with('success', 'La salida fue actualizada correctamente.');
    }

    private function paginate(Collection $items, Request $request): LengthAwarePaginator
    {
        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    private function durationMinutes(?string $duration): ?int
    {
        if (! $duration) {
            return null;
        }

        preg_match('/(?:(\d+)\s*h)?\s*(?:(\d+)\s*min)?/i', $duration, $matches);
        $minutes = ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0);

        return $minutes ?: null;
    }
}
