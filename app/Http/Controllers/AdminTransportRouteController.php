<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreRouteDepartureRequest;
use App\Http\Requests\Admin\StoreTransportRouteRequest;
use App\Models\CheckoutOrder;
use App\Models\DestinationCity;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\Vessel;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminTransportRouteController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = AdminScope::organizationId(auth()->user());
        $selectedOrganizationId = $organizationId ?: ($request->filled('organization_id') ? $request->integer('organization_id') : null);
        if ($selectedOrganizationId) {
            abort_unless(Organization::whereKey($selectedOrganizationId)->where('type', 'transport_company')->where('status', 'active')->exists(), 404);
        }

        $departures = RouteDeparture::with([
            'transportRoute.organization', 'transportRoute.originPort', 'transportRoute.destinationPort',
            'vessel.organization', 'reservations.seats',
        ])->where('departure_at', '>=', now()->startOfDay())
            ->when($selectedOrganizationId, fn ($query, $id) => $query->whereHas('transportRoute', fn ($route) => $route->where('organization_id', $id)))
            ->orderBy('departure_at')->limit(100)->get()
            ->reject(fn ($departure) => $departure->transportRoute->origin_port_id === $departure->transportRoute->destination_port_id
                || mb_strtolower(trim($departure->transportRoute->originPort->city)) === mb_strtolower(trim($departure->transportRoute->destinationPort->city)))
            ->values();

        $companies = Organization::where('type', 'transport_company')->where('status', 'active')
            ->when($organizationId, fn ($query, $id) => $query->whereKey($id))
            ->when($selectedOrganizationId, fn ($query, $id) => $query->whereKey($id))
            ->withCount([
                'vessels as active_vessels_count' => fn ($query) => $query->where('status', 'ready'),
                'aircraft as active_aircraft_count' => fn ($query) => $query->where('status', 'ready'),
            ])->withMin('vessels', 'inspection_expires_at')->orderBy('commercial_name')->orderBy('legal_name')->get();

        $isSuperAdmin = auth()->user()->roles->contains('code', 'super_admin');
        $confirmedOrdersToday = CheckoutOrder::query()->where('status', 'confirmed')->whereDate('updated_at', today());

        return view('admin.transport-routes.index', [
            'routes' => TransportRoute::with(['organization', 'originPort', 'destinationPort'])
                ->withCount(['departures' => fn ($query) => $query->where('departure_at', '>=', now()->startOfDay())])
                ->whereColumn('origin_port_id', '!=', 'destination_port_id')
                ->when($selectedOrganizationId, fn ($query, $id) => $query->where('organization_id', $id))
                ->latest()
                ->paginate(20)->withQueryString(),
            'departures' => $departures,
            'groupedDepartures' => $departures->groupBy(fn ($departure) => $departure->transportRoute->organization_id),
            'companies' => $companies,
            'selectedOrganizationId' => $selectedOrganizationId,
            'isSuperAdmin' => $isSuperAdmin,
            'todayTickets' => $isSuperAdmin ? Ticket::query()->whereDate('issued_at', today())->count() : 0,
            'globalRevenue' => $isSuperAdmin ? (float) (clone $confirmedOrdersToday)->sum('total_amount') : 0,
            'netCommission' => $isSuperAdmin ? (float) (clone $confirmedOrdersToday)->sum('platform_fee_amount') : 0,
            'originPorts' => Port::query()
                ->where('is_active', true)
                ->when($organizationId, fn ($query, $id) => $query->whereHas('originRoutes', fn ($route) => $route->where('organization_id', $id)))
                ->whereHas('originRoutes')
                ->orderBy('city')
                ->orderBy('name')
                ->get(['id', 'name', 'city']),
        ]);
    }

    public function create(): View
    {
        $this->authorizeSuperAdmin();

        return view('admin.transport-routes.create', $this->routeFormData());
    }

    public function store(StoreTransportRouteRequest $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();
        $data = $this->validatedRouteData($request);
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            $data['organization_id'] = $organizationId;
        }
        TransportRoute::create($data + ['status' => 'active']);

        return redirect()->route('admin.transport-routes.index')->with('success', 'Ruta fluvial registrada. Ahora puedes programar una salida.');
    }

    public function portsByCity(string $city): JsonResponse
    {
        return response()->json(
            Port::query()->where('is_active', true)->where('city', $city)->orderBy('name')->get(['id', 'name', 'city'])
        );
    }

    public function edit(TransportRoute $transportRoute): View
    {
        $this->authorizeSuperAdmin();
        $transportRoute->load(['originPort', 'destinationPort']);

        return view('admin.transport-routes.create', $this->routeFormData($transportRoute));
    }

    public function update(StoreTransportRouteRequest $request, TransportRoute $transportRoute): RedirectResponse
    {
        $this->authorizeSuperAdmin();
        $data = $this->validatedRouteData($request);
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            $data['organization_id'] = $organizationId;
        }
        $transportRoute->update($data);

        return redirect()->route('admin.transport-routes.index')->with('success', 'Ruta fluvial actualizada correctamente.');
    }

    public function createDeparture(Request $request): View
    {
        $this->authorizeCompanyAdmin();

        return view('admin.transport-routes.departure-create', $this->departureFormData(null, $request->integer('master_route')));
    }

    public function editDeparture(RouteDeparture $departure): View
    {
        $this->authorizeCompanyAdmin();
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            abort_unless($departure->transportRoute()->where('organization_id', $organizationId)->exists(), 403);
        }

        return view('admin.transport-routes.departure-create', $this->departureFormData($departure, $departure->transportRoute->master_route_id));
    }

    public function updateDeparture(StoreRouteDepartureRequest $request, RouteDeparture $departure): RedirectResponse
    {
        $this->authorizeCompanyAdmin();
        $data = $this->validatedDepartureData($request, $departure);
        $departure->update($data + ['cargo_enabled' => $request->boolean('cargo_enabled')]);

        return redirect()->route('company.departures.index')->with('success', 'Salida reprogramada correctamente.');
    }

    private function departureFormData(?RouteDeparture $departureRecord, ?int $selectedRouteId): array
    {
        $organization = Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->with('vessels.basePort')->firstOrFail();

        return [
            'organization' => $organization,
            'routes' => MasterRoute::with(['originPort', 'destinationPort'])->where('status', 'active')->where('modality', 'fluvial')->orderBy('origin_city')->orderBy('destination_city')->get(),
            'vessels' => Vessel::with('organization')->where('status', 'ready')->when(AdminScope::organizationId(auth()->user()), fn ($query, $id) => $query->where('organization_id', $id))->orderBy('name')->get(),
            'selectedRouteId' => $selectedRouteId,
            'departureRecord' => $departureRecord,
            'companyContext' => true,
        ];
    }

    public function storeDeparture(StoreRouteDepartureRequest $request): RedirectResponse
    {
        $this->authorizeCompanyAdmin();
        $data = $this->validatedDepartureData($request);
        RouteDeparture::create($data + [
            'cargo_enabled' => $request->boolean('cargo_enabled'),
            'included_baggage_kg' => 25,
            'cargo_payment_location' => 'terminal',
            'status' => 'scheduled',
        ]);

        return redirect()->route('company.departures.index')->with('success', 'Salida programada correctamente.');
    }

    public function updateDepartureStatus(Request $request, RouteDeparture $departure): RedirectResponse
    {
        $this->authorizeCompanyAdmin();
        $organizationId = AdminScope::organizationId(auth()->user());
        abort_unless($organizationId && $departure->transportRoute()->where('organization_id', $organizationId)->exists(), 403);
        $data = $request->validate(['status' => ['required', 'in:scheduled,departed,cancelled']]);
        $departure->update($data);

        return back()->with('success', 'Estado de la salida actualizado.');
    }

    private function validatedDepartureData(StoreRouteDepartureRequest $request, ?RouteDeparture $departure = null): array
    {
        $data = $request->validated();
        $masterRoute = MasterRoute::query()->where('status', 'active')->where('modality', 'fluvial')->findOrFail($data['master_route_id']);
        $vessel = Vessel::findOrFail($data['vessel_id']);
        $organizationId = AdminScope::organizationId(auth()->user());
        if ($organizationId) {
            abort_unless($vessel->organization_id === $organizationId, 403);
            if ($departure) {
                abort_unless($departure->transportRoute->organization_id === $organizationId, 403);
            }
        }

        $route = TransportRoute::firstOrCreate(
            ['organization_id' => $organizationId, 'master_route_id' => $masterRoute->id],
            [
                'origin_port_id' => $masterRoute->origin_port_id,
                'destination_port_id' => $masterRoute->destination_port_id,
                'estimated_duration_minutes' => $this->durationMinutes($masterRoute->estimated_duration_text),
                'status' => 'active',
                'description' => 'Ruta operativa basada en el tramo maestro '.$masterRoute->code,
            ]
        );

        $data['transport_route_id'] = $route->id;
        unset($data['master_route_id']);

        return $data;
    }

    private function durationMinutes(?string $duration): ?int
    {
        if (! $duration) {
            return null;
        }

        preg_match('/(?:(\d+)\s*h)?\s*(?:(\d+)\s*m(?:in)?)?/i', $duration, $matches);
        $minutes = ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0);

        return $minutes > 0 ? $minutes : null;
    }

    private function validatedRouteData(StoreTransportRouteRequest $request): array
    {
        $data = $request->validated();
        $origin = Port::where('is_active', true)->findOrFail($data['origin_port_id']);
        $destination = Port::where('is_active', true)->findOrFail($data['destination_port_id']);

        if ($origin->city !== $data['origin_city']) {
            throw ValidationException::withMessages(['origin_port_id' => 'El puerto de salida no pertenece a la ciudad de origen seleccionada.']);
        }
        if ($destination->city !== $data['destination_city']) {
            throw ValidationException::withMessages(['destination_port_id' => 'El puerto de llegada no pertenece a la ciudad de destino seleccionada.']);
        }
        if (mb_strtolower(trim($origin->city)) === mb_strtolower(trim($destination->city))) {
            throw ValidationException::withMessages(['destination_city' => 'El destino debe ser una ciudad diferente al origen.']);
        }

        unset($data['origin_city'], $data['destination_city']);

        return $data;
    }

    private function routeFormData(?TransportRoute $routeRecord = null): array
    {
        $originPort = old('origin_port_id') ? Port::find(old('origin_port_id')) : $routeRecord?->originPort;
        $destinationPort = old('destination_port_id') ? Port::find(old('destination_port_id')) : $routeRecord?->destinationPort;

        return [
            'routeRecord' => $routeRecord,
            'organizations' => Organization::where('type', 'transport_company')->where('status', 'active')->when(AdminScope::organizationId(auth()->user()), fn ($query, $id) => $query->whereKey($id))->orderBy('legal_name')->get(),
            'cities' => DestinationCity::query()->where('is_active', true)->orderBy('name')->pluck('name'),
            'initialOriginPorts' => $originPort ? Port::where('is_active', true)->where('city', $originPort->city)->orderBy('name')->get() : collect(),
            'initialDestinationPorts' => $destinationPort ? Port::where('is_active', true)->where('city', $destinationPort->city)->orderBy('name')->get() : collect(),
        ];
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->roles()->where('code', 'super_admin')->exists(), 403);
    }

    private function authorizeCompanyAdmin(): void
    {
        abort_unless(auth()->user()?->roles()->where('code', 'company_admin')->exists(), 403);
        abort_unless(AdminScope::organizationId(auth()->user()), 403);
    }
}
