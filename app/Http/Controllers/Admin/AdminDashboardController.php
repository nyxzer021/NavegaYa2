<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\Vessel;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        [$period, $from, $to] = $this->period($request);
        $today = today();

        $payments = Payment::query()
            ->whereIn('status', ['confirmed', 'succeeded'])
            ->whereBetween('paid_at', [$from, $to])
            ->with([
                'reservation.seats',
                'reservation.departure.transportRoute.originPort',
                'reservation.departure.transportRoute.destinationPort',
                'reservation.departure.transportRoute.organization',
                'reservation.airDeparture.airRoute.organization',
            ])
            ->get();

        $successfulPayments = fn () => Payment::query()
            ->whereIn('status', ['confirmed', 'succeeded']);

        $profitToday = (float) $successfulPayments()
            ->whereDate('paid_at', $today)
            ->sum('commission_amount');
        $profitMonth = (float) $successfulPayments()
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('commission_amount');
        $profitYear = (float) $successfulPayments()
            ->whereBetween('paid_at', [now()->startOfYear(), now()->endOfYear()])
            ->sum('commission_amount');

        $paymentAmounts = [
            'yape' => (float) $payments->whereIn('method', ['yape', 'plin'])->sum('amount'),
            'card' => (float) $payments->where('method', 'card')->sum('amount'),
        ];
        $paymentAmounts['cash'] = max(0, (float) $payments->sum('amount') - $paymentAmounts['yape'] - $paymentAmounts['card']);
        $paymentMethodsTotal = array_sum($paymentAmounts);
        $paymentMethodStats = [
            'yape_percent' => $paymentMethodsTotal > 0 ? round(($paymentAmounts['yape'] / $paymentMethodsTotal) * 100, 1) : 0,
            'card_percent' => $paymentMethodsTotal > 0 ? round(($paymentAmounts['card'] / $paymentMethodsTotal) * 100, 1) : 0,
            'cash_percent' => $paymentMethodsTotal > 0 ? round(($paymentAmounts['cash'] / $paymentMethodsTotal) * 100, 1) : 0,
        ];

        $cargoShipments = CargoShipment::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $grossSales = (float) $payments->sum('amount');
        $netCommission = (float) $payments->sum('commission_amount');
        $cargoRevenue = (float) $cargoShipments->sum('amount');
        $totalTicketsSold = $payments->sum(
            fn (Payment $payment) => $payment->reservation?->seats->count() ?? 0
        );
        $fluvialTicketsSold = $payments
            ->filter(fn (Payment $payment) => $payment->reservation?->route_departure_id !== null)
            ->sum(fn (Payment $payment) => $payment->reservation?->seats->count() ?? 0);
        $airTicketsSold = $payments
            ->filter(fn (Payment $payment) => $payment->reservation?->air_departure_id !== null)
            ->sum(fn (Payment $payment) => $payment->reservation?->seats->count() ?? 0);
        $cancelledTickets = Ticket::query()
            ->whereBetween('issued_at', [$from, $to])
            ->whereIn('status', ['cancelled', 'void'])
            ->count();

        $commissionCollected = (float) $payments
            ->where('commission_status', 'paid')
            ->sum('commission_amount');
        $commissionReceivable = (float) $payments
            ->whereIn('commission_status', ['pending', 'invoiced'])
            ->sum('commission_amount');
        $activeOperatorsCount = Organization::query()
            ->where('type', 'transport_company')
            ->where('status', 'active')
            ->count();
        $totalOperatorsCount = Organization::query()->where('type', 'transport_company')->count();
        $pendingOperatorsCount = Organization::query()->where('type', 'transport_company')->where('status', 'pending')->count();
        $rejectedOperatorsCount = Organization::query()->where('type', 'transport_company')->where('status', 'rejected')->count();
        $newOperatorsInPeriod = Organization::query()
            ->where('type', 'transport_company')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $yearAffiliations = Organization::query()
            ->where('type', 'transport_company')
            ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])
            ->get(['created_at'])
            ->groupBy(fn (Organization $organization) => $organization->created_at->month)
            ->map->count();
        $affiliationLabels = collect(range(1, 12))
            ->map(fn (int $month) => Carbon::create(null, $month)->locale('es')->translatedFormat('M'))
            ->all();
        $affiliationSeries = collect(range(1, 12))
            ->map(fn (int $month) => (int) ($yearAffiliations[$month] ?? 0))
            ->all();

        $fleetByOperator = Organization::query()
            ->where('type', 'transport_company')
            ->withCount(['vessels', 'aircraft'])
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->commercial_name ?: $organization->legal_name,
                'modality' => $organization->modality ?: 'fluvial',
                'vessels' => $organization->vessels_count,
                'aircraft' => $organization->aircraft_count,
                'total' => $organization->vessels_count + $organization->aircraft_count,
            ])
            ->sortByDesc('total')
            ->values();
        $registeredVesselsCount = (int) $fleetByOperator->sum('vessels');
        $registeredAircraftCount = (int) $fleetByOperator->sum('aircraft');

        $todayFluvialDepartures = RouteDeparture::query()
            ->whereDate('departure_at', $today)
            ->with('vessel')
            ->get();

        $todayAirDepartures = AirDeparture::query()
            ->whereDate('departure_at', $today)
            ->with('aircraft')
            ->get();

        $todaysDeparturesCount = $todayFluvialDepartures->count() + $todayAirDepartures->count();
        $todaysTicketsCount = Ticket::query()
            ->whereDate('issued_at', $today)
            ->whereNotIn('status', ['cancelled', 'void'])
            ->count();

        $todayCapacity = $todayFluvialDepartures->sum(fn (RouteDeparture $departure) => $departure->vessel?->seat_capacity ?? 0)
            + $todayAirDepartures->sum(fn (AirDeparture $departure) => $departure->aircraft?->seat_capacity ?? 0);

        $todayOccupancyRate = $todayCapacity > 0
            ? min(100, round(($todaysTicketsCount / $todayCapacity) * 100, 1))
            : 0;

        $riverUnitsInTransit = $todayFluvialDepartures->where('status', 'in_transit')->count();
        $airUnitsInFlight = $todayAirDepartures->where('status', 'in_transit')->count();

        $cancelledDeparturesToday = RouteDeparture::query()->whereDate('departure_at', $today)->where('status', 'cancelled')->count()
            + AirDeparture::query()->whereDate('departure_at', $today)->where('status', 'cancelled')->count();
        $cancelledDeparturesInPeriod = RouteDeparture::query()->whereBetween('departure_at', [$from, $to])->where('status', 'cancelled')->count()
            + AirDeparture::query()->whereBetween('departure_at', [$from, $to])->where('status', 'cancelled')->count();
        $rescheduledDeparturesInPeriod = RouteDeparture::query()
            ->whereBetween('departure_at', [$from, $to])
            ->where(fn ($query) => $query->where('status', 'rescheduled')->orWhereRaw("lower(coalesce(notes, '')) like ?", ['%reprogram%']))
            ->count()
            + AirDeparture::query()
                ->whereBetween('departure_at', [$from, $to])
                ->where(fn ($query) => $query->where('status', 'rescheduled')->orWhereRaw("lower(coalesce(notes, '')) like ?", ['%reprogram%']))
                ->count();

        $technicalAlerts = Vessel::query()
            ->where(function ($query) {
                $query->whereNotIn('status', ['ready', 'active'])
                    ->orWhereNull('inspection_expires_at')
                    ->orWhereDate('inspection_expires_at', '<', today());
            })
            ->count()
            + Aircraft::query()
                ->where(function ($query) {
                    $query->whereNotIn('status', ['ready', 'active'])
                        ->orWhereNull('airworthiness_expires_at')
                        ->orWhereDate('airworthiness_expires_at', '<', today());
                })
                ->count();

        $culqiOperational = (bool) config('services.culqi.enabled')
            && filled(config('services.culqi.public_key'))
            && filled(config('services.culqi.secret_key'));

        $operatorRanking = $payments
            ->groupBy(fn (Payment $payment) => $this->organization($payment)?->id ?? 0)
            ->filter(fn (Collection $group, int|string $organizationId) => (int) $organizationId > 0)
            ->map(function (Collection $group): array {
                $organization = $this->organization($group->first());
                $airTickets = $group->filter(fn (Payment $payment) => $payment->reservation?->air_departure_id !== null)->count();
                $fluvialTickets = $group->filter(fn (Payment $payment) => $payment->reservation?->route_departure_id !== null)->count();

                return [
                    'id' => $organization?->id,
                    'name' => $organization?->commercial_name ?: $organization?->legal_name ?: 'Operador',
                    'modality' => $airTickets > 0 && $fluvialTickets > 0
                        ? 'Mixto'
                        : ($airTickets > 0 ? 'Aéreo' : 'Fluvial'),
                    'tickets' => $group->sum(fn (Payment $payment) => $payment->reservation?->seats->count() ?? 0),
                    'sales' => (float) $group->sum('amount'),
                    'commission' => (float) $group->sum('commission_amount'),
                ];
            })
            ->sortByDesc('sales')
            ->take(5)
            ->values();

        $topRoutes = $payments
            ->groupBy(function (Payment $payment): string {
                $reservation = $payment->reservation;

                return $reservation?->air_departure_id
                    ? 'air:'.$reservation->airDeparture?->air_route_id
                    : 'river:'.$reservation?->departure?->transport_route_id;
            })
            ->filter(fn (Collection $group, string $key) => ! str_ends_with($key, ':'))
            ->map(function (Collection $group): array {
                $payment = $group->first();
                $reservation = $payment?->reservation;

                if ($reservation?->air_departure_id) {
                    $route = $reservation->airDeparture?->airRoute;

                    return [
                        'name' => ($route?->origin_city ?: 'Origen').' → '.($route?->destination_city ?: 'Destino'),
                        'type' => 'Aéreo',
                        'tickets' => $group->sum(fn (Payment $item) => $item->reservation?->seats->count() ?? 0),
                        'sales' => (float) $group->sum('amount'),
                    ];
                }

                $route = $reservation?->departure?->transportRoute;

                return [
                    'name' => ($route?->originPort?->city ?: 'Origen').' → '.($route?->destinationPort?->city ?: 'Destino'),
                    'type' => 'Fluvial',
                    'tickets' => $group->sum(fn (Payment $item) => $item->reservation?->seats->count() ?? 0),
                    'sales' => (float) $group->sum('amount'),
                ];
            })
            ->sortByDesc('tickets')
            ->take(5)
            ->values();

        [$chartLabels, $fluvialSeries, $airSeries, $cargoSeries] = $this->chartSeries(
            $payments,
            $cargoShipments,
            $period,
            $from,
            $to
        );

        $fluvialGmv = (float) $payments
            ->filter(fn (Payment $payment) => $payment->reservation?->route_departure_id !== null)
            ->sum('amount');
        $airGmv = (float) $payments
            ->filter(fn (Payment $payment) => $payment->reservation?->air_departure_id !== null)
            ->sum('amount');

        $modalityValues = [round($fluvialGmv, 2), round($airGmv, 2), round($cargoRevenue, 2)];
        $modalityTotal = array_sum($modalityValues);
        $modalityPercentages = array_map(
            fn (float $value) => $modalityTotal > 0 ? round(($value / $modalityTotal) * 100) : 0,
            $modalityValues
        );
        $cargoShipmentsCount = $cargoShipments->count();
        $cargoCustomersCount = $cargoShipments->pluck('sender_phone')->filter()->unique()->count();
        $cargoPackagesCount = (int) $cargoShipments->sum('package_count');

        return view('admin.dashboard', compact(
            'period',
            'from',
            'to',
            'grossSales',
            'netCommission',
            'commissionCollected',
            'commissionReceivable',
            'activeOperatorsCount',
            'totalOperatorsCount',
            'pendingOperatorsCount',
            'rejectedOperatorsCount',
            'newOperatorsInPeriod',
            'affiliationLabels',
            'affiliationSeries',
            'fleetByOperator',
            'registeredVesselsCount',
            'registeredAircraftCount',
            'totalTicketsSold',
            'fluvialTicketsSold',
            'airTicketsSold',
            'cancelledTickets',
            'cargoRevenue',
            'cargoShipmentsCount',
            'cargoCustomersCount',
            'cargoPackagesCount',
            'todaysDeparturesCount',
            'todaysTicketsCount',
            'todayOccupancyRate',
            'riverUnitsInTransit',
            'airUnitsInFlight',
            'technicalAlerts',
            'cancelledDeparturesToday',
            'cancelledDeparturesInPeriod',
            'rescheduledDeparturesInPeriod',
            'culqiOperational',
            'operatorRanking',
            'topRoutes',
            'chartLabels',
            'fluvialSeries',
            'airSeries',
            'cargoSeries',
            'modalityValues',
            'modalityPercentages',
            'profitToday',
            'profitMonth',
            'profitYear',
            'paymentMethodStats',
        ));
    }

    private function period(Request $request): array
    {
        $period = $request->string('period', 'today')->toString();

        if (! in_array($period, ['today', 'week', 'month', 'custom'], true)) {
            $period = 'today';
        }

        if ($period === 'custom') {
            $validated = $request->validate([
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
            ]);

            return [
                'custom',
                Carbon::parse($validated['from'])->startOfDay(),
                Carbon::parse($validated['to'])->endOfDay(),
            ];
        }

        return match ($period) {
            'week' => ['week', now()->startOfWeek()->startOfDay(), now()->endOfDay()],
            'month' => ['month', now()->startOfMonth()->startOfDay(), now()->endOfDay()],
            default => ['today', now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function organization(Payment $payment): ?Organization
    {
        return $payment->reservation?->departure?->transportRoute?->organization
            ?? $payment->reservation?->airDeparture?->airRoute?->organization;
    }

    private function chartSeries(
        Collection $payments,
        Collection $cargoShipments,
        string $period,
        Carbon $from,
        Carbon $to
    ): array {
        $labels = $fluvial = $air = $cargo = [];

        if ($period === 'today') {
            foreach ([0, 4, 8, 12, 16, 20] as $hour) {
                $blockStart = $from->copy()->setTime($hour, 0);
                $blockEnd = $blockStart->copy()->addHours(4);

                $paymentBlock = $payments->filter(function (Payment $payment) use ($blockStart, $blockEnd): bool {
                    $paidAt = $payment->paid_at instanceof Carbon ? $payment->paid_at : Carbon::parse($payment->paid_at);

                    return $paidAt->greaterThanOrEqualTo($blockStart) && $paidAt->lessThan($blockEnd);
                });

                $cargoBlock = $cargoShipments->filter(
                    fn (CargoShipment $shipment) => $shipment->created_at->greaterThanOrEqualTo($blockStart)
                        && $shipment->created_at->lessThan($blockEnd)
                );

                $labels[] = sprintf('%02d:00', $hour);
                $fluvial[] = round((float) $paymentBlock->filter(fn (Payment $payment) => $payment->reservation?->route_departure_id)->sum('amount'), 2);
                $air[] = round((float) $paymentBlock->filter(fn (Payment $payment) => $payment->reservation?->air_departure_id)->sum('amount'), 2);
                $cargo[] = round((float) $cargoBlock->sum('amount'), 2);
            }

            return [$labels, $fluvial, $air, $cargo];
        }

        $points = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()));

        foreach ($points as $point) {
            $paymentBlock = $payments->filter(function (Payment $payment) use ($point): bool {
                $paidAt = $payment->paid_at instanceof Carbon ? $payment->paid_at : Carbon::parse($payment->paid_at);

                return $paidAt->isSameDay($point);
            });
            $cargoBlock = $cargoShipments->filter(fn (CargoShipment $shipment) => $shipment->created_at->isSameDay($point));

            $labels[] = $point->format('d/m');
            $fluvial[] = round((float) $paymentBlock->filter(fn (Payment $payment) => $payment->reservation?->route_departure_id)->sum('amount'), 2);
            $air[] = round((float) $paymentBlock->filter(fn (Payment $payment) => $payment->reservation?->air_departure_id)->sum('amount'), 2);
            $cargo[] = round((float) $cargoBlock->sum('amount'), 2);
        }

        return [$labels, $fluvial, $air, $cargo];
    }
}
