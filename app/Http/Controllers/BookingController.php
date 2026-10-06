<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
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
        abort_unless(! $departure->transportRoute->organization->is_marketplace_paused && $departure->is_published && $departure->status === 'scheduled' && $departure->departure_at->isFuture(), 404);
        $occupiedSeatIds = ReservationSeat::query()->where('route_departure_id', $departure->id)->pluck('vessel_seat_id')->all();
        $seats = $departure->vessel->seats->where('is_available', true)->sortBy(['deck', 'row_position', 'column_position']);

        return view('bookings.create', compact('departure', 'seats', 'occupiedSeatIds'));
    }

    public function store(Request $request, RouteDeparture $departure): RedirectResponse
    {
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
        ]);
        $departure->loadMissing('transportRoute.organization');
        abort_unless(! $departure->transportRoute->organization->is_marketplace_paused && $departure->is_published && $departure->status === 'scheduled' && $departure->departure_at->isFuture(), 404);
        if (count($data['seats']) !== count($data['passengers'])) {
            return back()->withInput()->withErrors(['seats' => 'Cada asiento seleccionado debe tener los datos de un pasajero.']);
        }

        try {
            $reservation = DB::transaction(function () use ($data, $departure) {
                $this->expireReservations($departure);
                $seatIds = collect($data['seats'])->map(fn ($id) => (int) $id)->values();
                $validSeatIds = $departure->vessel()->firstOrFail()->seats()->where('is_available', true)->whereIn('id', $seatIds)->lockForUpdate()->pluck('id');
                if ($validSeatIds->count() !== $seatIds->count()) {
                    throw new \DomainException('Uno o más asientos ya no están disponibles para esta salida.');
                }
                $occupied = ReservationSeat::where('route_departure_id', $departure->id)->whereIn('vessel_seat_id', $seatIds)->exists();
                if ($occupied) {
                    throw new \DomainException('Uno de los asientos acaba de ser reservado por otra persona. Elige otro asiento.');
                }
                $reservation = Reservation::create([
                    'user_id' => auth()->id(),
                    'route_departure_id' => $departure->id,
                    'code' => $this->reservationCode(),
                    'contact_name' => $data['contact_name'],
                    'contact_email' => $data['contact_email'],
                    'contact_phone' => $data['contact_phone'],
                    'contact_document' => ($data['contact_document'] ?? null),
                    'total_amount' => $departure->fare * count($seatIds),
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes(10),
                ]);
                foreach ($seatIds as $index => $seatId) {
                    ReservationSeat::create([
                        'reservation_id' => $reservation->id,
                        'route_departure_id' => $departure->id,
                        'vessel_seat_id' => $seatId,
                        'passenger_name' => $data['passengers'][$index]['name'],
                        'document_type' => $data['passengers'][$index]['document_type'] ?? 'DNI',
                        'document_number' => $data['passengers'][$index]['document'],
                        'passenger_age' => $data['passengers'][$index]['age'] ?? null,
                    ]);
                }

                return $reservation;
            });
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['seats' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['seats' => 'Uno de los asientos acaba de ser reservado. Actualiza el plano y vuelve a elegir.']);
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
        if ($expiredIds->isNotEmpty()) {
            ReservationSeat::whereIn('reservation_id', $expiredIds)->delete();
            Reservation::whereIn('id', $expiredIds)->update(['status' => 'expired']);
        }
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
