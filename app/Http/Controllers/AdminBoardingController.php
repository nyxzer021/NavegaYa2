<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminBoardingController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = AdminScope::organizationId(auth()->user());
        $term = trim((string) $request->query('q'));
        $token = $term && str_contains($term, '/') ? basename(parse_url($term, PHP_URL_PATH) ?: '') : $term;
        $selectedDeparture = null;
        $selectedIsAir = false;

        if ($request->filled('departure')) {
            $selectedDeparture = RouteDeparture::with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel'])
                ->findOrFail($request->integer('departure'));
            abort_if($organizationId && $selectedDeparture->transportRoute->organization_id !== $organizationId, 403);
        } elseif ($request->filled('air_departure')) {
            $selectedDeparture = AirDeparture::with(['airRoute', 'aircraft'])->findOrFail($request->integer('air_departure'));
            $selectedIsAir = true;
            abort_if($organizationId && $selectedDeparture->airRoute->organization_id !== $organizationId, 403);
        }

        $tickets = collect();
        if ($selectedDeparture || $term !== '') {
            $query = Ticket::with([
                'reservationSeat.reservation.departure.transportRoute.originPort',
                'reservationSeat.reservation.departure.transportRoute.destinationPort',
                'reservationSeat.reservation.departure.vessel',
                'reservationSeat.reservation.airDeparture.airRoute',
                'reservationSeat.reservation.airDeparture.aircraft',
                'reservationSeat.seat', 'reservationSeat.aircraftSeat', 'boardedBy', 'boardedPort',
            ]);
            if ($organizationId) {
                $query->where(fn ($ticket) => $ticket
                    ->whereHas('reservationSeat.reservation.departure.transportRoute', fn ($route) => $route->where('organization_id', $organizationId))
                    ->orWhereHas('reservationSeat.reservation.airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organizationId)));
            }
            if ($selectedDeparture) {
                $query->whereHas('reservationSeat.reservation', fn ($reservation) => $reservation->where($selectedIsAir ? 'air_departure_id' : 'route_departure_id', $selectedDeparture->id));
            }
            if ($term !== '') {
                $query->where(fn ($ticket) => $ticket->where('code', $term)->orWhere('boarding_token', $token)
                    ->orWhereHas('reservationSeat', fn ($seat) => $seat->where('document_number', $term)
                        ->orWhereHas('reservation', fn ($reservation) => $reservation->where('code', $term))));
            }
            $tickets = $query->latest('issued_at')->get();
        }

        $current = $selectedDeparture
            ? $tickets->sortBy(fn ($ticket) => $selectedIsAir ? ($ticket->reservationSeat->aircraftSeat?->row_position ?? 0) * 100 + ($ticket->reservationSeat->aircraftSeat?->column_position ?? 0) : ($ticket->reservationSeat->seat?->row_position ?? 0) * 100 + ($ticket->reservationSeat->seat?->column_position ?? 0))->values()
            : $this->currentTickets($tickets);
        $previous = $selectedDeparture ? collect() : $tickets->diff($current)->sortByDesc(fn ($ticket) => $this->departureAt($ticket))->values();
        $boardingStats = $selectedDeparture ? ['total' => $current->count(), 'boarded' => $current->where('status', 'boarded')->count(), 'pending' => $current->where('status', '!=', 'boarded')->count()] : null;
        $ports = Port::where('is_active', true)->orderBy('city')->orderBy('name')->get();

        return view('admin.boarding.index', compact('current', 'previous', 'term', 'ports', 'selectedDeparture', 'selectedIsAir', 'boardingStats'));
    }

    public function board(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['port_id' => ['nullable', 'exists:ports,id']]);
        $ticket->load(['reservationSeat.reservation.departure.transportRoute', 'reservationSeat.reservation.airDeparture.airRoute']);
        $reservation = $ticket->reservationSeat->reservation;
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            $ownerId = $reservation->isAir() ? $reservation->airDeparture->airRoute->organization_id : $reservation->departure->transportRoute->organization_id;
            abort_unless($ownerId === $organizationId, 403);
        }
        if ($ticket->status === 'boarded') {
            $ticket->update(['status' => 'confirmed', 'checked_in_at' => null, 'boarded_at' => null, 'boarded_by_user_id' => null, 'boarded_port_id' => null]);

            return back()->with('success', "Abordaje anulado para {$ticket->code}.");
        }
        abort_unless(in_array($ticket->status, ['confirmed', 'issued'], true), 422);
        $ticket->update(['status' => 'boarded', 'checked_in_at' => $ticket->checked_in_at ?? now(), 'boarded_at' => now(), 'boarded_by_user_id' => auth()->id(), 'boarded_port_id' => $data['port_id'] ?? null]);

        return back()->with('success', "Embarque confirmado para {$ticket->code}.");
    }

    private function currentTickets(Collection $tickets): Collection
    {
        $threshold = now()->subDay();

        return $tickets->filter(fn ($ticket) => $this->departureAt($ticket)?->greaterThanOrEqualTo($threshold))->sortBy(fn ($ticket) => $this->departureAt($ticket))->values();
    }

    private function departureAt(Ticket $ticket): mixed
    {
        $reservation = $ticket->reservationSeat->reservation;

        return $reservation->airDeparture?->departure_at ?? $reservation->departure?->departure_at;
    }
}
