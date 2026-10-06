<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    private function passengerRows(?int $organizationId, $from = null, $to = null): Builder
    {
        return ReservationSeat::with([
            'reservation.departure.transportRoute.originPort', 'reservation.departure.transportRoute.destinationPort',
            'reservation.departure.vessel', 'reservation.airDeparture.airRoute', 'reservation.airDeparture.aircraft',
            'seat', 'aircraftSeat', 'ticket',
        ])->whereHas('reservation', fn (Builder $query) => $query->whereIn('status', ['confirmed', 'pending_payment']))
            ->when($organizationId, fn (Builder $query) => $query->whereHas('reservation', fn (Builder $reservation) => $this->scopeReservation($reservation, $organizationId)))
            ->when($from, fn (Builder $query) => $query->whereHas('reservation', fn (Builder $reservation) => $reservation->where('created_at', '>=', $from)))
            ->when($to, fn (Builder $query) => $query->whereHas('reservation', fn (Builder $reservation) => $reservation->where('created_at', '<=', $to)))
            ->orderByDesc('id');
    }

    private function scopeReservation(Builder $query, int $organizationId): Builder
    {
        return $query->where(fn (Builder $mode) => $mode
            ->whereHas('departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organizationId))
            ->orWhereHas('airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organizationId)));
    }

    private function details(ReservationSeat $row): array
    {
        $reservation = $row->reservation;
        if ($reservation->isAir()) {
            return ['mode' => 'Aéreo', 'route' => $reservation->airDeparture->airRoute->origin_city.' → '.$reservation->airDeparture->airRoute->destination_city,
                'departure_at' => $reservation->airDeparture->departure_at, 'vehicle' => $reservation->airDeparture->aircraft->name,
                'seat' => $row->aircraftSeat?->code ?? 'Sin asiento'];
        }

        return ['mode' => 'Fluvial', 'route' => $reservation->departure->transportRoute->originPort->city.' → '.$reservation->departure->transportRoute->destinationPort->city,
            'departure_at' => $reservation->departure->departure_at, 'vehicle' => $reservation->departure->vessel->name,
            'seat' => $row->seat?->code ?? 'Sin asiento'];
    }

    public function export(Request $request)
    {
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();
        $organizationId = AdminScope::organizationId(auth()->user()) ?: $request->integer('organization_id');
        $rows = $this->passengerRows($organizationId, $from, $to)->get();

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Reserva', 'Boleto', 'Pasajero', 'DNI/documento', 'Modalidad', 'Ruta', 'Fecha y hora de salida', 'Unidad', 'Asiento', 'Estado de abordaje', 'Token QR', 'Enlace QR'], ';');
            foreach ($rows as $row) {
                $ticket = $row->ticket;
                $details = $this->details($row);
                fputcsv($file, [$row->reservation->code, $ticket?->code ?? 'Pendiente', $row->passenger_name,
                    $row->document_number ?: 'Sin documento', $details['mode'], $details['route'], $details['departure_at']->format('Y-m-d H:i'),
                    $details['vehicle'], $details['seat'], $ticket?->status ?? 'Pendiente', $ticket?->boarding_token ?? '',
                    $ticket ? route('tickets.qr', $ticket->code) : ''], ';');
            }
            fclose($file);
        }, 'reporte-reservas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request): View
    {
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();
        $organizationId = AdminScope::organizationId(auth()->user()) ?: $request->integer('organization_id');
        $dates = fn (Builder $query, string $column = 'created_at') => $query
            ->when($from, fn (Builder $q) => $q->where($column, '>=', $from))->when($to, fn (Builder $q) => $q->where($column, '<=', $to));

        $river = $dates(RouteDeparture::query(), 'departure_at')->when($organizationId, fn (Builder $q) => $q->whereHas('transportRoute', fn (Builder $r) => $r->where('organization_id', $organizationId)));
        $air = $dates(AirDeparture::query(), 'departure_at')->when($organizationId, fn (Builder $q) => $q->whereHas('airRoute', fn (Builder $r) => $r->where('organization_id', $organizationId)));
        $reservations = $dates(Reservation::query())->when($organizationId, fn (Builder $q) => $this->scopeReservation($q, $organizationId));
        $tickets = $dates(Ticket::query(), 'issued_at')->when($organizationId, fn (Builder $q) => $q->whereHas('reservationSeat.reservation', fn (Builder $r) => $this->scopeReservation($r, $organizationId)));
        $cargo = $dates(CargoShipment::query())->when($organizationId, fn (Builder $q) => $q->where(fn (Builder $m) => $m
            ->whereHas('departure.transportRoute', fn (Builder $r) => $r->where('organization_id', $organizationId))
            ->orWhereHas('airDeparture.airRoute', fn (Builder $r) => $r->where('organization_id', $organizationId))));

        $recentRiver = (clone $river)->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel'])->latest('departure_at')->limit(8)->get()->map(fn ($d) => [
            'mode' => 'Fluvial', 'route' => $d->transportRoute->originPort->city.' → '.$d->transportRoute->destinationPort->city,
            'code' => $d->code, 'vehicle' => $d->vessel->name, 'departure_at' => $d->departure_at]);
        $recentAir = (clone $air)->with(['airRoute', 'aircraft'])->latest('departure_at')->limit(8)->get()->map(fn ($d) => [
            'mode' => 'Aéreo', 'route' => $d->airRoute->origin_city.' → '.$d->airRoute->destination_city,
            'code' => 'VUE-'.str_pad((string) $d->id, 6, '0', STR_PAD_LEFT), 'vehicle' => $d->aircraft->name, 'departure_at' => $d->departure_at]);

        $rows = $this->passengerRows($organizationId, $from, $to)->paginate(30)->withQueryString();
        $rows->getCollection()->transform(function (ReservationSeat $row) {
            $row->setAttribute('report_details', $this->details($row));

            return $row;
        });

        return view('admin.reports.index', [
            'from' => $request->query('from'), 'to' => $request->query('to'), 'organizationId' => $organizationId,
            'organizations' => Organization::where('type', 'transport_company')->orderBy('legal_name')->get(),
            'metrics' => ['routes' => TransportRoute::when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->count() + AirRoute::when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->count(),
                'departures' => (clone $river)->count() + (clone $air)->count(), 'reservations' => (clone $reservations)->count(),
                'confirmed_reservations' => (clone $reservations)->where('status', 'confirmed')->count(), 'passenger_income' => (float) (clone $reservations)->where('status', 'confirmed')->sum('total_amount'),
                'tickets' => (clone $tickets)->count(), 'boarded' => (clone $tickets)->where('status', 'boarded')->count(), 'cargo' => (clone $cargo)->count(),
                'cargo_income' => (float) (clone $cargo)->where('payment_status', 'paid_terminal')->sum('amount')],
            'recentDepartures' => $recentRiver->concat($recentAir)->sortByDesc('departure_at')->take(8)->values(), 'reservationRows' => $rows,
        ]);
    }
}
