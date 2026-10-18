<?php

namespace App\Http\Controllers\Admin;

use App\Models\AirDeparture;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\RouteDeparture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminItineraryController extends AdminSupervisionController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'modality' => ['nullable', 'in:fluvial,aereo,mixto'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:published,without_inventory,paused'],
        ]);
        $selectedDate = isset($filters['date']) ? Carbon::parse($filters['date'])->startOfDay() : today();

        $riverDepartures = RouteDeparture::query()
            ->with(['transportRoute.organization', 'transportRoute.originPort', 'transportRoute.destinationPort', 'vessel', 'reservations.seats'])
            ->whereDate('departure_at', $selectedDate)
            ->get()
            ->groupBy(fn (RouteDeparture $departure) => $departure->transportRoute?->organization_id);

        $airDepartures = AirDeparture::query()
            ->with(['airRoute.organization', 'aircraft', 'reservations.seats'])
            ->whereDate('departure_at', $selectedDate)
            ->get()
            ->groupBy(fn (AirDeparture $departure) => $departure->airRoute?->organization_id);

        $payments = Payment::query()
            ->with(['reservation.departure.transportRoute', 'reservation.airDeparture.airRoute'])
            ->whereIn('status', ['succeeded', 'confirmed'])
            ->where(function ($query) use ($selectedDate) {
                $query->whereHas('reservation.departure', fn ($departure) => $departure->whereDate('departure_at', $selectedDate))
                    ->orWhereHas('reservation.airDeparture', fn ($departure) => $departure->whereDate('departure_at', $selectedDate));
            })
            ->get()
            ->groupBy(function (Payment $payment) {
                $reservation = $payment->reservation;

                return $reservation?->departure?->transportRoute?->organization_id
                    ?? $reservation?->airDeparture?->airRoute?->organization_id;
            });

        $operators = Organization::query()
            ->where('type', 'transport_company')
            ->where('status', 'active')
            ->with(['transportRoutes.originPort', 'transportRoutes.destinationPort', 'airRoutes'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($scope) => $scope
                    ->where('commercial_name', 'like', '%'.$search.'%')
                    ->orWhere('legal_name', 'like', '%'.$search.'%')
                    ->orWhere('ruc', 'like', '%'.$search.'%'));
            })
            ->when($filters['modality'] ?? null, fn ($query, string $modality) => $query->where('modality', $modality))
            ->orderBy('commercial_name')
            ->get()
            ->map(function (Organization $organization) use ($riverDepartures, $airDepartures, $payments) {
                $river = $riverDepartures->get($organization->id, collect());
                $air = $airDepartures->get($organization->id, collect());
                $publishedRiver = $river->filter(fn (RouteDeparture $departure) => $departure->is_published
                    && in_array($departure->status, ['scheduled', 'boarding'], true));
                $publishedAir = $air->filter(fn (AirDeparture $departure) => $departure->is_published
                    && in_array($departure->status, ['scheduled', 'boarding'], true));

                $riverCapacity = $publishedRiver->sum(fn (RouteDeparture $departure) => (int) ($departure->vessel?->seat_capacity ?? 0));
                $airCapacity = $publishedAir->sum(fn (AirDeparture $departure) => (int) ($departure->aircraft?->seat_capacity ?? 0));
                $soldSeats = $publishedRiver->sum(fn (RouteDeparture $departure) => $this->confirmedSeats($departure->reservations))
                    + $publishedAir->sum(fn (AirDeparture $departure) => $this->confirmedSeats($departure->reservations));

                $operatorPayments = $payments->get($organization->id, collect());
                $gmv = round((float) $operatorPayments->sum('amount'), 2);
                $recordedCommission = round((float) $operatorPayments->sum('commission_amount'), 2);
                $commissionRate = (float) ($organization->commission_rate ?? 8);
                $commission = $recordedCommission > 0
                    ? $recordedCommission
                    : round($gmv * ($commissionRate / 100), 2);

                $destinations = $river->map(fn (RouteDeparture $departure) => $departure->transportRoute?->destinationPort?->city)
                    ->concat($air->map(fn (AirDeparture $departure) => $departure->airRoute?->destination_city))
                    ->filter()->unique();

                if ($destinations->isEmpty()) {
                    $destinations = $organization->transportRoutes->pluck('destinationPort.city')
                        ->concat($organization->airRoutes->pluck('destination_city'))
                        ->filter()->unique();
                }

                $hasRiver = $organization->transportRoutes->isNotEmpty() || $river->isNotEmpty();
                $hasAir = $organization->airRoutes->isNotEmpty() || $air->isNotEmpty();
                $modality = $hasRiver && $hasAir ? 'Mixto' : ($hasAir ? 'Aéreo' : 'Fluvial');
                $departuresCount = $river->count() + $air->count();
                $publishedCount = $publishedRiver->count() + $publishedAir->count();

                return (object) [
                    'id' => $organization->id,
                    'name' => $organization->commercial_name ?: $organization->legal_name,
                    'legal_name' => $organization->legal_name,
                    'ruc' => $organization->ruc,
                    'base_city' => $organization->base_city ?: 'Loreto',
                    'modality' => $modality,
                    'modality_key' => $hasRiver && $hasAir ? 'mixto' : ($hasAir ? 'aereo' : 'fluvial'),
                    'destinations' => $destinations->take(3)->implode(', ') ?: 'Rutas locales',
                    'departures_count' => $departuresCount,
                    'published_departures_count' => $publishedCount,
                    'capacity' => $riverCapacity + $airCapacity,
                    'sold_seats' => $soldSeats,
                    'gmv' => $gmv,
                    'commission' => $commission,
                    'commission_rate' => $commissionRate,
                    'is_paused' => (bool) $organization->is_marketplace_paused,
                ];
            })
            ->when($filters['status'] ?? null, fn ($items, string $status) => $items->filter(fn ($operator) => match ($status) {
                'published' => $operator->published_departures_count > 0 && ! $operator->is_paused,
                'without_inventory' => $operator->published_departures_count === 0,
                'paused' => $operator->is_paused,
            })->values());

        $fluvialOperators = $operators
            ->filter(fn ($operator) => in_array($operator->modality_key, ['fluvial', 'mixto'], true))
            ->values();
        $airOperators = $operators
            ->filter(fn ($operator) => in_array($operator->modality_key, ['aereo', 'air'], true))
            ->values();

        $riverForDay = $riverDepartures->flatten(1);
        $airForDay = $airDepartures->flatten(1);
        $dayDepartures = $riverForDay->concat($airForDay);
        $departedStatuses = ['departed', 'in_transit', 'completed'];
        $pendingStatuses = ['scheduled', 'boarding'];
        $rescheduledFromNotes = $dayDepartures->filter(fn ($departure) => $departure->status === 'rescheduled'
            || str_contains(mb_strtolower((string) $departure->notes), 'reprogram'));
        $rescheduledFromAudit = AuditLog::query()
            ->where('event', 'updated')
            ->where(function ($query) use ($riverForDay, $airForDay) {
                $query->where(fn ($river) => $river->where('subject_type', RouteDeparture::class)->whereIn('subject_id', $riverForDay->pluck('id')))
                    ->orWhere(fn ($air) => $air->where('subject_type', AirDeparture::class)->whereIn('subject_id', $airForDay->pluck('id')));
            })
            ->get(['subject_type', 'subject_id', 'changes'])
            ->filter(fn (AuditLog $log) => array_key_exists('departure_at', $log->changes['old'] ?? [])
                && array_key_exists('departure_at', $log->changes['new'] ?? []))
            ->map(fn (AuditLog $log) => $log->subject_type.':'.$log->subject_id);
        $rescheduledKeys = $rescheduledFromNotes->map(fn ($departure) => $departure::class.':'.$departure->id)
            ->concat($rescheduledFromAudit)->unique();

        return view('admin.itineraries.index', [
            'operators' => $operators,
            'fluvialOperators' => $fluvialOperators,
            'airOperators' => $airOperators,
            'totalPublishedSeats' => $operators->sum('capacity'),
            'totalPublishedDepartures' => $operators->sum('published_departures_count'),
            'totalSoldSeats' => $operators->sum('sold_seats'),
            'pausedOperatorsCount' => $operators->where('is_paused', true)->count(),
            'operatorsCount' => $operators->count(),
            'selectedDate' => $selectedDate,
            'scheduledDeparturesCount' => $dayDepartures->count(),
            'departedDeparturesCount' => $dayDepartures->whereIn('status', $departedStatuses)->count(),
            'pendingDeparturesCount' => $dayDepartures->whereIn('status', $pendingStatuses)->count(),
            'cancelledDeparturesCount' => $dayDepartures->where('status', 'cancelled')->count(),
            'rescheduledDeparturesCount' => $rescheduledKeys->count(),
        ]);
    }

    public function toggleOperatorSales(Organization $organization): RedirectResponse
    {
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);

        $organization->update(['is_marketplace_paused' => ! $organization->is_marketplace_paused]);

        return back()->with('success', $organization->is_marketplace_paused
            ? 'Las ventas web del operador fueron pausadas. Sus salidas ya no aparecen en el marketplace.'
            : 'Las ventas web del operador fueron reactivadas.');
    }

    private function confirmedSeats(Collection $reservations): int
    {
        return $reservations
            ->where('status', 'confirmed')
            ->sum(fn ($reservation) => $reservation->seats->count());
    }
}
