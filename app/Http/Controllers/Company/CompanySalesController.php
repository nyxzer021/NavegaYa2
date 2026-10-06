<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Support\AdminScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanySalesController extends Controller
{
    public function index(Request $request): View
    {
        [$organization, $from, $to] = $this->context($request);
        $payments = $this->payments($organization->id, $from, $to);
        $totalRevenue = (float) (clone $payments)->sum('amount');
        $webSales = (float) (clone $payments)->whereHas('reservation', fn (Builder $query) => $query->where('sales_channel', 'web'))->sum('amount');
        $counterSales = (float) (clone $payments)->whereHas('reservation', fn (Builder $query) => $query->where('sales_channel', 'counter'))->sum('amount');
        $totalTickets = $this->tickets($organization->id, $from, $to)->count();
        $dailySales = (clone $payments)->selectRaw('DATE(paid_at) as sale_date, SUM(amount) as total')
            ->groupBy(DB::raw('DATE(paid_at)'))->orderBy('sale_date')->pluck('total', 'sale_date');
        $tickets = $this->tickets($organization->id, $from, $to)
            ->with($this->ticketRelations())->latest('tickets.created_at')->paginate(15)->withQueryString();

        return view('company.sales.index', compact('organization', 'from', 'to', 'totalRevenue', 'totalTickets', 'webSales', 'counterSales', 'dailySales', 'tickets'));
    }

    public function export(Request $request): StreamedResponse
    {
        [$organization, $from, $to] = $this->context($request);
        $tickets = $this->tickets($organization->id, $from, $to)->with($this->ticketRelations())->latest('tickets.created_at')->get();

        return response()->streamDownload(function () use ($tickets): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Boleto', 'Fecha', 'Pasajero', 'Documento', 'Tramo', 'Salida', 'Asiento', 'Canal', 'Monto PEN', 'Estado']);
            foreach ($tickets as $ticket) {
                fputcsv($file, $this->ticketRow($ticket));
            }
            fclose($file);
        }, "ventas-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function context(Request $request): array
    {
        $organizationId = AdminScope::organizationId($request->user());
        abort_if(! $organizationId, 403, 'Sin empresa asignada.');
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->endOfMonth()->toDateString())->endOfDay();

        return [Organization::with('vessels.basePort')->findOrFail($organizationId), $from, $to];
    }

    private function payments(int $organizationId, Carbon $from, Carbon $to): Builder
    {
        return Payment::query()->where('status', 'confirmed')->whereBetween('paid_at', [$from, $to])
            ->whereHas('reservation', fn (Builder $query) => $this->scopeReservations($query, $organizationId));
    }

    private function tickets(int $organizationId, Carbon $from, Carbon $to): Builder
    {
        return Ticket::query()->whereBetween('tickets.created_at', [$from, $to])
            ->whereHas('reservationSeat.reservation', fn (Builder $query) => $this->scopeReservations($query, $organizationId));
    }

    private function scopeReservations(Builder $query, int $organizationId): Builder
    {
        return $query->where(function (Builder $query) use ($organizationId): void {
            $query->whereHas('departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organizationId))
                ->orWhereHas('airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organizationId));
        });
    }

    private function ticketRelations(): array
    {
        return [
            'reservationSeat.seat', 'reservationSeat.aircraftSeat', 'reservationSeat.reservation.payments',
            'reservationSeat.reservation.departure.transportRoute.originPort',
            'reservationSeat.reservation.departure.transportRoute.destinationPort',
            'reservationSeat.reservation.airDeparture.airRoute',
        ];
    }

    public static function present(Ticket $ticket): array
    {
        $seat = $ticket->reservationSeat;
        $reservation = $seat->reservation;
        $departure = $reservation->isAir() ? $reservation->airDeparture : $reservation->departure;
        $route = $reservation->isAir() ? $departure?->airRoute : $departure?->transportRoute;
        $origin = $reservation->isAir() ? ($route?->origin_city ?? '—') : ($route?->originPort?->city ?? '—');
        $destination = $reservation->isAir() ? ($route?->destination_city ?? '—') : ($route?->destinationPort?->city ?? '—');
        $amount = (float) $reservation->total_amount / max(1, $reservation->seats()->count());

        return compact('seat', 'reservation', 'departure', 'origin', 'destination', 'amount');
    }

    private function ticketRow(Ticket $ticket): array
    {
        extract(self::present($ticket));

        return [$ticket->code, $ticket->created_at->format('d/m/Y H:i'), $seat->passenger_name, $seat->document_number, "{$origin} - {$destination}", $departure?->departure_at?->format('d/m/Y H:i'), $seat->seat?->code ?? $seat->aircraftSeat?->code ?? '—', $reservation->sales_channel, number_format($amount, 2, '.', ''), $ticket->status];
    }
}
