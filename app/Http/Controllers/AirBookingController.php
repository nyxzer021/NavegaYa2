<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAirBookingRequest;
use App\Models\AirDeparture;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AirBookingController extends Controller
{
    public function create(AirDeparture $departure): View
    {
        $this->expire($departure);
        $departure->load(['airRoute.organization', 'aircraft.seats']);
        abort_unless(! $departure->airRoute->organization->is_marketplace_paused && $departure->is_published && $departure->status === 'scheduled' && $departure->departure_at->isFuture(), 404);
        $occupiedSeatIds = ReservationSeat::where('air_departure_id', $departure->id)->pluck('aircraft_seat_id')->all();
        $seats = $departure->aircraft->seats->where('is_available', true)->sortBy(['cabin', 'row_position', 'column_position']);

        return view('bookings.air-create', compact('departure', 'seats', 'occupiedSeatIds'));
    }

    public function store(StoreAirBookingRequest $request, AirDeparture $departure): RedirectResponse
    {
        $data = $request->validated();
        $departure->loadMissing('airRoute.organization');
        abort_unless(! $departure->airRoute->organization->is_marketplace_paused && $departure->is_published && $departure->status === 'scheduled' && $departure->departure_at->isFuture(), 404);
        if (count($data['seats']) !== count($data['passengers'])) {
            return back()->withInput()->withErrors(['seats' => 'Cada asiento debe tener un pasajero.']);
        }
        try {
            $reservation = DB::transaction(function () use ($data, $departure) {
                $this->expire($departure);
                $ids = collect($data['seats'])->map(fn ($id) => (int) $id);
                $valid = $departure->aircraft->seats()->where('is_available', true)->whereIn('id', $ids)->lockForUpdate()->pluck('id');
                if ($valid->count() !== $ids->count() || ReservationSeat::where('air_departure_id', $departure->id)->whereIn('aircraft_seat_id', $ids)->exists()) {
                    throw new \DomainException('Uno de los asientos ya no está disponible.');
                }
                $reservation = Reservation::create(['user_id' => auth()->id(), 'air_departure_id' => $departure->id, 'code' => 'NY-'.now()->format('ymd').'-'.Str::upper(Str::random(6)), 'contact_name' => $data['contact_name'], 'contact_email' => $data['contact_email'], 'contact_phone' => $data['contact_phone'], 'contact_document' => $data['contact_document'] ?? null, 'total_amount' => $departure->fare * $ids->count(), 'status' => 'pending_payment', 'expires_at' => now()->addMinutes(10)]);
                foreach ($ids->values() as $i => $seatId) {
                    ReservationSeat::create(['reservation_id' => $reservation->id, 'air_departure_id' => $departure->id, 'aircraft_seat_id' => $seatId, 'passenger_name' => $data['passengers'][$i]['name'], 'document_number' => $data['passengers'][$i]['document']]);
                }

                return $reservation;
            });
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['seats' => $e->getMessage()]);
        }
        $request->session()->push('cart_reservation_ids', $reservation->id);

        return redirect()->route('cart.index');
    }

    private function expire(AirDeparture $departure): void
    {
        $ids = Reservation::where('air_departure_id', $departure->id)->where('status', 'pending_payment')->where('expires_at', '<=', now())->pluck('id');
        if ($ids->isNotEmpty()) {
            ReservationSeat::whereIn('reservation_id', $ids)->delete();
            Reservation::whereIn('id', $ids)->update(['status' => 'expired']);
        }
    }
}
