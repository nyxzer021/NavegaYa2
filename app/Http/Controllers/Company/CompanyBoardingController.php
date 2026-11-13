<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Support\AdminScope;
use App\Support\CrewRoster;
use App\Support\ManifestReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CompanyBoardingController extends Controller
{
    public function show(Request $request, RouteDeparture $departure): View
    {
        $organizationId = $this->authorizeDeparture($request, $departure);
        $departure->load(['transportRoute.masterRoute', 'transportRoute.originPort', 'transportRoute.destinationPort', 'vessel']);

        $term = trim((string) $request->query('q'));
        $token = $term !== '' && str_contains($term, '/') ? basename(parse_url($term, PHP_URL_PATH) ?: '') : $term;
        $passengers = ReservationSeat::query()
            ->where('route_departure_id', $departure->id)
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['expired', 'cancelled']))
            ->with(['seat', 'reservation', 'ticket'])
            ->get()
            ->when($term !== '', fn ($seats) => $seats->filter(function (ReservationSeat $seat) use ($term, $token): bool {
                $ticket = $seat->ticket;

                return $seat->document_number === $term
                    || str_contains(mb_strtolower($seat->passenger_name), mb_strtolower($term))
                    || $ticket?->code === $term
                    || $ticket?->boarding_token === $token
                    || $seat->reservation?->code === $term;
            }))
            ->sortBy(fn (ReservationSeat $seat) => (($seat->seat?->row_position ?? 0) * 100) + ($seat->seat?->column_position ?? 0))
            ->values();

        $boardingStats = [
            'total' => $passengers->count(),
            'boarded' => $passengers->filter(fn (ReservationSeat $seat) => $seat->ticket?->status === 'boarded')->count(),
            'absent' => $passengers->filter(fn (ReservationSeat $seat) => $seat->ticket?->status === 'no_show')->count(),
        ];
        $boardingStats['pending'] = $boardingStats['total'] - $boardingStats['boarded'] - $boardingStats['absent'];

        $organization = Organization::with('vessels.basePort')->findOrFail($organizationId);
        $canBoard = CrewRoster::commandReady($departure);

        return view('company.boarding.show', compact('organization', 'departure', 'passengers', 'term', 'boardingStats', 'canBoard'));
    }

    public function toggle(Request $request, RouteDeparture $departure, Ticket $ticket): RedirectResponse
    {
        $this->authorizeDeparture($request, $departure);
        abort_unless(CrewRoster::commandReady($departure), 422, CrewRoster::BOARDING_BLOCK);
        abort_unless(in_array($departure->status, ['scheduled', 'boarding', 'delayed', 'rescheduled'], true), 422, 'El estado de la salida no permite embarcar pasajeros.');
        $ticket->load('reservationSeat.reservation');
        abort_unless($ticket->reservationSeat?->reservation?->route_departure_id === $departure->id, 404);

        if ($request->input('action') === 'no_show') {
            $ticket->update([
                'status' => 'no_show',
                'checked_in_at' => null,
                'boarded_at' => null,
                'boarded_by_user_id' => null,
                'boarded_port_id' => null,
            ]);

            return back()->with('success', "{$ticket->code} quedó como no se presentó.");
        }

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

        abort_unless(in_array($ticket->status, ['confirmed', 'issued', 'no_show'], true), 422);
        $ticket->update([
            'status' => 'boarded',
            'checked_in_at' => $ticket->checked_in_at ?? now(),
            'boarded_at' => now(),
            'boarded_by_user_id' => $request->user()->id,
            'boarded_port_id' => $departure->transportRoute()->value('origin_port_id'),
        ]);

        return back()->with('success', "Embarque confirmado para {$ticket->code}.");
    }

    public function seat(Request $request, RouteDeparture $departure, ReservationSeat $reservationSeat): RedirectResponse
    {
        $this->authorizeDeparture($request, $departure);
        abort_unless(CrewRoster::commandReady($departure), 422, CrewRoster::BOARDING_BLOCK);
        abort_unless((int) $reservationSeat->route_departure_id === (int) $departure->id, 404);
        $action = $request->validate(['action' => ['required', 'in:board,no_show,undo']])['action'];
        $ticket = $reservationSeat->ticket ?? Ticket::create([
            'reservation_seat_id' => $reservationSeat->id,
            'code' => 'BOL-'.$departure->id.'-'.$reservationSeat->id,
            'boarding_token' => Str::upper(Str::random(16)),
            'status' => 'confirmed',
            'issued_at' => now(),
        ]);
        $request->merge(['action' => $action === 'board' ? 'board' : $action]);
        if ($action === 'undo') {
            $ticket->update(['status' => 'confirmed', 'checked_in_at' => null, 'boarded_at' => null, 'boarded_by_user_id' => null, 'boarded_port_id' => null]);

            return back()->with('success', "Se reabrió {$ticket->code}.");
        }

        return $this->toggle($request, $departure, $ticket);
    }

    public function passengerPdf(RouteDeparture $departure): Response
    {
        $this->authorizeDeparture(request(), $departure);

        return app('dompdf.wrapper')->loadView('company.manifests.passengers-pdf', ManifestReport::river($departure))->setPaper('a4')->download('manifiesto-pasajeros-'.$departure->code.'.pdf');
    }

    public function cargoPdf(RouteDeparture $departure): Response
    {
        $this->authorizeDeparture(request(), $departure);

        return app('dompdf.wrapper')->loadView('company.manifests.cargo-pdf', [
            'departure' => $departure,
            'shipments' => ManifestReport::cargo($departure),
        ])->setPaper('a4')->download('manifiesto-carga-'.$departure->code.'.pdf');
    }

    private function authorizeDeparture(Request $request, RouteDeparture $departure): int
    {
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);
        abort_unless(Organization::query()->whereKey($organizationId)->where('status', 'active')->exists(), 403);
        abort_unless((int) $departure->transportRoute()->value('organization_id') === (int) $organizationId, 403);

        return $organizationId;
    }
}
