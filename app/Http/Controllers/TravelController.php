<?php

namespace App\Http\Controllers;

use App\Models\CompanyReview;
use App\Models\ConsumerClaim;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\TicketRefund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class TravelController extends Controller
{
    public function index(): View
    {
        $tickets = collect();
        $reservations = collect();
        $travelSummary = ['upcoming' => 0, 'completed' => 0, 'changed' => 0, 'cancelled' => 0];
        $accountMode = auth()->check() && auth()->user()->isCustomer();
        if ($accountMode) {
            auth()->user()->claimGuestPurchases();
            $tickets = $this->ticketQuery()
                ->whereHas('reservationSeat.reservation', fn ($query) => $query->where('user_id', auth()->id()))
                ->get()->sortByDesc(fn (Ticket $ticket) => $ticket->issued_at)->values();
            $reservations = Reservation::query()->where('user_id', auth()->id())
                ->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel', 'airDeparture.airRoute', 'airDeparture.aircraft', 'seats.ticket', 'seats.seat', 'seats.aircraftSeat'])
                ->latest()->get();
            $this->refundedIds = TicketRefund::query()->whereIn('reservation_id', $reservations->pluck('id'))
                ->whereIn('status', ['approved', 'paid'])->pluck('reservation_id')->map(fn ($id): int => (int) $id)->all();
            foreach ($reservations as $reservation) {
                $travelSummary[$this->tripState($reservation)]++;
            }
        }

        $nextTrip = $reservations
            ->filter(function (Reservation $reservation): bool {
                $trip = $reservation->isAir() ? $reservation->airDeparture : $reservation->departure;

                return $reservation->status === 'confirmed' && $trip?->departure_at?->isFuture() && $trip?->status !== 'cancelled'
                    && ! in_array((int) $reservation->id, $this->refundedIds, true);
            })
            ->sortBy(fn (Reservation $reservation) => ($reservation->isAir() ? $reservation->airDeparture : $reservation->departure)?->departure_at)
            ->first();
        $refunds = $accountMode
            ? TicketRefund::query()->whereIn('reservation_id', $reservations->pluck('id'))->latest()->get()->unique('reservation_id')->keyBy('reservation_id')
            : collect();
        $tripReviews = $accountMode
            ? CompanyReview::query()->whereIn('reservation_id', $reservations->pluck('id'))->get()->keyBy('reservation_id')
            : collect();
        $claims = $accountMode && auth()->user()->hasVerifiedEmail()
            ? ConsumerClaim::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(auth()->user()->email)])->latest()->limit(10)->get()
            : collect();

        // Próximos: viajes que aún no salen, del más cercano al más lejano. Historial: el resto, del más reciente al más antiguo.
        $upcomingList = $reservations
            ->filter(fn (Reservation $reservation): bool => in_array($this->tripState($reservation), ['upcoming', 'changed'], true) && ($this->tripOf($reservation)?->departure_at?->isFuture() ?? false))
            ->sortBy(fn (Reservation $reservation) => $this->tripOf($reservation)?->departure_at)
            ->values();
        $historyList = $reservations->reject(fn (Reservation $reservation): bool => $upcomingList->contains('id', $reservation->id))
            ->sortByDesc(fn (Reservation $reservation) => $this->tripOf($reservation)?->departure_at ?? $reservation->created_at)
            ->values();
        $tripView = in_array(request('vista'), ['proximos', 'historial'], true) ? request('vista') : ($upcomingList->isNotEmpty() ? 'proximos' : 'historial');
        $page = max(1, request()->integer('pagina', 1));
        $tripList = $tripView === 'proximos'
            ? $upcomingList
            : new LengthAwarePaginator($historyList->forPage($page, 10)->values(), $historyList->count(), 10, $page, ['path' => route('travels.index'), 'pageName' => 'pagina', 'query' => ['vista' => 'historial']]);
        $tripCounts = ['proximos' => $upcomingList->count(), 'historial' => $historyList->count()];

        return view('travels.lookup', compact('tickets', 'accountMode', 'travelSummary', 'nextTrip', 'refunds', 'claims', 'tripView', 'tripCounts', 'tripReviews') + ['reservations' => $tripList, 'searched' => false, 'document' => '', 'reference' => '']);
    }

    /**
     * Reservas con una devolución aprobada o pagada: cuentan como canceladas y van al historial.
     *
     * @var list<int>
     */
    private array $refundedIds = [];

    private function tripOf(Reservation $reservation): mixed
    {
        return $reservation->isAir() ? $reservation->airDeparture : $reservation->departure;
    }

    /**
     * Estado del viaje para los contadores y las pestañas: upcoming, changed, completed o cancelled.
     */
    private function tripState(Reservation $reservation): string
    {
        $trip = $this->tripOf($reservation);

        return match (true) {
            in_array((int) $reservation->id, $this->refundedIds, true) => 'cancelled',
            in_array($reservation->status, ['cancelled', 'expired'], true) || $trip?->status === 'cancelled' => 'cancelled',
            $trip?->status === 'rescheduled' || str_contains(mb_strtolower((string) $trip?->notes), 'reprogram') => 'changed',
            $trip?->departure_at?->isPast() || in_array($trip?->status, ['departed', 'in_transit', 'completed'], true) => 'completed',
            default => 'upcoming',
        };
    }

    public function search(Request $request): View
    {
        $data = $this->validatedLookup($request);
        $tickets = $this->ticketsFor($data['document'], $data['reference']);
        session(['travels_ticket_codes' => $tickets->pluck('code')->all()]);

        return view('travels.lookup', ['tickets' => $tickets, 'reservations' => collect(), 'travelSummary' => [], 'nextTrip' => null, 'refunds' => collect(), 'claims' => collect(), 'tripView' => 'proximos', 'tripCounts' => [], 'searched' => true, 'accountMode' => false, 'document' => $data['document'], 'reference' => $data['reference']]);
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
                'seat' => $isAir ? ($seat->aircraftSeat?->code ?? '—') : ($seat->seat?->code ?? '—'),
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
