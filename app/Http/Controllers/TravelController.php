<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TravelController extends Controller
{
    public function index(): View
    {
        $tickets = collect();
        $accountMode = auth()->check() && auth()->user()->isCustomer();
        if ($accountMode) {
            auth()->user()->claimGuestPurchases();
            $tickets = $this->ticketQuery()
                ->whereHas('reservationSeat.reservation', fn ($query) => $query->where('user_id', auth()->id()))
                ->get()->sortByDesc(fn (Ticket $ticket) => $ticket->issued_at)->values();
        }

        return view('travels.lookup', compact('tickets', 'accountMode') + ['searched' => false, 'document' => '', 'reference' => '']);
    }

    public function search(Request $request): View
    {
        $data = $this->validatedLookup($request);
        $tickets = $this->ticketsFor($data['document'], $data['reference']);
        session(['travels_ticket_codes' => $tickets->pluck('code')->all()]);

        return view('travels.lookup', ['tickets' => $tickets, 'searched' => true, 'accountMode' => false, 'document' => $data['document'], 'reference' => $data['reference']]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $this->validatedLookup($request);
        $tickets = $this->ticketsFor($data['document'], $data['reference']);
        session(['travels_ticket_codes' => $tickets->pluck('code')->all()]);

        return response()->json(['tickets' => $tickets->map(function (Ticket $ticket) {
            $seat = $ticket->reservationSeat;
            $reservation = $seat->reservation;
            $isAir = $reservation->isAir();
            $trip = $isAir ? $reservation->airDeparture : $reservation->departure;
            $route = $isAir ? $trip->airRoute : $trip->transportRoute;
            $origin = $isAir ? $route->origin_city : $route->originPort->city;
            $destination = $isAir ? $route->destination_city : $route->destinationPort->city;

            return [
                'code' => $ticket->code,
                'passenger' => $ticket->reservationSeat->passenger_name,
                'document' => $ticket->reservationSeat->document_number,
                'reservation' => $ticket->reservationSeat->reservation->code,
                'route' => $origin.' → '.$destination,
                'departure' => $trip->departure_at->format('d/m/Y · H:i').' h',
                'vessel' => $isAir ? $trip->aircraft->name : $trip->vessel->name,
                'seat' => $isAir ? $seat->aircraftSeat->code : $seat->seat->code,
                'transport' => $isAir ? 'Aéreo' : 'Fluvial',
                'status' => $ticket->status,
                'qr_url' => route('tickets.qr', $ticket->code),
                'pdf_url' => route('tickets.pdf', $ticket->code),
                'whatsapp_url' => 'https://wa.me/?text='.urlencode('Mi boleto NavegaYA '.$ticket->code.' · Reserva '.$reservation->code.' · '.$origin.' → '.$destination),
            ];
        })->all()]);
    }

    private function validatedLookup(Request $request): array
    {
        $data = $request->validate([
            'document' => ['required', 'string', 'min:6', 'max:25'],
            'reference' => ['required', 'string', 'min:6', 'max:40'],
        ], ['document.required' => 'Ingresa el DNI o documento del viajero.', 'reference.required' => 'Ingresa el código de reserva o boleto.']);

        return ['document' => trim($data['document']), 'reference' => strtoupper(trim($data['reference']))];
    }

    private function ticketsFor(string $document, string $reference)
    {
        return $this->ticketQuery()
            ->whereIn('status', ['confirmed', 'boarded', 'finished', 'no_show'])
            ->whereHas('reservationSeat', fn ($seat) => $seat->where('document_number', $document)->where(fn ($row) => $row->whereHas('reservation', fn ($reservation) => $reservation->where('code', $reference))->orWhereHas('ticket', fn ($ticket) => $ticket->where('code', $reference))))
            ->get()
            ->sortByDesc(fn (Ticket $ticket) => ($ticket->reservationSeat->reservation->airDeparture?->departure_at ?? $ticket->reservationSeat->reservation->departure?->departure_at)?->isFuture())
            ->values();
    }

    private function ticketQuery()
    {
        return Ticket::with([
            'reservationSeat.reservation.departure.transportRoute.originPort',
            'reservationSeat.reservation.departure.transportRoute.destinationPort',
            'reservationSeat.reservation.departure.vessel',
            'reservationSeat.reservation.airDeparture.airRoute',
            'reservationSeat.reservation.airDeparture.aircraft',
            'reservationSeat.seat',
            'reservationSeat.aircraftSeat',
        ]);
    }
}
