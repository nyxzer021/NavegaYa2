<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyDepartureController as BaseCompanyDepartureController;
use App\Http\Requests\Admin\StoreRouteDepartureRequest;
use App\Models\DepartureSeries;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use App\Support\AdminScope;
use App\Support\CrewRoster;
use App\Support\DepartureSchedule;
use App\Support\FleetPlan;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyDepartureController extends BaseCompanyDepartureController
{
    public function store(StoreRouteDepartureRequest $request): RedirectResponse
    {
        $this->authorizeManager();
        if ($request->input('schedule_kind') === 'series') {
            return $this->storeSeries($request);
        }
        $organization = $this->organization();
        $data = $request->validated();
        $masterRoute = MasterRoute::query()
            ->where('status', 'active')
            ->where('modality', 'fluvial')
            ->findOrFail($data['master_route_id']);
        $vessel = Vessel::query()->findOrFail($data['vessel_id']);
        abort_unless($vessel->organization_id === $organization->id, 403);
        if (! FleetPlan::isComplete($vessel)) {
            return back()->withInput()->withErrors(['vessel_id' => FleetPlan::missingText($vessel)]);
        }

        $route = TransportRoute::firstOrCreate(
            ['organization_id' => $organization->id, 'master_route_id' => $masterRoute->id],
            [
                'origin_port_id' => $masterRoute->origin_port_id,
                'destination_port_id' => $masterRoute->destination_port_id,
                'estimated_duration_minutes' => $this->durationMinutes($masterRoute->estimated_duration_text),
                'status' => 'active',
                'description' => 'Ruta operativa basada en el tramo maestro '.$masterRoute->code,
            ]
        );

        if ($route->status !== 'active') {
            throw ValidationException::withMessages(['master_route_id' => 'La ruta está desactivada. Actívala antes de programar.']);
        }
        $when = Carbon::parse($data['departure_at']);
        $this->guardUnit($vessel, $route, $when);

        unset($data['master_route_id']);
        DB::transaction(function () use ($data, $request, $route, $vessel): void {
            $departure = RouteDeparture::create($data + [
                'transport_route_id' => $route->id,
                'vessel_id' => $vessel->id,
                'cargo_enabled' => $request->boolean('cargo_enabled'),
                'cargo_payment_location' => 'terminal',
                'status' => 'scheduled',
            ]);
            $this->assignCrew($request, $departure);
        });
        $total = number_format(DepartureSchedule::webTotal((float) $request->input('fare'), $organization), 2);

        return redirect()->route('company.departures.index')->with('success', 'Salida programada. El viajero web pagará S/ '.$total.'.');
    }

    public function updateSeries(StoreRouteDepartureRequest $request, DepartureSeries $series): RedirectResponse
    {
        return $this->replaceSeries($request, $series);
    }

    public function destroySeries(DepartureSeries $series): RedirectResponse
    {
        $this->authorizeManager();
        abort_unless($series->organization_id === $this->organization()->id, 404);
        $series->riverDepartures()
            ->where('departure_at', '>=', now())
            ->whereDoesntHave('reservations', fn ($query) => $query->whereIn('status', ['pending', 'confirmed']))
            ->delete();
        if ($series->riverDepartures()->exists() || $series->airDepartures()->exists()) {
            $series->update(['status' => 'retired']);
        } else {
            $series->delete();
        }

        return back()->with('success', 'La serie ya no genera salidas. Las que tienen ventas se conservan.');
    }

    public function manifest(RouteDeparture $departure): View
    {
        $organization = $this->organization();
        abort_unless($departure->transportRoute()->where('organization_id', $organization->id)->exists(), 403);

        $departure->load(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization']);
        $organization->loadMissing('vessels.basePort');
        $passengers = $departure->reservations()->whereNotIn('status', ['expired', 'cancelled'])
            ->with(['seats.seat', 'seats.ticket'])->get()->flatMap->seats
            ->sortBy(fn ($seat) => ($seat->seat?->row_position ?? 0) * 100 + ($seat->seat?->column_position ?? 0))->values();

        return view('company.manifests.show', [
            'organization' => $organization,
            'departure' => $departure,
            'passengers' => $passengers,
            'destination' => $departure->transportRoute->destinationPort->city,
        ]);
    }

    private function storeSeries(StoreRouteDepartureRequest $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validated();
        $span = $request->validate([
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $this->guardSpan($span['starts_on'], $span['ends_on']);
        [$route, $vessel] = $this->operationalRoute($organization, $data);
        $time = Carbon::parse($data['departure_at'])->timezone(DepartureSchedule::ZONE)->format('H:i');
        $series = DepartureSeries::create([
            'organization_id' => $organization->id,
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'weekdays' => array_values(array_map('intval', $span['weekdays'])),
            'departure_time' => $time,
            'starts_on' => $span['starts_on'],
            'ends_on' => $span['ends_on'],
            'boarding_offset_minutes' => 45,
            'fare' => $data['fare'],
            'cargo_enabled' => $request->boolean('cargo_enabled'),
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ]);
        $created = $this->fillSeries($series, $route, $vessel, $skipped, $this->captainId($request), $this->crewIds($request), $skippedCrew);
        $total = number_format(DepartureSchedule::webTotal((float) $data['fare'], $organization), 2);
        $message = 'Se crearon '.$created.' salidas. El viajero web pagará S/ '.$total.'.';
        if ($skipped !== []) {
            $message .= ' '.count($skipped).' fechas no se crearon: '.implode(', ', $skipped).'.';
        }
        if ($skippedCrew !== []) {
            $message .= ' La tripulación no se asignó en: '.implode(', ', $skippedCrew).'.';
        }

        return redirect()->route('company.departures.index')->with('success', $message);
    }

    private function replaceSeries(StoreRouteDepartureRequest $request, DepartureSeries $series): RedirectResponse
    {
        $this->authorizeManager();
        $organization = $this->organization();
        abort_unless($series->organization_id === $organization->id, 404);
        $data = $request->validated();
        $span = $request->validate([
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $this->guardSpan($span['starts_on'], $span['ends_on']);
        $route = $series->transportRoute;
        $vessel = $series->vessel;
        abort_unless($route && $vessel && $route->status === 'active', 422);
        $time = Carbon::parse($data['departure_at'])->timezone(DepartureSchedule::ZONE)->format('H:i');
        $series->update([
            'weekdays' => array_values(array_map('intval', $span['weekdays'])),
            'departure_time' => $time,
            'starts_on' => $span['starts_on'],
            'ends_on' => $span['ends_on'],
            'fare' => $data['fare'],
        ]);
        $wanted = collect(DepartureSchedule::dates($span['starts_on'], $span['ends_on'], $series->weekdays, $time))
            ->map(fn (Carbon $moment) => $moment->format('Y-m-d H:i:s'));
        $series->riverDepartures()
            ->where('departure_at', '>=', now())
            ->whereDoesntHave('reservations', fn ($query) => $query->whereIn('status', ['pending', 'confirmed']))
            ->get()
            ->each(function (RouteDeparture $departure) use ($wanted): void {
                if (! $wanted->contains($departure->departure_at->format('Y-m-d H:i:s'))) {
                    $departure->delete();
                }
            });
        $created = $this->fillSeries($series->fresh(), $route, $vessel, $skipped, $this->captainId($request), $this->crewIds($request), $skippedCrew);
        $message = 'Serie actualizada. Se agregaron '.$created.' salidas.';
        if ($skipped !== []) {
            $message .= ' '.count($skipped).' fechas no se crearon: '.implode(', ', $skipped).'.';
        }
        if ($skippedCrew !== []) {
            $message .= ' La tripulación no se asignó en: '.implode(', ', $skippedCrew).'.';
        }

        return back()->with('success', $message);
    }

    /**
     * @param  array{master_route_id: int, vessel_id: int}  $data
     * @return array{0: TransportRoute, 1: Vessel}
     */
    private function operationalRoute(Organization $organization, array $data): array
    {
        $masterRoute = MasterRoute::query()->where('status', 'active')->where('modality', 'fluvial')->findOrFail($data['master_route_id']);
        $vessel = Vessel::query()->findOrFail($data['vessel_id']);
        abort_unless($vessel->organization_id === $organization->id, 403);
        if (! FleetPlan::isComplete($vessel)) {
            throw ValidationException::withMessages(['vessel_id' => FleetPlan::missingText($vessel)]);
        }
        $route = TransportRoute::firstOrCreate(
            ['organization_id' => $organization->id, 'master_route_id' => $masterRoute->id],
            [
                'origin_port_id' => $masterRoute->origin_port_id,
                'destination_port_id' => $masterRoute->destination_port_id,
                'estimated_duration_minutes' => $this->durationMinutes($masterRoute->estimated_duration_text),
                'status' => 'active',
                'description' => 'Ruta operativa basada en el tramo maestro '.$masterRoute->code,
            ]
        );
        if ($route->status !== 'active') {
            throw ValidationException::withMessages(['master_route_id' => 'La ruta está desactivada. Actívala antes de programar.']);
        }

        return [$route, $vessel];
    }

    /**
     * @param  list<string>  $skipped
     */
    private function fillSeries(DepartureSeries $series, TransportRoute $route, Vessel $vessel, ?array &$skipped = null, ?int $captainId = null, array $crewIds = [], ?array &$skippedCrew = null): int
    {
        $skipped = [];
        $skippedCrew = [];
        $created = 0;
        $moments = DepartureSchedule::dates($series->starts_on->toDateString(), $series->ends_on->toDateString(), $series->weekdays, $series->departure_time);
        foreach ($moments as $moment) {
            if ($series->riverDepartures()->where('departure_at', $moment)->exists()) {
                continue;
            }
            if (DepartureSchedule::blockedOn($vessel, $moment) || DepartureSchedule::conflicts($vessel, $moment, (int) $route->estimated_duration_minutes)->isNotEmpty()) {
                $skipped[] = DepartureSchedule::label($moment);

                continue;
            }
            $departure = RouteDeparture::create([
                'transport_route_id' => $route->id,
                'vessel_id' => $vessel->id,
                'departure_series_id' => $series->id,
                'departure_at' => $moment,
                'boarding_starts_at' => $moment->copy()->subMinutes(45),
                'estimated_arrival_at' => $moment->copy()->addMinutes(max(1, (int) $route->estimated_duration_minutes)),
                'fare' => $series->fare,
                'cargo_enabled' => $series->cargo_enabled,
                'cargo_payment_location' => 'terminal',
                'notes' => $series->notes,
                'status' => 'scheduled',
            ]);
            if ($captainId || $crewIds !== []) {
                try {
                    CrewRoster::sync($departure, $captainId, $crewIds);
                } catch (ValidationException) {
                    $skippedCrew[] = DepartureSchedule::label($moment);
                }
            }
            $created++;
        }

        return $created;
    }

    private function guardSpan(string $startsOn, string $endsOn): void
    {
        $days = Carbon::parse($startsOn, DepartureSchedule::ZONE)->startOfDay()->diffInDays(Carbon::parse($endsOn, DepartureSchedule::ZONE)->startOfDay());
        if ($days > 90) {
            throw ValidationException::withMessages(['ends_on' => 'La serie no puede pasar de 90 días.']);
        }
    }

    private function guardUnit(Vessel $vessel, TransportRoute $route, Carbon $when, ?int $ignoreId = null): void
    {
        if (! FleetPlan::isComplete($vessel) || $vessel->status !== 'ready' || DepartureSchedule::blockedOn($vessel, $when)) {
            throw ValidationException::withMessages(['vessel_id' => 'Solo se programa una unidad habilitada, con el plano completo y sin mantenimiento ese día.']);
        }
        if (DepartureSchedule::conflicts($vessel, $when, (int) $route->estimated_duration_minutes, $ignoreId)->isNotEmpty()) {
            throw ValidationException::withMessages(['departure_at' => 'Esa unidad ya tiene una salida que se cruza con este horario.']);
        }
    }

    private function assignCrew(StoreRouteDepartureRequest $request, RouteDeparture $departure): void
    {
        if (! $request->exists('captain_id') && ! $request->exists('crew_ids')) {
            return;
        }
        CrewRoster::sync($departure, $this->captainId($request), $this->crewIds($request));
    }

    private function captainId(StoreRouteDepartureRequest $request): ?int
    {
        return $request->filled('captain_id') ? $request->integer('captain_id') : null;
    }

    /**
     * @return list<int>
     */
    private function crewIds(StoreRouteDepartureRequest $request): array
    {
        return array_values(array_filter(array_map('intval', (array) $request->input('crew_ids', []))));
    }

    private function organization(): Organization
    {
        return Organization::query()
            ->whereKey(AdminScope::organizationId(auth()->user()))
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();
    }

    private function authorizeManager(): void
    {
        abort_unless(auth()->user()->roles()->where('code', 'company_admin')->exists(), 403);
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
}
