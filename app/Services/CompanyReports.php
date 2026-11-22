<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\CargoShipment;
use App\Models\CashSession;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\TicketRefund;
use App\Support\DepartureSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CompanyReports
{
    public function __construct(
        private readonly CompanyCollections $collections,
        private readonly PaymentReceipts $receipts,
        private readonly AgencyAccounts $agencies,
        private readonly CashDesk $cash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(Organization $organization, string $view, string $from, string $to): array
    {
        $range = $this->range($view, $from, $to);
        $period = $this->period($range);
        $range['from'] = $period['from'];
        $range['to'] = $period['to'];
        $range['label'] = $period['label'];
        $statement = $this->collections->statement($organization, $period);
        $catalog = $this->receipts->catalog($organization, [
            'desde' => $period['from'],
            'hasta' => $period['to'],
        ]);
        $sales = $this->sales($catalog['rows']);
        $accounts = $this->agencies->statement($organization);

        return [
            'range' => $range,
            'collections' => $statement,
            'receipts' => $catalog['kpis'],
            'channels' => $sales['channels'],
            'routes' => $sales['routes'],
            'departures' => $sales['departures'],
            'sellers' => $sales['sellers'],
            'agencies_sales' => $sales['agencies'],
            'total_count' => $sales['count'],
            'total_cents' => $sales['cents'],
            'occupancy' => $this->occupancy($organization, $period['start'], $period['end']),
            'parcels' => $this->parcels($organization, $period['start'], $period['end'], $catalog['kpis']),
            'cash' => $this->drawers($organization, $period['start'], $period['end']),
            'refunds' => $this->refunds($organization),
            'agency_accounts' => $accounts['totals'],
        ];
    }

    public function soles(int $cents): string
    {
        return $this->collections->soles($cents);
    }

    public function signed(int $cents): string
    {
        return $this->agencies->soles($cents);
    }

    /**
     * @return array{view: string, from: string, to: string, label: string}
     */
    public function range(string $view, string $from, string $to): array
    {
        if (! in_array($view, ['hoy', 'semana', 'mes', 'rango'], true)) {
            $view = 'mes';
        }
        $today = now()->timezone(DepartureSchedule::ZONE);
        if ($view === 'hoy') {
            $start = $today->copy()->startOfDay();
            $end = $today->copy()->endOfDay();
            $label = __('reports.today_of', ['day' => $start->format('d/m/Y')]);
        } elseif ($view === 'semana') {
            $start = $today->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $today->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $label = __('reports.week_of', ['from' => $start->format('d/m/Y'), 'to' => $end->format('d/m/Y')]);
        } elseif ($view === 'rango') {
            $start = $this->day($from, false) ?? $today->copy()->startOfMonth()->startOfDay();
            $end = $this->day($to, true) ?? $today->copy()->endOfMonth()->endOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
            $label = __('reports.range_of', ['from' => $start->format('d/m/Y'), 'to' => $end->format('d/m/Y')]);
        } else {
            $start = $today->copy()->startOfMonth()->startOfDay();
            $end = $today->copy()->endOfMonth()->endOfDay();
            $label = ucfirst($start->locale('es')->translatedFormat('F Y'));
        }

        return [
            'view' => $view,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'label' => $label,
        ];
    }

    /**
     * @param  array{view: string, from: string, to: string, label: string}  $range
     * @return array{view: string, date: string, from: string, to: string, label: string, start: Carbon, end: Carbon}
     */
    private function period(array $range): array
    {
        if (in_array($range['view'], ['semana', 'mes'], true)) {
            return $this->collections->period($range['view'], $range['from']);
        }
        $start = Carbon::parse($range['from'], DepartureSchedule::ZONE)->startOfDay();
        $end = Carbon::parse($range['to'], DepartureSchedule::ZONE)->endOfDay();

        return [
            'view' => 'mes',
            'date' => $range['from'],
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
            'start' => $start->utc(),
            'end' => $end->utc(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{channels: list<array<string, mixed>>, routes: list<array<string, mixed>>, departures: list<array<string, mixed>>, sellers: list<array<string, mixed>>, agencies: list<array<string, mixed>>, count: int, cents: int}
     */
    private function sales(array $rows): array
    {
        $tickets = collect($rows)->filter(function (array $row): bool {
            return $row['kind'] === 'ticket' && in_array($row['status'], ['confirmed', 'succeeded'], true);
        })->values();
        $tickets->pluck('payment')->filter()->each(function (Payment $payment): void {
            $payment->loadMissing([
                'reservation.soldBy',
                'reservation.seats',
                'reservation.departure.transportRoute.originPort',
                'reservation.departure.transportRoute.destinationPort',
                'collector',
            ]);
        });
        $agencyNames = Agency::query()
            ->whereIn('id', $tickets->map(fn (array $row): int => (int) ($row['payment']->reservation?->agency_id ?? 0))->filter()->unique()->all())
            ->pluck('name', 'id');

        $channels = [
            'web' => $this->blank(__('reports.channel_web'), 'var(--ny-tone-river)'),
            'counter' => $this->blank(__('reports.channel_counter'), 'var(--ny-tone-leaf)'),
            'agency' => $this->blank(__('reports.channel_agency'), 'var(--ny-tone-violet)'),
        ];
        $routes = [];
        $departures = [];
        $sellers = [];
        $agencies = [];
        foreach ($tickets as $row) {
            /** @var Payment $payment */
            $payment = $row['payment'];
            $reservation = $payment->reservation;
            $channel = in_array($row['channel'], ['web', 'counter', 'agency'], true) ? $row['channel'] : 'counter';
            $count = max(1, (int) ($reservation?->seats?->count() ?? 0));
            $cents = (int) $row['amount_cents'];
            $channels[$channel]['count'] += $count;
            $channels[$channel]['cents'] += $cents;
            $route = $this->routeLabel($payment);
            $this->add($routes, $route, $route, $count, $cents);
            $when = $reservation?->departure?->departure_at;
            $departureKey = (string) ($reservation?->route_departure_id ?? 'none');
            $departureLabel = $when === null ? __('reports.no_departure') : $route.' · '.DepartureSchedule::show($when, 'Y-m-d H:i');
            $this->add($departures, $departureKey, $departureLabel, $count, $cents);
            $seller = $reservation?->soldBy?->name ?: $payment->collector?->name ?: __('reports.no_seller');
            $this->add($sellers, $seller, $seller, $count, $cents);
            if ($channel === 'agency') {
                $name = (string) ($agencyNames[(int) ($reservation?->agency_id ?? 0)] ?? __('reports.no_agency'));
                $this->add($agencies, $name, $name, $count, $cents);
            }
        }
        $peak = max(1, $channels['web']['cents'], $channels['counter']['cents'], $channels['agency']['cents']);
        foreach ($channels as $key => $channel) {
            $channels[$key]['percent'] = (int) round($channel['cents'] / $peak * 100);
        }

        return [
            'channels' => array_values($channels),
            'routes' => array_values($routes),
            'departures' => array_values($departures),
            'sellers' => array_values($sellers),
            'agencies' => array_values($agencies),
            'count' => array_sum(array_column($channels, 'count')),
            'cents' => array_sum(array_column($channels, 'cents')),
        ];
    }

    /**
     * @return list<array{label: string, when: string, sold: int, capacity: int, percent: int}>
     */
    private function occupancy(Organization $organization, Carbon $start, Carbon $end): array
    {
        $departures = RouteDeparture::query()
            ->whereBetween('departure_at', [$start, $end])
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.seats'])
            ->orderBy('departure_at')
            ->get();
        $sold = ReservationSeat::query()
            ->whereIn('route_departure_id', $departures->pluck('id'))
            ->whereNotNull('vessel_seat_id')
            ->whereHas('reservation', fn ($query) => $query->where('status', 'confirmed'))
            ->selectRaw('route_departure_id, count(*) as sold')
            ->groupBy('route_departure_id')
            ->pluck('sold', 'route_departure_id');

        return $departures->map(function (RouteDeparture $departure) use ($sold): array {
            $capacity = (int) ($departure->vessel?->seats?->count() ?? 0);
            $taken = (int) ($sold[$departure->id] ?? 0);
            $percent = $capacity > 0 ? (int) min(100, round($taken / $capacity * 100)) : 0;
            $origin = $departure->transportRoute?->originPort?->name ?: __('reports.unknown_place');
            $destination = $departure->transportRoute?->destinationPort?->name ?: __('reports.unknown_place');

            return [
                'label' => $origin.' → '.$destination,
                'when' => DepartureSchedule::show($departure->departure_at, 'Y-m-d H:i'),
                'sold' => $taken,
                'capacity' => $capacity,
                'percent' => $percent,
            ];
        })->all();
    }

    /**
     * @param  array{receipts: int, tickets: int, ticket_cents: int, parcels: int, parcel_cents: int, invoices: int}  $kpis
     * @return array{count: int, kg: string, cents: int, due_count: int, due_cents: int}
     */
    private function parcels(Organization $organization, Carbon $start, Carbon $end, array $kpis): array
    {
        $shipments = CargoShipment::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('received_at', [$start, $end])
            ->get(['declared_weight_kg']);
        $due = $this->cash->receivable($organization->id);

        return [
            'count' => $shipments->count(),
            'kg' => number_format((float) $shipments->sum('declared_weight_kg'), 2, '.', ''),
            'cents' => (int) $kpis['parcel_cents'],
            'due_count' => $due->count(),
            'due_cents' => (int) $due->sum(fn (CargoShipment $shipment): int => (int) round(((float) $shipment->amount) * 100)),
        ];
    }

    /**
     * @return array{rows: list<array{seller: string, cents: int, sessions: int}>, cents: int}
     */
    private function drawers(Organization $organization, Carbon $start, Carbon $end): array
    {
        $sessions = CashSession::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('closed_at')
            ->whereBetween('opened_at', [$start, $end])
            ->with('user')
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();
        $grouped = [];
        foreach ($sessions as $session) {
            $seller = $session->user?->name ?: __('reports.no_seller');
            $cents = (int) round(((float) $session->difference) * 100);
            if (! isset($grouped[$seller])) {
                $grouped[$seller] = ['seller' => $seller, 'cents' => 0, 'sessions' => 0];
            }
            $grouped[$seller]['cents'] += $cents;
            $grouped[$seller]['sessions']++;
        }

        return [
            'rows' => array_values($grouped),
            'cents' => array_sum(array_column($grouped, 'cents')),
        ];
    }

    /**
     * @return array{requested: int, approved_cents: int, paid_cents: int, rejected: int}
     */
    private function refunds(Organization $organization): array
    {
        $rows = TicketRefund::query()->where('organization_id', $organization->id)->get();

        return [
            'requested' => $rows->where('status', 'requested')->count(),
            'approved_cents' => $this->refundCents($rows, 'approved'),
            'paid_cents' => $this->refundCents($rows, 'paid'),
            'rejected' => $rows->where('status', 'rejected')->count(),
        ];
    }

    /**
     * @param  Collection<int, TicketRefund>  $rows
     */
    private function refundCents(Collection $rows, string $status): int
    {
        return (int) $rows->where('status', $status)->sum(fn (TicketRefund $refund): int => $this->collections->cents((string) $refund->amount));
    }

    /**
     * @param  array<string, array{label: string, count: int, cents: int}>  $groups
     */
    private function add(array &$groups, string $key, string $label, int $count, int $cents): void
    {
        if (! isset($groups[$key])) {
            $groups[$key] = ['label' => $label, 'count' => 0, 'cents' => 0];
        }
        $groups[$key]['count'] += $count;
        $groups[$key]['cents'] += $cents;
    }

    /**
     * @return array{key: string, label: string, tone: string, count: int, cents: int, percent: int}
     */
    private function blank(string $label, string $tone): array
    {
        return ['label' => $label, 'tone' => $tone, 'count' => 0, 'cents' => 0, 'percent' => 0];
    }

    private function routeLabel(Payment $payment): string
    {
        $route = $payment->reservation?->departure?->transportRoute;
        $origin = $route?->originPort?->name ?: __('reports.unknown_place');
        $destination = $route?->destinationPort?->name ?: __('reports.unknown_place');

        return $origin.' → '.$destination;
    }

    private function day(string $value, bool $end): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $day = Carbon::parse($value, DepartureSchedule::ZONE);

        return $end ? $day->endOfDay() : $day->startOfDay();
    }
}
