<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use App\Support\AdminScope;
use Illuminate\View\View;

class CompanyCounterController extends Controller
{
    public function index(): View
    {
        $company = Organization::with('vessels.basePort')
            ->whereKey(AdminScope::organizationId(auth()->user()))
            ->where('status', 'active')
            ->firstOrFail();
        $river = in_array($company->modality ?: 'fluvial', ['fluvial', 'mixto'], true) ? RouteDeparture::whereHas('transportRoute', fn ($query) => $query->where('organization_id', $company->id))
            ->whereDate('departure_at', today())->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])->orderBy('departure_at')->get()
            ->map(fn ($item) => $this->row($item, false)) : collect();
        $air = in_array($company->modality, ['aereo', 'mixto'], true) ? AirDeparture::whereHas('airRoute', fn ($query) => $query->where('organization_id', $company->id))
            ->whereDate('departure_at', today())->with(['airRoute', 'aircraft', 'reservations.seats'])->orderBy('departure_at')->get()
            ->map(fn ($item) => $this->row($item, true)) : collect();
        $departures = $river->concat($air)->sortBy('departure_at')->values();
        $base = $company->vessels()->with('basePort')->first()?->basePort?->name ?? $company->address ?? 'Base operativa';

        return view('company.counter', [
            'organization' => $company,
            'company' => $company,
            'departures' => $departures,
            'base' => $base,
            'riverStatus' => match (strtolower((string) SystemSetting::value('river_status', 'normal'))) {
                'rising' => 'En aumento',
                'high' => 'Alto',
                'falling' => 'En descenso',
                'low' => 'Bajo',
                default => 'Normal',
            },
        ]);
    }

    private function row($departure, bool $air): array
    {
        $route = $air ? $departure->airRoute : $departure->transportRoute;
        $unit = $air ? $departure->aircraft : $departure->vessel;
        $reservations = $departure->reservations->whereNotIn('status', ['expired', 'cancelled']);

        return ['model' => $departure, 'is_air' => $air, 'departure_at' => $departure->departure_at,
            'origin' => $air ? ($route?->origin_city ?? 'Origen') : ($route?->originPort?->city ?? 'Origen'),
            'destination' => $air ? ($route?->destination_city ?? 'Destino') : ($route?->destinationPort?->city ?? 'Destino'),
            'unit' => $unit?->name ?? 'Unidad por asignar', 'capacity' => (int) ($unit?->seat_capacity ?? 0),
            'booked' => $reservations->sum(fn ($reservation) => $reservation->seats->count()), 'status' => $departure->status];
    }
}
