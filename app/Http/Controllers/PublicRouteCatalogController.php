<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicRouteCatalogController
{
    public function index(Request $request): View
    {
        if ($request->string('transport')->toString() === 'air') {
            $airDepartures = AirDeparture::with(['airRoute.organization', 'aircraft.organization', 'reservations.seats'])
                ->where('status', 'scheduled')->where('is_published', true)
                ->whereHas('airRoute.organization', fn ($organization) => $organization->where('is_marketplace_paused', false))
                ->where('departure_at', '>=', now())
                ->when($request->filled('origin'), fn ($query) => $query->whereHas('airRoute', fn ($route) => $route->where('origin_city', $request->string('origin')->toString())))
                ->when($request->filled('destination'), fn ($query) => $query->whereHas('airRoute', fn ($route) => $route->where('destination_city', $request->string('destination')->toString())))
                ->when($request->date('date'), fn ($query, $date) => $query->whereDate('departure_at', $date))
                ->orderBy('departure_at')->get();
            $cities = $airDepartures->flatMap(fn ($departure) => [$departure->airRoute->origin_city, $departure->airRoute->destination_city])->filter()->unique()->sort()->values();
            $dateGroups = $airDepartures->groupBy(fn ($departure) => $departure->departure_at->toDateString());

            return view('routes.air', compact('airDepartures', 'cities', 'dateGroups'));
        }
        $departures = RouteDeparture::with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization', 'reservations.seats'])
            ->where('status', 'scheduled')->where('is_published', true)
            ->whereHas('transportRoute.organization', fn ($organization) => $organization->where('is_marketplace_paused', false))
            ->where('departure_at', '>=', now())
            ->when($request->filled('origin'), fn ($query) => $query->whereHas('transportRoute.originPort', fn ($port) => $port->where('city', $request->string('origin')->toString())))
            ->when($request->filled('destination'), fn ($query) => $query->whereHas('transportRoute.destinationPort', fn ($port) => $port->where('city', $request->string('destination')->toString())))
            ->when($request->date('date'), fn ($query, $date) => $query->whereDate('departure_at', $date))
            ->when($request->filled('service'), function ($query) use ($request) {
                $service = $request->string('service')->lower()->toString();
                $query->whereHas('vessel', fn ($vessel) => $vessel->whereRaw('LOWER(vessel_type) LIKE ?', ['%'.$service.'%']));
            })
            ->when($request->filled('duration'), function ($query) use ($request) {
                $duration = $request->string('duration')->toString();
                if ($duration === 'short') {
                    $query->whereHas('transportRoute', fn ($route) => $route->where('estimated_duration_minutes', '<=', 720));
                } if ($duration === 'medium') {
                    $query->whereHas('transportRoute', fn ($route) => $route->whereBetween('estimated_duration_minutes', [721, 2880]));
                } if ($duration === 'long') {
                    $query->whereHas('transportRoute', fn ($route) => $route->where('estimated_duration_minutes', '>', 2880));
                }
            })
            ->orderBy('departure_at')->get()->unique(fn ($departure) => $departure->transport_route_id.'|'.$departure->departure_at->toDateString())->values();
        $cities = $departures->flatMap(fn ($departure) => [$departure->transportRoute->originPort->city, $departure->transportRoute->destinationPort->city])->filter()->unique()->sort()->values();
        $groups = $departures->groupBy(fn ($departure) => $departure->transportRoute->originPort->city.' → '.$departure->transportRoute->destinationPort->city);
        $dateGroups = $departures->groupBy(fn ($departure) => $departure->departure_at->toDateString());
        $whatsappNumber = SystemSetting::value('whatsapp_number');

        return view('routes.index', compact('groups', 'dateGroups', 'cities', 'departures', 'whatsappNumber'));
    }
}
