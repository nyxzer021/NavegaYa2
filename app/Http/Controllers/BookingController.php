<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\VesselSeat;
use App\Services\HammockSale;
use App\Support\DiscountLedger;
use App\Support\FareTable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(RouteDeparture $departure): View
    {
        $this->expireReservations($departure);
        $departure->load(['transportRoute.originPort', 'transportRoute.destinationPort', 'transportRoute.organization', 'vessel.seats']);
        abort_unless(! $departure->transportRoute->organization->is_marketplace_paused && $departure->is_published && $departure->sales_paused_at === null && $departure->status === 'scheduled' && $departure->departure_at->isFuture(), 404);
        $occupiedSeatIds = ReservationSeat::query()->where('route_departure_id', $departure->id)->pluck('vessel_seat_id')->all();
        $cabins = $departure->vessel->seats->where('space_type', 'cabin_bed')->sortBy('code')->values();
        $freeHammocks = $departure->vessel->seats->where('space_type', 'hammock')->filter(
            fn ($seat): bool => $seat->is_available && ! in_array($seat->id, $occupiedSeatIds, true)
        )->count();
        $seats = $departure->vessel->seats->where('space_type', 'seat')->sortBy(['deck', 'row_position', 'column_position']);
        $route = $departure->transportRoute;
        $when = $departure->departure_at;
        $prices = [
            'seat' => FareTable::price($route, 'seat', 'adult', $when, (float) $departure->fare),
            'hammock' => FareTable::price($route, 'hammock', 'adult', $when, null),
            'cabin_bed' => FareTable::price($route, 'cabin_bed', 'adult', $when, null),
        ];
        $day = $when->copy()->timezone('America/Lima')->toDateString();
        $discounts = Discount::query()
            ->where('organization_id', $route->organization_id)
            ->where('status', 'active')
            ->get()
            ->filter(function (Discount $discount) use ($day, $route): bool {
                if (! in_array('web', $discount->channels ?? [], true)) {
                    return false;
                }
                $starts = $discount->starts_on?->toDateString();
                $ends = $discount->ends_on?->toDateString();
                if (($starts !== null && $starts > $day) || ($ends !== null && $ends < $day)) {
                    return false;
                }
                $discount->loadMissing('assignments');

                return $discount->assignments->isEmpty()
                    || $discount->assignments->contains(fn ($row): bool => (int) $row->transport_route_id === (int) $route->id);
            })
            ->values();

        return view('bookings.create', compact('departure', 'seats', 'occupiedSeatIds', 'cabins', 'freeHammocks', 'prices', 'discounts'));
    }

    public function store(Request $request, RouteDeparture $departure): RedirectResponse
    {
        if ($request->input('place') === 'hammock') {
            return $this->storeHammock($request, $departure);
        }
        $request->merge([
            'infants' => collect($request->input('infants', []))
                ->filter(fn ($row): bool => is_array($row) && (filled($row['name'] ?? null) || filled($row['document'] ?? null)))
                ->values()
                ->all(),
        ]);
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'contact_document' => ['nullable', 'string', 'max:40'],
            'seats' => ['required', 'array', 'min:1', 'max:4'],
            'seats.*' => ['required', 'integer', 'distinct', 'exists:vessel_seats,id'],
            'passengers' => ['required', 'array'],
            'passengers.*.name' => ['required', 'string', 'max:150'],
            'passengers.*.document_type' => ['nullable', 'in:DNI,CE,Pasaporte'],
            'passengers.*.document' => ['required', 'string', 'max:40'],
            'passengers.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'infants' => ['nullable', 'array', 'max:4'],
            'infants.*.name' => ['required', 'string', 'max:150'],
            'infants.*.document' => ['required', 'string', 'max:40'],
            'discount_id' => ['nullable', 'integer', 'exists:discounts,id'],
        ]);
        $departure->loadMissing('transportRoute.organization');
        abort_unless(! $departure->transportRoute->organization->is_marketplace_paused && $departure->is_published && $departure->sales_paused_at === null && $departure->status === 'scheduled' && $departure->departure_at->isFuture(), 404);
        if (count($data['seats']) !== count($data['passengers'])) {
            return back()->withInput()->withErrors(['seats' => 'Cada asiento seleccionado debe tener los datos de un pasajero.']);
        }

        try {
            $reservation = DB::transaction(function () use ($data, $departure) {
                RouteDeparture::query()->whereKey($departure->id)->lockForUpdate()->first();
                $this->expireReservations($departure);
                $seatIds = collect($data['seats'])->map(fn ($id) => (int) $id)->values();
                $validSeatIds = $departure->vessel()->firstOrFail()->seats()->where('is_available', true)->whereIn('id', $seatIds)->lockForUpdate()->pluck('id');
                if ($validSeatIds->count() !== $seatIds->count()) {
                    throw new DomainException('Uno o más asientos ya no están disponibles para esta salida.');
                }
                $occupied = ReservationSeat::where('route_departure_id', $departure->id)->whereIn('vessel_seat_id', $seatIds)->exists();
                if ($occupied) {
                    throw new DomainException('Uno de los asientos acaba de ser reservado por otra persona. Elige otro asiento.');
                }
                $chosen = VesselSeat::query()->whereIn('id', $seatIds)->get()->keyBy('id');
                $departure->loadMissing('transportRoute');
                $base = 0.0;
                $vests = 0;
                foreach ($seatIds as $seatId) {
                    $seat = $chosen->get($seatId);
                    $space = $seat?->space_type ?: 'seat';
                    $amount = FareTable::price($departure->transportRoute, $space, 'adult', $departure->departure_at, $space === 'seat' ? (float) $departure->fare : null);
                    if ($amount === null) {
                        throw new DomainException($space === 'cabin_bed' ? 'El camarote no está disponible en esta ruta.' : 'Esa clase no está disponible en esta ruta.');
                    }
                    $base += $amount;
                    $vests += $seat ? FareTable::occupants($seat) : 1;
                }
                $infants = array_values($data['infants'] ?? []);
                if (count($infants) > $seatIds->count()) {
                    throw new DomainException('Solo puede viajar un infante por adulto.');
                }
                foreach ($infants as $index => $infant) {
                    $adultSeat = $chosen->get($seatIds[$index] ?? $seatIds->first());
                    $space = $adultSeat?->space_type ?: 'seat';
                    $infantPrice = FareTable::price($departure->transportRoute, $space, 'infant', $departure->departure_at, null);
                    if ($infantPrice === null) {
                        throw new DomainException('El infante no tiene tarifa en esta ruta.');
                    }
                    $base += $infantPrice;
                    $vests++;
                }
                FareTable::assertVests($departure, $vests);
                $base = round($base, 2);
                $discountAmount = 0.0;
                $discount = null;
                if (! empty($data['discount_id'])) {
                    $discount = Discount::query()->whereKey($data['discount_id'])->lockForUpdate()->first();
                    FareTable::assertDiscount($discount, $departure, 'web', $data['contact_document'] ?? null);
                    $priced = FareTable::net($base, $discount);
                    $discountAmount = $priced['discount'];
                    $base = $priced['base'];
                    $net = $priced['net'];
                } else {
                    $net = $base;
                }
                $reservation = Reservation::create([
                    'user_id' => auth()->id(),
                    'route_departure_id' => $departure->id,
                    'code' => $this->reservationCode(),
                    'contact_name' => $data['contact_name'],
                    'contact_email' => $data['contact_email'],
                    'contact_phone' => $data['contact_phone'],
                    'contact_document' => ($data['contact_document'] ?? null),
                    'base_amount' => $base,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $net,
                    'discount_id' => $discount?->id,
                    'discount_held' => $discount !== null,
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes(10),
                ]);
                if ($discount !== null) {
                    DiscountLedger::redeem($discount);
                }
                foreach ($seatIds as $index => $seatId) {
                    ReservationSeat::create([
                        'reservation_id' => $reservation->id,
                        'route_departure_id' => $departure->id,
                        'vessel_seat_id' => $seatId,
                        'passenger_name' => $data['passengers'][$index]['name'],
                        'document_type' => $data['passengers'][$index]['document_type'] ?? 'DNI',
                        'document_number' => $data['passengers'][$index]['document'],
                        'passenger_age' => $data['passengers'][$index]['age'] ?? null,
                        'passenger_type' => 'adult',
                    ]);
                }
                foreach ($infants as $infant) {
                    ReservationSeat::create([
                        'reservation_id' => $reservation->id,
                        'route_departure_id' => $departure->id,
                        'passenger_name' => $infant['name'],
                        'document_type' => 'DNI',
                        'document_number' => $infant['document'],
                        'passenger_type' => 'infant',
                    ]);
                }

                return $reservation;
            });
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['seats' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['seats' => 'Uno de los asientos acaba de ser reservado. Actualiza el plano y vuelve a elegir.']);
        }

        $request->session()->push('cart_reservation_ids', $reservation->id);
        $request->session()->put('cart_reservation_ids', array_values(array_unique($request->session()->get('cart_reservation_ids', []))));

        return redirect()->route('cart.index');
    }

    private function storeHammock(Request $request, RouteDeparture $departure): RedirectResponse
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'contact_document' => ['nullable', 'string', 'max:40'],
        ]);
        $departure->loadMissing('transportRoute.organization');
        abort_unless(! $departure->transportRoute->organization->is_marketplace_paused && $departure->is_published && $departure->sales_paused_at === null && $departure->status === 'scheduled' && $departure->departure_at->isFuture(), 404);

        try {
            $reservation = app(HammockSale::class)->reserve($departure, [
                'name' => $data['contact_name'],
                'email' => $data['contact_email'],
                'phone' => $data['contact_phone'],
                'document' => $data['contact_document'] ?? null,
            ]);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['place' => $exception->getMessage()]);
        } catch (QueryException) {
            return back()->withInput()->withErrors(['place' => 'Esa hamaca acaba de ser reservada. Actualiza la página e inténtalo de nuevo.']);
        }

        $request->session()->push('cart_reservation_ids', $reservation->id);
        $request->session()->put('cart_reservation_ids', array_values(array_unique($request->session()->get('cart_reservation_ids', []))));

        return redirect()->route('cart.index');
    }

    public function confirmation(string $code): View
    {
        $reservation = Reservation::where('code', $code)->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel', 'seats.seat'])->firstOrFail();
        $this->ensureReservationAccess($reservation);
        $this->expireReservations($reservation->departure);
        $reservation->refresh()->load(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel', 'seats.seat']);

        return view('bookings.confirmation', compact('reservation'));
    }

    private function expireReservations(RouteDeparture $departure): void
    {
        $expiredIds = Reservation::where('route_departure_id', $departure->id)->where('status', 'pending_payment')->whereNotNull('expires_at')->where('expires_at', '<=', now())->pluck('id');
        Reservation::expirePending($expiredIds);
    }

    private function ensureReservationAccess(Reservation $reservation): void
    {
        if ($reservation->user_id) {
            abort_unless(auth()->id() === $reservation->user_id, 403);
        }
    }

    private function reservationCode(): string
    {
        do {
            $code = 'NY-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Reservation::where('code', $code)->exists());

        return $code;
    }
}
