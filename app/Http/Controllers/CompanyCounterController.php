<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use App\Support\AdminScope;
use App\Support\CompanyBase;
use App\Support\CrewRoster;
use App\Support\DepartureSchedule;
use App\Support\ManifestReport;
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
            ->tap(fn ($query) => DepartureSchedule::onThisLimaDay($query))->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats', 'assignments.member'])->orderBy('departure_at')->get()
            ->map(fn ($item) => $this->row($item, false)) : collect();
        $air = in_array($company->modality, ['aereo', 'mixto'], true) ? AirDeparture::whereHas('airRoute', fn ($query) => $query->where('organization_id', $company->id))
            ->tap(fn ($query) => DepartureSchedule::onThisLimaDay($query))->with(['airRoute', 'aircraft.organization', 'reservations.seats', 'assignments.member'])->orderBy('departure_at')->get()
            ->map(fn ($item) => $this->row($item, true)) : collect();
        $departures = $river->concat($air)->sortBy('departure_at')->values();
        $from = DepartureSchedule::dayBounds()[1];
        $until = $from->copy()->addDays(14);
        $upcomingRiver = in_array($company->modality ?: 'fluvial', ['fluvial', 'mixto'], true) ? RouteDeparture::whereHas('transportRoute', fn ($query) => $query->where('organization_id', $company->id))
            ->where('departure_at', '>', $from)->where('departure_at', '<=', $until)->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])->orderBy('departure_at')->limit(6)->get()
            ->map(fn ($item) => $this->row($item, false)) : collect();
        $upcomingAir = in_array($company->modality, ['aereo', 'mixto'], true) ? AirDeparture::whereHas('airRoute', fn ($query) => $query->where('organization_id', $company->id))
            ->where('departure_at', '>', $from)->where('departure_at', '<=', $until)->with(['airRoute', 'aircraft', 'reservations.seats'])->orderBy('departure_at')->limit(6)->get()
            ->map(fn ($item) => $this->row($item, true)) : collect();
        $upcoming = $upcomingRiver->concat($upcomingAir)->sortBy('departure_at')->take(6)->values();
        $base = CompanyBase::label($company) ?? __('company_portal.requires_implementation');
        $schedule = $departures->map(fn (array $row): array => $row + ['when' => 'today'])
            ->concat($upcoming->map(fn (array $row): array => $row + ['when' => 'later']))
            ->values();

        return view('company.counter', [
            'organization' => $company,
            'company' => $company,
            'schedule' => $schedule,
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
        $confirmedSeats = $departure->reservations
            ->where('status', 'confirmed')
            ->sum(fn ($reservation) => $reservation->seats->count());

        return ['model' => $departure, 'is_air' => $air, 'departure_at' => $departure->departure_at,
            'origin' => $air ? ($route?->origin_city ?? 'Origen') : ($route?->originPort?->city ?? 'Origen'),
            'destination' => $air ? ($route?->destination_city ?? 'Destino') : ($route?->destinationPort?->city ?? 'Destino'),
            'unit' => $unit?->name ?? 'Unidad por asignar', 'capacity' => (int) ($unit?->seat_capacity ?? 0),
            'confirmed_seats' => (int) $confirmedSeats, 'status' => $departure->status,
            'can_board' => CrewRoster::commandReady($departure),
            'load' => $air ? ManifestReport::airLoad($departure) : null,
        ];
    }
}
