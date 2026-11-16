<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencyLot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\User;
use App\Models\VesselSeat;
use App\Support\BaggageAllowance;
use App\Support\FareTable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgencySale
{
    public function __construct(private readonly BookingPaymentService $payments) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{reservation: Reservation, cargo: bool}
     */
    public function sell(User $seller, Agency $agency, RouteDeparture $departure, array $data): array
    {
        abort_unless((int) $seller->agency_id === (int) $agency->id, 403);
        if (! $agency->isActive()) {
            abort(422, 'Esta agencia está suspendida y no puede vender.');
        }

        $departure->loadMissing('transportRoute.organization', 'vessel');
        $organization = $departure->transportRoute->organization;
        abort_unless((int) $organization->id === (int) $agency->organization_id, 403);
        $allowance = BaggageAllowance::for($organization);
        $hand = (int) ($data['hand_kg'] ?? 0);
        $holdKg = (int) ($data['hold_kg'] ?? 0);

        $confirmed = DB::transaction(function () use ($seller, $agency, $departure, $data, $hand, $holdKg) {
            $locked = RouteDeparture::query()->whereKey($departure->id)->lockForUpdate()->firstOrFail();
            app(CounterSale::class)->assertOpen($locked);
            $seat = $this->lockSeat($locked, $data);
            $hold = RouteDepartureSeatHold::query()
                ->where('route_departure_id', $locked->id)
                ->where('vessel_seat_id', $seat->id)
                ->where('agency_id', $agency->id)
                ->lockForUpdate()
                ->first();
            if ($hold === null) {
                throw new DomainException('Esa plaza no está en un lote de tu agencia.');
            }

            $space = $seat->space_type ?: 'seat';
            $passenger = (string) $data['passenger_type'];
            $locked->loadMissing('transportRoute');
            $public = FareTable::price($locked->transportRoute, $space, $passenger, $locked->departure_at, $space === 'seat' ? (float) $locked->fare : null);
            if ($public === null) {
                throw new DomainException('Esa clase no tiene tarifa para este pasajero.');
            }
            $charged = $this->chargedPrice($public, (string) $agency->max_surcharge_percent, $data['sale_price'] ?? null);
            $this->assertPayment($data, $charged);
            FareTable::assertVests($locked, FareTable::occupants($seat));

            $first = trim((string) $data['first_name']);
            $last = trim((string) $data['last_name']);
            $name = trim($first.' '.$last);
            $code = 'AGE-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $reservation = Reservation::create([
                'route_departure_id' => $locked->id,
                'sold_by_user_id' => $seller->id,
                'agency_id' => $agency->id,
                'agency_lot_id' => $hold->agency_lot_id,
                'code' => $code,
                'contact_name' => $name,
                'contact_email' => ($data['email'] ?? null) ?: strtolower($code).'@pos.navegaya.test',
                'contact_phone' => $data['phone'] ?? '',
                'contact_document' => $data['document_number'],
                'base_amount' => $charged,
                'discount_amount' => 0,
                'total_amount' => $charged,
                'status' => 'pending_payment',
                'sales_channel' => 'agency',
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
                'hold_kg' => $holdKg,
            ]);
            $hold->delete();

            $method = (string) $data['method'];
            $tendered = $method === 'cash' ? round((float) $data['cash_tendered'], 2) : null;
            $change = $tendered === null ? null : round($tendered - $charged, 2);
            $confirmed = $this->payments->confirm($reservation, [
                'method' => $method,
                'provider' => 'pos',
                'provider_reference' => 'POS-'.$reservation->code,
                'provider_payload' => [
                    'registered_by' => $seller->id,
                    'agency_id' => $agency->id,
                    'cash_tendered' => $tendered,
                    'cash_change' => $change,
                    'operation_number' => $data['operation_number'] ?? null,
                ],
            ]);

            Payment::query()
                ->where('reservation_id', $confirmed->id)
                ->where('provider_reference', 'POS-'.$confirmed->code)
                ->update([
                    'cash_tendered' => $tendered,
                    'cash_change' => $change,
                    'operation_number' => $data['operation_number'] ?? null,
                ]);

            return $confirmed;
        });

        return [
            'reservation' => $confirmed->refresh(),
            'cargo' => ($hand + $holdKg) > $allowance['cargo'],
        ];
    }

    public function ceiling(float $public, string $percent): float
    {
        $cents = (int) round($public * 100);
        $extra = (int) round($cents * ((float) $percent) / 100);

        return round(($cents + $extra) / 100, 2);
    }

    /**
     * @return array{sold: int, free: int, released: int, assigned: int, total: string}
     */
    public function tally(AgencyLot $lot): array
    {
        $free = $lot->holds()->count();
        $released = $lot->releases()->count();
        $sold = ReservationSeat::query()
            ->whereHas('reservation', fn ($query) => $query->where('agency_lot_id', $lot->id)->where('status', 'confirmed'))
            ->count();
        $assigned = $free + $released + $sold;
        $cents = (int) round(((float) $lot->unit_price) * 100) * $assigned;

        return [
            'sold' => $sold,
            'free' => $free,
            'released' => $released,
            'assigned' => $assigned,
            'total' => number_format($cents / 100, 2, '.', ''),
        ];
    }

    private function chargedPrice(float $public, string $percent, mixed $asked): float
    {
        if ($asked === null || $asked === '') {
            throw ValidationException::withMessages(['sale_price' => 'Indica el precio de venta.']);
        }
        $price = round((float) $asked, 2);
        $floor = (int) round($public * 100);
        $cap = (int) round($this->ceiling($public, $percent) * 100);
        $cents = (int) round($price * 100);
        if ($cents < $floor || $cents > $cap) {
            throw ValidationException::withMessages([
                'sale_price' => 'El precio tiene que estar entre el público y el recargo máximo.',
            ]);
        }

        return $price;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lockSeat(RouteDeparture $departure, array $data): VesselSeat
    {
        $seat = VesselSeat::query()
            ->whereKey($data['seat_id'] ?? 0)
            ->where('vessel_id', $departure->vessel_id)
            ->lockForUpdate()
            ->first();
        if ($seat === null || ! $seat->is_available) {
            throw new DomainException('Esa plaza no está disponible.');
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
     * @param  array<string, mixed>  $data
     */
    private function assertPayment(array $data, float $total): void
    {
        if ($data['method'] === 'cash') {
            $tendered = round((float) ($data['cash_tendered'] ?? 0), 2);
            if ((int) round($tendered * 100) < (int) round($total * 100)) {
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
}
