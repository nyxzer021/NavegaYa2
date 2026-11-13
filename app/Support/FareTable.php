<?php

namespace App\Support;

use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\Discount;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\RouteFare;
use App\Models\TransportRoute;
use App\Models\VesselSeat;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Validation\ValidationException;

class FareTable
{
    public static function price(TransportRoute|AirRoute $route, string $space, string $passenger, CarbonInterface $on, ?float $seatFallback = null): ?float
    {
        if ($route instanceof AirRoute && $space !== 'seat') {
            return null;
        }

        $own = self::match($route, $space, $passenger, $on);
        if ($own !== null) {
            return $own;
        }
        if ($passenger === 'infant') {
            return null;
        }
        if (in_array($passenger, ['child', 'student', 'senior'], true)) {
            return self::price($route, $space, 'adult', $on, $seatFallback);
        }
        if ($space === 'seat') {
            return $seatFallback === null ? null : round($seatFallback, 2);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function guardOverlap(array $attributes, ?int $ignoreId = null): void
    {
        $query = RouteFare::query()
            ->where('organization_id', $attributes['organization_id'])
            ->where('space_type', $attributes['space_type'])
            ->where('passenger_type', $attributes['passenger_type']);
        if (! empty($attributes['transport_route_id'])) {
            $query->where('transport_route_id', $attributes['transport_route_id']);
        } else {
            $query->where('air_route_id', $attributes['air_route_id']);
        }
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        $candidate = new RouteFare([
            'starts_on' => $attributes['starts_on'] ?? null,
            'ends_on' => $attributes['ends_on'] ?? null,
        ]);
        foreach ($query->get() as $other) {
            if (self::rangesOverlap($candidate, $other)) {
                throw ValidationException::withMessages([
                    'ends_on' => 'Esa temporada se cruza con otra tarifa de la misma clase y tipo de pasajero.',
                ]);
            }
        }
    }

    /**
     * @return array{base: float, discount: float, net: float}
     */
    public static function net(float $base, Discount $discount): array
    {
        $base = round($base, 2);
        $off = $discount->kind === 'percent'
            ? round($base * ((float) $discount->value) / 100, 2)
            : round((float) $discount->value, 2);
        $off = round(min(max(0, $off), $base), 2);

        return ['base' => $base, 'discount' => $off, 'net' => round($base - $off, 2)];
    }

    public static function assertDiscount(?Discount $discount, RouteDeparture|AirDeparture $departure, string $channel, ?string $document): void
    {
        if ($discount === null || $discount->status !== 'active') {
            throw new DomainException('Ese descuento no está disponible.');
        }

        $organizationId = $departure instanceof AirDeparture
            ? $departure->airRoute->organization_id
            : $departure->transportRoute->organization_id;
        if ((int) $discount->organization_id !== (int) $organizationId) {
            throw new DomainException('Ese descuento no pertenece a esta empresa.');
        }

        $day = $departure->departure_at->copy()->timezone('America/Lima')->toDateString();
        $starts = $discount->starts_on?->toDateString();
        $ends = $discount->ends_on?->toDateString();
        if (($starts !== null && $starts > $day) || ($ends !== null && $ends < $day)) {
            throw new DomainException('Ese descuento no cubre la fecha de la salida.');
        }
        if (! in_array($channel, $discount->channels ?? [], true)) {
            throw new DomainException('Ese descuento no aplica en este canal.');
        }
        if ($discount->requires_document && blank($document)) {
            throw new DomainException('Ese descuento pide el documento del pasajero.');
        }
        if ($discount->usage_limit !== null && $discount->redeemed_count >= $discount->usage_limit) {
            throw new DomainException('Ese descuento ya no tiene usos disponibles.');
        }

        $discount->loadMissing('assignments');
        if ($discount->assignments->isEmpty()) {
            return;
        }
        $matches = $departure instanceof AirDeparture
            ? $discount->assignments->contains(fn ($row): bool => (int) $row->air_route_id === (int) $departure->air_route_id)
            : $discount->assignments->contains(fn ($row): bool => (int) $row->transport_route_id === (int) $departure->transport_route_id);
        if (! $matches) {
            throw new DomainException('Ese descuento no aplica a esta ruta.');
        }
    }

    public static function occupants(VesselSeat $seat): int
    {
        if ($seat->space_type === 'cabin_bed') {
            return max(1, (int) $seat->bed_count);
        }

        return 1;
    }

    public static function peopleBooked(RouteDeparture $departure): int
    {
        $rows = ReservationSeat::query()
            ->where('route_departure_id', $departure->id)
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['expired', 'cancelled', 'refunded']))
            ->with('seat')
            ->get();
        $people = 0;
        foreach ($rows as $row) {
            if ($row->passenger_type === 'infant' || $row->vessel_seat_id === null) {
                $people++;

                continue;
            }
            $people += $row->seat ? self::occupants($row->seat) : 1;
        }

        return $people;
    }

    public static function assertVests(RouteDeparture $departure, int $adding): void
    {
        $departure->loadMissing('vessel');
        $limit = $departure->vessel?->life_vest_count;
        if ($limit === null) {
            return;
        }
        if (self::peopleBooked($departure) + $adding > (int) $limit) {
            throw new DomainException('No hay chalecos para todas las personas de esta salida.');
        }
    }

    private static function match(TransportRoute|AirRoute $route, string $space, string $passenger, CarbonInterface $on): ?float
    {
        $day = $on->copy()->timezone('America/Lima')->toDateString();
        $query = RouteFare::query()->where('space_type', $space)->where('passenger_type', $passenger);
        if ($route instanceof AirRoute) {
            $query->where('air_route_id', $route->id);
        } else {
            $query->where('transport_route_id', $route->id);
        }
        $matched = $query->get()->filter(fn (RouteFare $fare): bool => self::covers($fare, $day));
        if ($matched->isEmpty()) {
            return null;
        }
        $chosen = $matched->sortBy(fn (RouteFare $fare): int => self::span($fare))->first();

        return round((float) $chosen->amount, 2);
    }

    private static function covers(RouteFare $fare, string $day): bool
    {
        $starts = $fare->starts_on?->toDateString();
        $ends = $fare->ends_on?->toDateString();

        return ($starts === null || $starts <= $day) && ($ends === null || $ends >= $day);
    }

    private static function span(RouteFare $fare): int
    {
        if ($fare->starts_on === null || $fare->ends_on === null) {
            return PHP_INT_MAX;
        }

        return $fare->starts_on->diffInDays($fare->ends_on);
    }

    private static function rangesOverlap(RouteFare $left, RouteFare $right): bool
    {
        $leftStart = $left->starts_on?->toDateString() ?? '0001-01-01';
        $leftEnd = $left->ends_on?->toDateString() ?? '9999-12-31';
        $rightStart = $right->starts_on?->toDateString() ?? '0001-01-01';
        $rightEnd = $right->ends_on?->toDateString() ?? '9999-12-31';

        return $leftStart <= $rightEnd && $rightStart <= $leftEnd;
    }
}
