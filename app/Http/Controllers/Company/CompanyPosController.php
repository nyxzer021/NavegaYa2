<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\CashSession;
use App\Models\Discount;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\Reservation;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Services\AgencySale;
use App\Services\CounterSale;
use App\Support\AdminScope;
use App\Support\BaggageAllowance;
use App\Support\DepartureSchedule;
use App\Support\FareTable;
use App\Support\VesselLayout;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyPosController extends Controller
{
    public function index(Request $request, CounterSale $sales): View
    {
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);
        $organization = Organization::query()->where('status', 'active')->findOrFail($organizationId);
        $agency = $this->agency($request, $organizationId);
        $agencySuspended = $agency !== null && ! $agency->isActive();
        $week = $request->query('horizonte') === 'semana';
        [$todayStart, $todayEnd] = DepartureSchedule::dayBounds();
        $until = $week
            ? now()->timezone(DepartureSchedule::ZONE)->addDays(6)->endOfDay()->utc()
            : $todayEnd;
        $departures = $agencySuspended
            ? collect()
            : ($agency
                ? $this->agencyDepartures($organizationId, $agency)
                : $this->openDepartures($organizationId, $todayStart, $until));
        $selected = $departures->firstWhere('id', $request->integer('departure')) ?? $departures->first();
        $heldIds = $selected
            ? RouteDepartureSeatHold::query()->where('route_departure_id', $selected->id)->pluck('vessel_seat_id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $sellableIds = $agency && $selected
            ? RouteDepartureSeatHold::query()->where('route_departure_id', $selected->id)->where('agency_id', $agency->id)->pluck('vessel_seat_id')->map(fn ($id): int => (int) $id)->all()
            : [];
        if ($agency) {
            $heldIds = array_values(array_diff($heldIds, $sellableIds));
        }
        $occupiedIds = $selected ? $sales->busySeatIdsFor($selected->id) : [];
        $decks = $selected ? $this->decks($selected) : [];
        $cabins = $selected ? $selected->vessel->seats->where('space_type', 'cabin_bed')->sortBy('code')->values() : collect();
        $lotHammocks = $agency && $selected
            ? $selected->vessel->seats->where('space_type', 'hammock')->whereIn('id', $sellableIds)->values()
            : collect();
        $hammocks = $selected ? $selected->vessel->seats->where('space_type', 'hammock')->where('is_available', true)->whereNotIn('id', $occupiedIds)->count() : 0;
        $free = $agency
            ? count($sellableIds)
            : ($selected ? $selected->vessel->seats->where('is_available', true)->whereNotIn('id', $occupiedIds)->count() : 0);
        $prices = $selected ? $this->prices($selected) : [];
        $discounts = Discount::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereJsonContains('channels', 'counter')
            ->orderBy('name')
            ->get(['id', 'name', 'kind', 'value']);
        $sites = OrganizationSite::query()->where('organization_id', $organizationId)->where('is_active', true)->orderBy('name')->get();
        if (! $agency && $request->user()->organization_site_id) {
            $sites = $sites->where('id', $request->user()->organization_site_id)->values();
        }
        $allowance = BaggageAllowance::for($organization);
        $later = ($departures->isEmpty() && ! $agency)
            ? $this->openDepartures($organizationId, $todayEnd, now()->timezone(DepartureSchedule::ZONE)->addDays(6)->endOfDay()->utc())
            : collect();

        return view('company.pos.index', [
            'organization' => $organization,
            'departures' => $departures,
            'selectedDeparture' => $selected,
            'week' => $week,
            'heldIds' => $heldIds,
            'sellableIds' => $sellableIds,
            'occupiedIds' => $occupiedIds,
            'decks' => $decks,
            'cabins' => $cabins,
            'hammocks' => $hammocks,
            'lotHammocks' => $lotHammocks,
            'freeSeats' => $free,
            'prices' => $prices,
            'discounts' => $agency ? collect() : $discounts,
            'sites' => $agency ? collect() : $sites,
            'allowance' => $allowance,
            'later' => $later,
            'todayCount' => $departures->filter(fn (RouteDeparture $departure): bool => $departure->departure_at->between($todayStart, $todayEnd))->count(),
            'agency' => $agency,
            'agencySuspended' => $agencySuspended,
            'cashSessions' => $agency ? collect() : CashSession::query()->where('user_id', $request->user()->id)->where('organization_id', $organizationId)->whereNull('closed_at')->get(),
        ]);
    }

    public function store(Request $request, CounterSale $sales, AgencySale $agencySales): RedirectResponse
    {
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);
        $departure = RouteDeparture::with('transportRoute')->find($request->integer('departure_id'));
        if ($departure && (int) $departure->transportRoute->organization_id !== (int) $organizationId) {
            abort(403);
        }
        if ($departure) {
            $sales->assertOpen($departure);
        }
        $rules = [
            'departure_id' => ['required', 'integer'],
            'place' => ['nullable', 'in:hammock'],
            'seat_id' => ['required_unless:place,hammock', 'nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'document_type' => ['required', 'in:DNI,CE,Pasaporte'],
            'document_number' => ['required', 'string', 'max:40'],
            'guardian_document' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'passenger_type' => ['required', 'in:'.implode(',', CounterSale::PASSENGERS)],
            'discount_id' => ['nullable', 'integer'],
            'hand_kg' => ['nullable', 'integer', 'min:0', 'max:200'],
            'hold_kg' => ['nullable', 'integer', 'min:0', 'max:500'],
            'method' => ['required', 'in:cash,yape,card'],
            'cash_tendered' => ['required_if:method,cash', 'nullable', 'numeric', 'min:0'],
            'operation_number' => ['required_if:method,yape,card', 'nullable', 'string', 'max:40'],
        ];
        $agency = $this->agency($request, $organizationId);
        if ($agency) {
            $rules['sale_price'] = ['required', 'numeric', 'min:0'];
        }
        $data = $request->validate($rules, [
            'operation_number.required_if' => 'El número de operación es obligatorio.',
            'cash_tendered.required_if' => 'Indica el monto recibido.',
            'sale_price.required' => 'Indica el precio de venta.',
        ]);

        if (! $departure) {
            throw ValidationException::withMessages(['departure_id' => 'Elige una salida abierta.']);
        }

        try {
            $sale = $agency
                ? $agencySales->sell($request->user(), $agency, $departure, $data)
                : $sales->sell($request->user(), $departure, $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['seat_id' => $exception->getMessage()]);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('tickets.show', $sale['reservation']->code)
            ->with('success', __('pos.sold'))
            ->with('cargo_offer', $agency || ! $sale['cargo'] ? null : route('company.parcels.index', array_filter([
                'remitente' => trim($data['first_name'].' '.$data['last_name']),
                'documento' => $data['document_number'],
                'telefono' => $data['phone'] ?? null,
                'peso' => ((int) ($data['hand_kg'] ?? 0)) + ((int) ($data['hold_kg'] ?? 0)),
                'salida' => $departure->id,
            ])));
    }

    public function void(Request $request, Reservation $reservation, CounterSale $sales): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        try {
            $sales->void($request, $reservation, $data['reason']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('company.pos.index')->with('success', __('pos.voided'));
    }

    /**
     * @return Collection<int, RouteDeparture>
     */
    private function openDepartures(int $organizationId, mixed $from, mixed $until)
    {
        return RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->where('departure_at', '>', now())
            ->where('departure_at', '<=', $until)
            ->where('departure_at', '>=', $from)
            ->where('is_published', true)
            ->whereIn('status', CounterSale::OPEN_STATUSES)
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.seats'])
            ->orderBy('departure_at')
            ->get();
    }

    /**
     * @return list<array{label: string, plan: array<string, mixed>}>
     */
    private function decks(RouteDeparture $departure): array
    {
        $decks = [];
        $seats = $departure->vessel->seats->where('space_type', 'seat')->sortBy(['deck', 'row_position', 'column_position']);
        foreach ($seats->groupBy('deck') as $deck => $deckSeats) {
            $decks[] = [
                'deck' => (string) $deck,
                'plan' => VesselLayout::grid($departure->vessel, (string) $deck, $deckSeats),
            ];
        }

        return $decks;
    }

    /**
     * @return array<string, array<string, ?float>>
     */
    private function prices(RouteDeparture $departure): array
    {
        $prices = [];
        foreach (['seat', 'hammock', 'cabin_bed'] as $space) {
            foreach (CounterSale::PASSENGERS as $passenger) {
                $prices[$space][$passenger] = FareTable::price(
                    $departure->transportRoute,
                    $space,
                    $passenger,
                    $departure->departure_at,
                    $space === 'seat' ? (float) $departure->fare : null,
                );
            }
        }

        return $prices;
    }

    private function agency(Request $request, int $organizationId): ?Agency
    {
        $id = $request->user()?->agency_id;
        if (! $id) {
            return null;
        }
        $agency = Agency::query()->whereKey($id)->first();
        abort_unless($agency && (int) $agency->organization_id === $organizationId, 403);

        return $agency;
    }

    /**
     * @return Collection<int, RouteDeparture>
     */
    private function agencyDepartures(int $organizationId, Agency $agency)
    {
        $ids = RouteDepartureSeatHold::query()->where('agency_id', $agency->id)->pluck('route_departure_id');

        return RouteDeparture::query()
            ->whereIn('id', $ids)
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->where('departure_at', '>', now())
            ->where('is_published', true)
            ->whereIn('status', CounterSale::OPEN_STATUSES)
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.seats'])
            ->orderBy('departure_at')
            ->get();
    }
}
