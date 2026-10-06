<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Ticket;
use App\Services\BookingPaymentService;
use App\Support\BoardingQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function show(string $code): View
    {
        $reservation = $this->reservationOrFail($code);
        $sandboxEnabled = config('payments.sandbox_enabled');
        $culqiEnabled = config('services.culqi.enabled');

        return view('payments.show', compact('reservation', 'sandboxEnabled', 'culqiEnabled'));
    }

    public function sandboxConfirm(Request $request, string $code, BookingPaymentService $payments): RedirectResponse
    {
        abort_unless(config('payments.sandbox_enabled'), 404);

        $data = $request->validate(['method' => ['required', 'in:yape,plin,card']]);
        $reservation = $this->reservationOrFail($code);
        if ($reservation->status === 'confirmed') {
            return redirect()->route('tickets.show', $reservation->code);
        }
        if ($reservation->status !== 'pending_payment') {
            return redirect()->route('bookings.confirmation', $reservation->code)->with('payment_error', 'Esta reserva ya no puede procesar un pago.');
        }

        $payments->confirm($reservation, [
            'method' => $data['method'],
            'provider' => 'sandbox',
            'provider_reference' => 'DEMO-'.Str::upper(Str::random(12)),
            'provider_payload' => ['mode' => 'sandbox'],
        ]);

        return redirect()->route('tickets.show', $reservation->code);
    }

    public function tickets(string $code): View
    {
        $reservation = $this->reservationOrFail($code);
        abort_unless($reservation->status === 'confirmed', 404);
        $reservation->load(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel', 'airDeparture.airRoute', 'airDeparture.aircraft', 'seats.seat', 'seats.aircraftSeat', 'seats.ticket']);
        $isSandbox = $reservation->payments()->where('provider', 'sandbox')->exists();

        return view('tickets.show', compact('reservation', 'isSandbox'));
    }

    public function qr(string $ticketCode): Response
    {
        $ticket = Ticket::where('code', $ticketCode)->with('reservationSeat.reservation')->firstOrFail();
        $this->ensureTicketAccess($ticket);

        return response(BoardingQr::svg($ticket), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=300']);
    }

    public function pdf(string $ticketCode)
    {
        $ticket = Ticket::where('code', $ticketCode)->with(['reservationSeat.reservation.departure.transportRoute.originPort', 'reservationSeat.reservation.departure.transportRoute.destinationPort', 'reservationSeat.reservation.departure.vessel.organization', 'reservationSeat.reservation.airDeparture.airRoute', 'reservationSeat.reservation.airDeparture.aircraft.organization', 'reservationSeat.seat', 'reservationSeat.aircraftSeat'])->firstOrFail();
        $this->ensureTicketAccess($ticket);
        $qrDataUri = BoardingQr::dataUri($ticket);

        return app('dompdf.wrapper')->loadView('tickets.pdf', compact('ticket', 'qrDataUri'))->setPaper('a4')->download('boleto-'.$ticket->code.'.pdf');
    }

    private function ensureTicketAccess(Ticket $ticket): void
    {
        $reservation = $ticket->reservationSeat->reservation;
        if ($reservation->user_id) {
            abort_unless(auth()->id() === $reservation->user_id || in_array($ticket->code, session('travels_ticket_codes', []), true), 403);
        }
    }

    public function verify(string $token): View
    {
        $ticket = Ticket::where('boarding_token', $token)->with(['reservationSeat.reservation.departure.transportRoute.originPort', 'reservationSeat.reservation.departure.transportRoute.destinationPort', 'reservationSeat.reservation.departure.vessel', 'reservationSeat.reservation.airDeparture.airRoute', 'reservationSeat.reservation.airDeparture.aircraft', 'reservationSeat.seat', 'reservationSeat.aircraftSeat'])->firstOrFail();

        return view('tickets.verify', compact('ticket'));
    }

    private function reservationOrFail(string $code): Reservation
    {
        $reservation = Reservation::where('code', $code)->with('seats')->firstOrFail();
        $this->ensureReservationAccess($reservation);
        if ($reservation->status === 'pending_payment' && $reservation->expires_at?->isPast()) {
            DB::transaction(function () use ($reservation) {
                $reservation->seats()->delete();
                $reservation->update(['status' => 'expired']);
            });
            $reservation->refresh()->load('seats');
        }

        return $reservation;
    }

    private function ensureReservationAccess(Reservation $reservation): void
    {
        if ($reservation->user_id) {
            abort_unless(auth()->id() === $reservation->user_id, 403);
        }
    }
}
