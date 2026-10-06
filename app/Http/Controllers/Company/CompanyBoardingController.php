<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyBoardingController extends Controller
{
    public function show(Request $request, RouteDeparture $departure): View
    {
        $organizationId = $this->authorizeDeparture($request, $departure);
        $departure->load(['transportRoute.masterRoute', 'transportRoute.originPort', 'transportRoute.destinationPort', 'vessel']);

        $term = trim((string) $request->query('q'));
        $token = $term !== '' && str_contains($term, '/') ? basename(parse_url($term, PHP_URL_PATH) ?: '') : $term;
        $baseQuery = Ticket::query()
            ->whereHas('reservationSeat.reservation', fn ($query) => $query->where('route_departure_id', $departure->id));

        $boardingStats = [
            'total' => (clone $baseQuery)->count(),
            'boarded' => (clone $baseQuery)->where('status', 'boarded')->count(),
        ];
        $boardingStats['pending'] = $boardingStats['total'] - $boardingStats['boarded'];

        $tickets = $baseQuery
            ->with(['reservationSeat.seat', 'reservationSeat.reservation', 'boardedBy'])
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term, $token) {
                $query->where('code', $term)
                    ->orWhere('boarding_token', $token)
                    ->orWhereHas('reservationSeat', fn ($seat) => $seat
                        ->where('document_number', $term)
                        ->orWhere('passenger_name', 'like', "%{$term}%")
                        ->orWhereHas('reservation', fn ($reservation) => $reservation->where('code', $term)));
            }))
            ->get()
            ->sortBy(fn (Ticket $ticket) => (($ticket->reservationSeat->seat?->row_position ?? 0) * 100)
                + ($ticket->reservationSeat->seat?->column_position ?? 0))
            ->values();

        $organization = Organization::with('vessels.basePort')->findOrFail($organizationId);

        return view('company.boarding.show', compact('organization', 'departure', 'tickets', 'term', 'boardingStats'));
    }

    public function toggle(Request $request, RouteDeparture $departure, Ticket $ticket): RedirectResponse
    {
        $this->authorizeDeparture($request, $departure);
        $ticket->load('reservationSeat.reservation');
        abort_unless($ticket->reservationSeat?->reservation?->route_departure_id === $departure->id, 404);

        if ($ticket->status === 'boarded') {
            $ticket->update([
                'status' => 'confirmed',
                'checked_in_at' => null,
                'boarded_at' => null,
                'boarded_by_user_id' => null,
                'boarded_port_id' => null,
            ]);

            return back()->with('success', "Abordaje anulado para {$ticket->code}.");
        }

        abort_unless(in_array($ticket->status, ['confirmed', 'issued'], true), 422);
        $ticket->update([
            'status' => 'boarded',
            'checked_in_at' => $ticket->checked_in_at ?? now(),
            'boarded_at' => now(),
            'boarded_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', "Embarque confirmado para {$ticket->code}.");
    }

    private function authorizeDeparture(Request $request, RouteDeparture $departure): int
    {
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);
        abort_unless((int) $departure->transportRoute()->value('organization_id') === (int) $organizationId, 403);

        return $organizationId;
    }
}
