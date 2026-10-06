<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\Organization;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAirRouteController extends Controller
{
    private function scope()
    {
        return AdminScope::organizationId(auth()->user());
    }

    public function index(): View
    {
        $scope = $this->scope();

        return view('admin.air-routes.index', ['routes' => AirRoute::with('organization')->withCount('departures')->when($scope, fn ($q) => $q->where('organization_id', $scope))->latest()->get(), 'departures' => AirDeparture::with(['airRoute', 'aircraft'])->when($scope, fn ($q) => $q->whereHas('airRoute', fn ($r) => $r->where('organization_id', $scope)))->where('departure_at', '>=', now()->startOfDay())->orderBy('departure_at')->get()]);
    }

    public function edit(AirRoute $airRoute): View
    {
        if ($s = $this->scope()) {
            abort_unless($airRoute->organization_id === $s, 403);
        }

return view('admin.air-routes.form', ['organizations' => Organization::where('type', 'transport_company')->where('status', 'active')->when($s, fn ($q) => $q->whereKey($s))->get(), 'routes' => AirRoute::when($s, fn ($q) => $q->where('organization_id', $s))->where('status', 'active')->get(), 'aircraft' => Aircraft::when($s, fn ($q) => $q->where('organization_id', $s))->where('status', 'ready')->get(), 'airRoute' => $airRoute]);
    }

    public function update(Request $r, AirRoute $airRoute): RedirectResponse
    {
        if ($s = $this->scope()) {
            abort_unless($airRoute->organization_id === $s, 403);
        }$d = $r->validate(['origin_city' => ['required', 'string', 'max:100'], 'destination_city' => ['required', 'different:origin_city', 'string', 'max:100'], 'estimated_duration_minutes' => ['nullable', 'integer', 'min:10'], 'description' => ['nullable', 'string', 'max:1000']]);
        $airRoute->update($d);

        return redirect()->route('admin.air-routes.index')->with('success', 'Ruta aérea actualizada.');
    }

    public function destroy(AirRoute $airRoute): RedirectResponse
    {
        if ($s = $this->scope()) {
            abort_unless($airRoute->organization_id === $s, 403);
        }$airRoute->delete();

        return back()->with('success', 'Ruta aérea eliminada.');
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $r->validate(['organization_id' => ['nullable', 'exists:organizations,id'], 'origin_city' => ['required', 'string', 'max:100'], 'destination_city' => ['required', 'different:origin_city', 'string', 'max:100'], 'estimated_duration_minutes' => ['nullable', 'integer', 'min:10'], 'description' => ['nullable', 'string', 'max:1000']]);
        if ($s = $this->scope()) {
            $d['organization_id'] = $s;
        } else {
            $d['organization_id'] ??= Organization::where('type', 'transport_company')->where('status', 'active')->value('id');
        }AirRoute::create($d + ['code' => 'AIR-'.Str::upper(Str::random(7))]);

        return back()->with('success', 'Ruta aérea registrada.');
    }

    public function storeDeparture(Request $r): RedirectResponse
    {
        $d = $r->validate(['air_route_id' => ['required', 'exists:air_routes,id'], 'aircraft_id' => ['required', 'exists:aircraft,id'], 'departure_at' => ['required', 'date'], 'boarding_starts_at' => ['nullable', 'date'], 'estimated_arrival_at' => ['nullable', 'date'], 'fare' => ['required', 'numeric', 'min:0'], 'cargo_enabled' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $route = AirRoute::findOrFail($d['air_route_id']);
        $aircraft = Aircraft::findOrFail($d['aircraft_id']);
        if ($s = $this->scope()) {
            abort_unless($route->organization_id === $s && $aircraft->organization_id === $s, 403);
        }if ($route->organization_id !== $aircraft->organization_id) {
            return back()->withErrors(['aircraft_id' => 'La aeronave debe pertenecer a la misma empresa.']);
        }AirDeparture::create($d + ['cargo_enabled' => $r->boolean('cargo_enabled'), 'status' => 'scheduled']);

        return back()->with('success', 'Salida aérea programada.');
    }

    public function departureForm(): View
    {
        $s = $this->scope();

        return view('admin.air-routes.departure-form', ['routes' => AirRoute::when($s, fn ($q) => $q->where('organization_id', $s))->where('status', 'active')->get(), 'aircraft' => Aircraft::when($s, fn ($q) => $q->where('organization_id', $s))->where('status', 'ready')->get()]);
    }

    public function form(): View
    {
        $s = $this->scope();

        return view('admin.air-routes.form', ['organizations' => Organization::where('type', 'transport_company')->where('status', 'active')->when($s, fn ($q) => $q->whereKey($s))->get(), 'routes' => AirRoute::when($s, fn ($q) => $q->where('organization_id', $s))->where('status', 'active')->get(), 'aircraft' => Aircraft::when($s,fn ($q) => $q->where('organization_id',$s))->where('status','ready')->get()]);
    }
}
