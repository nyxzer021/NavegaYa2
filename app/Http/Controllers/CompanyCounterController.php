<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use Illuminate\View\View;

class CompanyCounterController extends Controller
{
    public function index(): View
    {
        $company = auth()->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')->firstOrFail();
        $river = RouteDeparture::whereHas('transportRoute', fn ($query) => $query->where('organization_id', $company->id))
            ->whereDate('departure_at', today())->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])->orderBy('departure_at')->get()
            ->map(fn ($item) => $this->row($item, false));
        $air = AirDeparture::whereHas('airRoute', fn ($query) => $query->where('organization_id', $company->id))
            ->whereDate('departure_at', today())->with(['airRoute', 'aircraft', 'reservations.seats'])->orderBy('departure_at')->get()
            ->map(fn ($item) => $this->row($item, true));
        $departures = $river->concat($air)->sortBy('departure_at')->values();
        $base = $company->vessels()->with('basePort')->first()?->basePort?->name ?? $company->address ?? 'Base operativa';

        return view('company.counter', ['company' => $company, 'departures' => $departures, 'base' => $base, 'riverStatus' => SystemSetting::value('river_status', 'Normal')]);
    }

    private function row($departure, bool $air): array
    {
        $route = $air ? $departure->airRoute : $departure->transportRoute;
        $unit = $air ? $departure->aircraft : $departure->vessel;
        $reservations = $departure->reservations->whereNotIn('status', ['expired', 'cancelled']);

        return ['model' => $departure, 'is_air' => $air, 'departure_at' => $departure->departure_at,
            'origin' => $air ? $route->origin_city : $route->originPort->city, 'destination' => $air ? $route->destination_city : $route->destinationPort->city,
            'unit' => $unit->name, 'capacity' => (int) $unit->seat_capacity, 'booked' => $reservations->sum(fn ($reservation) => $reservation->seats->count()), 'status' => $departure->status];
    }
}
