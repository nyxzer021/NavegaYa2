<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencyLot;
use App\Models\AgencyLotRelease;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\User;
use App\Models\VesselSeat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyLotDesk
{
    public function __construct(private readonly CounterSale $counter) {}

    /**
     * @param  list<int>  $seatIds
     */
    public function assign(Agency $agency, RouteDeparture $departure, array $seatIds, int $hammocks, string $unitPrice, string $payment, ?string $note): AgencyLot
    {
        $departure->loadMissing('transportRoute');
        abort_unless((int) $departure->transportRoute->organization_id === (int) $agency->organization_id, 403);

        return DB::transaction(function () use ($agency, $departure, $seatIds, $hammocks, $unitPrice, $payment, $note): AgencyLot {
            $locked = RouteDeparture::query()->whereKey($departure->id)->lockForUpdate()->firstOrFail();
            $this->counter->assertOpen($locked);
            $chosen = $this->seats($locked, $seatIds);
            $hammockSeats = $this->hammocks($locked, $hammocks);
            $rows = $chosen->concat($hammockSeats);
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['seats' => 'Elige al menos una plaza o una cantidad de hamacas.']);
            }

            $lot = AgencyLot::create([
                'agency_id' => $agency->id,
                'route_departure_id' => $locked->id,
                'unit_price' => $unitPrice,
                'payment_status' => $payment,
                'note' => $note,
            ]);
            foreach ($rows as $seat) {
                RouteDepartureSeatHold::create([
                    'route_departure_id' => $locked->id,
                    'vessel_seat_id' => $seat->id,
                    'kind' => 'agency',
                    'agency_id' => $agency->id,
                    'agency_lot_id' => $lot->id,
                ]);
            }

            return $lot;
        });
    }

    /**
     * @param  list<int>  $seatIds
     */
    public function release(User $actor, AgencyLot $lot, array $seatIds, string $note): void
    {
        DB::transaction(function () use ($actor, $lot, $seatIds, $note): void {
            $holds = RouteDepartureSeatHold::query()
                ->where('agency_lot_id', $lot->id)
                ->whereIn('vessel_seat_id', $seatIds)
                ->lockForUpdate()
                ->get();
            if ($holds->isEmpty()) {
                throw ValidationException::withMessages(['seats' => 'Esas plazas ya no están en el lote.']);
            }
            foreach ($holds as $hold) {
                AgencyLotRelease::create([
                    'agency_lot_id' => $lot->id,
                    'vessel_seat_id' => $hold->vessel_seat_id,
                    'note' => $note,
                    'released_by_user_id' => $actor->id,
                ]);
                $hold->delete();
            }
        });
    }

    /**
     * @param  list<int>  $seatIds
     * @return Collection<int, VesselSeat>
     */
    private function seats(RouteDeparture $departure, array $seatIds): Collection
    {
        if ($seatIds === []) {
            return collect();
        }
        $busy = $this->counter->busySeatIdsFor($departure->id);
        $seats = VesselSeat::query()
            ->where('vessel_id', $departure->vessel_id)
            ->whereIn('id', $seatIds)
            ->whereIn('space_type', ['seat', 'cabin_bed'])
            ->where('is_available', true)
            ->lockForUpdate()
            ->get();
        if ($seats->count() !== count(array_unique($seatIds))) {
            throw ValidationException::withMessages(['seats' => 'Una de las plazas no pertenece a esta embarcación.']);
        }
        if ($seats->contains(fn (VesselSeat $seat): bool => in_array($seat->id, $busy, true))) {
            throw ValidationException::withMessages(['seats' => 'Una de las plazas ya está ocupada o apartada.']);
        }

        return $seats;
    }

    /**
     * @return Collection<int, VesselSeat>
     */
    private function hammocks(RouteDeparture $departure, int $count): Collection
    {
        if ($count < 1) {
            return collect();
        }
        $busy = $this->counter->busySeatIdsFor($departure->id);
        $seats = VesselSeat::query()
            ->where('vessel_id', $departure->vessel_id)
            ->where('space_type', 'hammock')
            ->where('is_available', true)
            ->when($busy !== [], fn ($query) => $query->whereNotIn('id', $busy))
            ->orderBy('code')
            ->lockForUpdate()
            ->limit($count)
            ->get();
        if ($seats->count() < $count) {
            throw ValidationException::withMessages(['hammocks' => 'No hay tantas hamacas libres en esta salida.']);
        }

        return $seats;
    }
}
