<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\OrganizationSite;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VesselSeat;
use App\Support\AdminScope;
use App\Support\AuditRecorder;
use App\Support\BaggageAllowance;
use App\Support\DepartureSchedule;
use App\Support\DiscountLedger;
use App\Support\FareTable;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CounterSale
{
    /** @var list<string> */
    public const OPEN_STATUSES = ['scheduled', 'delayed', 'rescheduled', 'boarding'];

    /** @var list<string> */
    public const PASSENGERS = ['adult', 'child', 'student', 'senior', 'infant'];

    public function __construct(private readonly BookingPaymentService $payments) {}

    public function assertOpen(RouteDeparture $departure): void
    {
        $open = $departure->is_published
            && in_array($departure->status, self::OPEN_STATUSES, true)
            && $departure->departure_at->greaterThan(now());

        if (! $open) {
            abort(422, 'Esta salida no se puede vender: está cancelada, cerrada o ya zarpó.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{reservation: Reservation, cargo: bool}
     */
    public function sell(User $seller, RouteDeparture $departure, array $data): array
    {
        $this->assertOpen($departure);
        $departure->loadMissing('transportRoute.organization', 'vessel');
        $organization = $departure->transportRoute->organization;
        $allowance = BaggageAllowance::for($organization);
        $hand = (int) ($data['hand_kg'] ?? 0);
        $hold = (int) ($data['hold_kg'] ?? 0);
        $siteId = $this->siteId($organization->id, $data['site_id'] ?? null);

        $confirmed = DB::transaction(function () use ($seller, $departure, $data, $hand, $hold, $siteId) {
            $locked = RouteDeparture::query()->whereKey($departure->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);
            $this->releaseExpired($locked);
            $seat = $this->lockSeat($locked, $data);
            $space = $seat->space_type ?: 'seat';
            $passenger = (string) $data['passenger_type'];
            $locked->loadMissing('transportRoute');
            $base = FareTable::price($locked->transportRoute, $space, $passenger, $locked->departure_at, $space === 'seat' ? (float) $locked->fare : null);
            if ($base === null) {
                throw new DomainException('Esa clase no tiene tarifa para este pasajero.');
            }
            [$discount, $discountAmount] = $this->discount($locked, $data['discount_id'] ?? null, $base, (string) $data['document_number']);
            $total = round($base - $discountAmount, 2);
            $this->assertPayment($data, $total);
            FareTable::assertVests($locked, FareTable::occupants($seat));

            $first = trim((string) $data['first_name']);
            $last = trim((string) $data['last_name']);
            $name = trim($first.' '.$last);
            $code = 'POS-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $reservation = Reservation::create([
                'route_departure_id' => $locked->id,
                'sold_by_user_id' => $seller->id,
                'organization_site_id' => $siteId,
                'code' => $code,
                'contact_name' => $name,
                'contact_email' => ($data['email'] ?? null) ?: strtolower($code).'@pos.navegaya.test',
                'contact_phone' => $data['phone'] ?? '',
                'contact_document' => $data['document_number'],
                'base_amount' => $base,
                'discount_amount' => $discountAmount,
                'discount_id' => $discount?->id,
                'discount_held' => $discount !== null,
                'total_amount' => $total,
                'status' => 'pending_payment',
                'sales_channel' => 'counter',
                'expires_at' => null,
            ]);
            ReservationSeat::create([
                'reservation_id' => $reservation->id,
                'route_departure_id' => $locked->id,
                'vessel_seat_id' => $seat->id,
                'passenger_name' => $name,
                'first_name' => $first,
                'last_name' => $last,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'guardian_document' => $data['guardian_document'] ?? null,
                'passenger_type' => $passenger,
                'hand_kg' => $hand,
                'hold_kg' => $hold,
            ]);
            if ($discount !== null) {
                DiscountLedger::redeem($discount);
            }

            $method = (string) $data['method'];
            $tendered = $method === 'cash' ? round((float) $data['cash_tendered'], 2) : null;
            $change = $tendered === null ? null : round($tendered - $total, 2);
            $confirmed = $this->payments->confirm($reservation, [
                'method' => $method,
                'provider' => 'pos',
                'provider_reference' => 'POS-'.$reservation->code,
                'provider_payload' => [
                    'registered_by' => $seller->id,
                    'site_id' => $siteId,
                    'cash_tendered' => $tendered,
                    'cash_change' => $change,
                    'operation_number' => $data['operation_number'] ?? null,
                ],
            ]);

            return [$confirmed, $tendered, $change, $data['operation_number'] ?? null];
        });

        [$confirmed, $tendered, $change, $operation] = $confirmed;
        Payment::query()->where('reservation_id', $confirmed->id)->where('provider_reference', 'POS-'.$confirmed->code)->update([
            'cash_tendered' => $tendered,
            'cash_change' => $change,
            'operation_number' => $operation,
        ]);

        return [
            'reservation' => $confirmed->refresh(),
            'cargo' => ($hand + $hold) > $allowance['cargo'],
        ];
    }

    public function void(Request $request, Reservation $reservation, string $reason): void
    {
        $seller = $request->user();
        $reservation->loadMissing('departure.transportRoute', 'seats.ticket', 'payments');
        $organizationId = (int) $reservation->departure?->transportRoute?->organization_id;
        abort_unless($organizationId === (int) AdminScope::organizationId($seller), 403);
        abort_unless($reservation->sales_channel === 'counter' && $reservation->status === 'confirmed', 422);

        $soldOn = ($reservation->paid_at ?? $reservation->created_at)?->copy()->timezone(DepartureSchedule::ZONE);
        $today = now()->timezone(DepartureSchedule::ZONE);
        if ($soldOn === null || ! $soldOn->isSameDay($today)) {
            throw ValidationException::withMessages(['reason' => 'Solo se anula una venta del muelle el mismo día.']);
        }
        $sameSeller = (int) $reservation->sold_by_user_id === (int) $seller->id;
        if (! $sameSeller && ! $seller->isCompanyAdmin()) {
            abort(403);
        }
        if ($reservation->seats->contains(fn (ReservationSeat $seat): bool => $seat->ticket?->status === 'boarded')) {
            throw ValidationException::withMessages(['reason' => 'Este pasajero ya embarcó. La anulación no libera una plaza embarcada.']);
        }

        DB::transaction(function () use ($request, $reservation, $reason, $seller): void {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'confirmed', 422);
            $locked->seats()->update(['vessel_seat_id' => null]);
            Ticket::query()->whereIn('reservation_seat_id', $locked->seats()->pluck('id'))->update(['status' => 'voided']);
            Payment::query()->where('reservation_id', $locked->id)->where('status', 'confirmed')->update([
                'status' => 'voided',
                'voided_at' => now(),
            ]);
            $locked->update([
                'status' => 'cancelled',
                'voided_at' => now(),
                'voided_by_user_id' => $seller->id,
                'void_reason' => $reason,
            ]);
            DiscountLedger::release($locked);
            AuditRecorder::record($request, 'counter_sale_voided', $locked, [
                'reason' => $reason,
                'method' => $locked->payment_method,
                'amount' => (string) $locked->total_amount,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lockSeat(RouteDeparture $departure, array $data): VesselSeat
    {
        if (($data['place'] ?? null) === 'hammock') {
            $taken = $this->busySeatIds($departure->id);
            $seat = VesselSeat::query()
                ->where('vessel_id', $departure->vessel_id)
                ->where('space_type', 'hammock')
                ->where('is_available', true)
                ->when($taken !== [], fn ($query) => $query->whereNotIn('id', $taken))
                ->orderBy('code')
                ->lockForUpdate()
                ->first();
            if ($seat === null) {
                throw new DomainException('No quedan hamacas libres en esta salida.');
            }

            return $seat;
        }

        $seat = VesselSeat::query()
            ->whereKey($data['seat_id'] ?? 0)
            ->where('vessel_id', $departure->vessel_id)
            ->lockForUpdate()
            ->first();
        if ($seat === null || ! $seat->is_available) {
            throw new DomainException('Esa plaza no está disponible.');
        }
        if (RouteDepartureSeatHold::query()->where('route_departure_id', $departure->id)->where('vessel_seat_id', $seat->id)->exists()) {
            throw new DomainException('Esa plaza está apartada para una agencia.');
        }
        $taken = ReservationSeat::query()
            ->where('route_departure_id', $departure->id)
            ->where('vessel_seat_id', $seat->id)
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['expired', 'cancelled', 'refunded']))
            ->lockForUpdate()
            ->exists();
        if ($taken) {
            throw new DomainException('Esa plaza ya está ocupada.');
        }

        return $seat;
    }

    /**
     * @return array{0: ?Discount, 1: float}
     */
    private function discount(RouteDeparture $departure, mixed $discountId, float $base, string $document): array
    {
        if (blank($discountId)) {
            return [null, 0.0];
        }
        $discount = Discount::query()->whereKey($discountId)->first();
        FareTable::assertDiscount($discount, $departure, 'counter', $document);
        $net = FareTable::net($base, $discount);

        return [$discount, $net['discount']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPayment(array $data, float $total): void
    {
        if ($data['method'] === 'cash') {
            $tendered = round((float) ($data['cash_tendered'] ?? 0), 2);
            if ($this->cents($tendered) < $this->cents($total)) {
                throw ValidationException::withMessages([
                    'cash_tendered' => 'El monto recibido no alcanza para el total.',
                ]);
            }

            return;
        }
        if (blank($data['operation_number'] ?? null)) {
            throw ValidationException::withMessages([
                'operation_number' => 'El número de operación es obligatorio.',
            ]);
        }
    }

    private function siteId(int $organizationId, mixed $siteId): ?int
    {
        $sites = OrganizationSite::query()->where('organization_id', $organizationId)->where('is_active', true);
        if ($sites->doesntExist()) {
            return null;
        }
        $site = OrganizationSite::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereKey($siteId)
            ->first();
        if ($site === null) {
            throw ValidationException::withMessages(['site_id' => 'Elige la sede donde estás vendiendo.']);
        }

        return $site->id;
    }

    /**
     * @return list<int>
     */
    private function busySeatIds(int $departureId): array
    {
        $sold = ReservationSeat::query()
            ->where('route_departure_id', $departureId)
            ->whereNotNull('vessel_seat_id')
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['expired', 'cancelled', 'refunded']))
            ->pluck('vessel_seat_id');
        $held = RouteDepartureSeatHold::query()->where('route_departure_id', $departureId)->pluck('vessel_seat_id');

        return $sold->merge($held)->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /**
     * @return list<int>
     */
    public function busySeatIdsFor(int $departureId): array
    {
        return $this->busySeatIds($departureId);
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function releaseExpired(RouteDeparture $departure): void
    {
        $expired = Reservation::query()
            ->where('route_departure_id', $departure->id)
            ->where('status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');
        if ($expired->isNotEmpty()) {
            Reservation::expirePending($expired);
        }
    }
}
