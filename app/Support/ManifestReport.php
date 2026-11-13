<?php

namespace App\Support;

use App\Models\AirDeparture;
use App\Models\CargoShipment;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use Illuminate\Support\Collection;

class ManifestReport
{
    /**
     * @return array{passengers: Collection<int, ReservationSeat>, crew: Collection<int, mixed>, vests: int, people: int, captain: string}
     */
    public static function river(RouteDeparture $departure): array
    {
        $departure->loadMissing(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization', 'assignments.member']);
        $passengers = ReservationSeat::query()
            ->where('route_departure_id', $departure->id)
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['expired', 'cancelled']))
            ->with(['seat', 'ticket', 'reservation'])
            ->get()
            ->sortBy(fn (ReservationSeat $seat) => ($seat->passenger_type === 'infant' ? 1 : 0).($seat->seat?->code ?? 'Z'))
            ->values();
        $captain = $departure->assignments->firstWhere('duty', 'command')?->member;

        return [
            'departure' => $departure,
            'passengers' => $passengers,
            'crew' => $departure->assignments,
            'vests' => (int) ($departure->vessel?->life_vest_count ?? 0),
            'people' => $passengers->count(),
            'captain' => $captain?->name ?? 'Sin capitán',
        ];
    }

    /**
     * @return Collection<int, CargoShipment>
     */
    public static function cargo(RouteDeparture|AirDeparture $departure): Collection
    {
        return CargoShipment::query()
            ->where($departure instanceof AirDeparture ? 'air_departure_id' : 'route_departure_id', $departure->id)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('code')
            ->get();
    }

    /**
     * @return array{baggage: float, cargo: float, total: float, limit: ?float, over: bool}
     */
    public static function airLoad(AirDeparture $departure): array
    {
        $departure->loadMissing(['aircraft.organization', 'airRoute']);
        $organization = $departure->aircraft?->organization ?? $departure->airRoute?->organization;
        $allowance = $organization ? (int) BaggageAllowance::for($organization)['air'] : 0;
        $people = ReservationSeat::query()
            ->where('air_departure_id', $departure->id)
            ->where('passenger_type', '!=', 'infant')
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['expired', 'cancelled']))
            ->count();
        $baggage = round($allowance * $people, 2);
        $cargo = round((float) self::cargo($departure)->sum(fn (CargoShipment $shipment) => $shipment->verified_weight_kg ?? $shipment->declared_weight_kg), 2);
        $limit = $departure->aircraft?->useful_load_kg !== null ? (float) $departure->aircraft->useful_load_kg : null;

        return [
            'baggage' => $baggage,
            'cargo' => $cargo,
            'total' => round($baggage + $cargo, 2),
            'limit' => $limit,
            'over' => $limit !== null && ($baggage + $cargo) > $limit,
        ];
    }
}
