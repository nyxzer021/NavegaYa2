<?php

namespace App\Support;

use App\Models\AirDeparture;
use App\Models\CrewMember;
use App\Models\DepartureAssignment;
use App\Models\RouteDeparture;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CrewRoster
{
    /**
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'captain' => 'Capitán',
        'engineer' => 'Motorista',
        'sailor' => 'Marinero',
        'pilot' => 'Piloto',
        'copilot' => 'Copiloto',
    ];

    public const BOARDING_BLOCK = 'Asigna un capitán con licencia vigente';

    public static function seesContact(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->roles()->whereIn('code', ['company_admin', 'company_operator', 'super_admin', 'admin'])->exists();
    }

    public static function licenseValid(CrewMember $member, CarbonInterface $on): bool
    {
        if ($member->status !== 'active' || $member->license_expires_on === null) {
            return false;
        }

        return $member->license_expires_on->toDateString() >= $on->copy()->timezone(DepartureSchedule::ZONE)->toDateString();
    }

    public static function commandReady(RouteDeparture|AirDeparture $departure): bool
    {
        $departure->loadMissing('assignments.member');
        $command = $departure->assignments->firstWhere('duty', 'command')?->member;
        if (! $command instanceof CrewMember) {
            return false;
        }
        $expected = $departure instanceof AirDeparture ? 'pilot' : 'captain';

        return $command->role === $expected && self::licenseValid($command, $departure->departure_at);
    }

    public static function overlaps(CrewMember $member, CarbonInterface $start, int $durationMinutes, ?int $ignoreRouteId = null, ?int $ignoreAirId = null): bool
    {
        $durationMinutes = max(1, $durationMinutes);
        $end = $start->copy()->addMinutes($durationMinutes);
        $assignments = DepartureAssignment::query()
            ->where('crew_member_id', $member->id)
            ->with(['routeDeparture.transportRoute', 'airDeparture.airRoute'])
            ->get();

        foreach ($assignments as $assignment) {
            $other = $assignment->routeDeparture ?? $assignment->airDeparture;
            if ($other === null || in_array($other->status, ['cancelled', 'completed'], true)) {
                continue;
            }
            if ($other instanceof RouteDeparture && $ignoreRouteId !== null && (int) $other->id === $ignoreRouteId) {
                continue;
            }
            if ($other instanceof AirDeparture && $ignoreAirId !== null && (int) $other->id === $ignoreAirId) {
                continue;
            }
            $route = $other instanceof AirDeparture ? $other->airRoute : $other->transportRoute;
            $otherEnd = $other->departure_at->copy()->addMinutes(max(1, (int) ($route?->estimated_duration_minutes ?? 1)));
            if ($start->lt($otherEnd) && $end->gt($other->departure_at)) {
                return true;
            }
        }

        return false;
    }

    public static function guardMove(RouteDeparture|AirDeparture $departure, CarbonInterface $start): void
    {
        $departure->loadMissing($departure instanceof AirDeparture
            ? ['assignments.member', 'airRoute']
            : ['assignments.member', 'transportRoute']);
        $route = $departure instanceof AirDeparture ? $departure->airRoute : $departure->transportRoute;
        $duration = max(1, (int) ($route?->estimated_duration_minutes ?? 1));
        foreach ($departure->assignments as $assignment) {
            if (! $assignment->member) {
                continue;
            }
            if (self::overlaps(
                $assignment->member,
                $start,
                $duration,
                $departure instanceof RouteDeparture ? $departure->id : null,
                $departure instanceof AirDeparture ? $departure->id : null,
            )) {
                throw ValidationException::withMessages([
                    'departure_at' => 'Esa persona ya está en una salida que se cruza con este horario.',
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $crewIds
     */
    public static function sync(RouteDeparture|AirDeparture $departure, ?int $captainId, array $crewIds): void
    {
        $departure->loadMissing($departure instanceof AirDeparture ? 'airRoute' : 'transportRoute');
        $route = $departure instanceof AirDeparture ? $departure->airRoute : $departure->transportRoute;
        $organizationId = (int) $route->organization_id;
        $duration = max(1, (int) ($route->estimated_duration_minutes ?? 1));
        $commandRole = $departure instanceof AirDeparture ? 'pilot' : 'captain';
        $allowed = $departure instanceof AirDeparture
            ? ['pilot', 'copilot']
            : ['captain', 'engineer', 'sailor'];
        $ignoreRoute = $departure instanceof RouteDeparture ? $departure->id : null;
        $ignoreAir = $departure instanceof AirDeparture ? $departure->id : null;
        $crewIds = array_values(array_unique(array_filter(array_map('intval', $crewIds), fn (int $id): bool => $id > 0 && $id !== (int) $captainId)));

        $chosen = [];
        if ($captainId) {
            $captain = CrewMember::query()->where('organization_id', $organizationId)->find($captainId);
            if ($captain === null || $captain->role !== $commandRole) {
                throw ValidationException::withMessages(['captain_id' => 'Elige un capitán o piloto de esta empresa.']);
            }
            self::guardMember($captain, $departure->departure_at, $duration, $allowed, $ignoreRoute, $ignoreAir);
            $chosen[] = [$captain, 'command'];
        }
        foreach ($crewIds as $crewId) {
            $member = CrewMember::query()->where('organization_id', $organizationId)->find($crewId);
            if ($member === null || ! in_array($member->role, $allowed, true)) {
                throw ValidationException::withMessages(['crew_ids' => 'La tripulación no pertenece a esta salida.']);
            }
            self::guardMember($member, $departure->departure_at, $duration, $allowed, $ignoreRoute, $ignoreAir);
            $chosen[] = [$member, 'member'];
        }

        $departure->assignments()->delete();
        foreach ($chosen as [$member, $duty]) {
            DepartureAssignment::create([
                'crew_member_id' => $member->id,
                'route_departure_id' => $ignoreRoute,
                'air_departure_id' => $ignoreAir,
                'duty' => $duty,
            ]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function guardMember(CrewMember $member, CarbonInterface $start, int $duration, array $allowed, ?int $ignoreRouteId, ?int $ignoreAirId): void
    {
        if (! in_array($member->role, $allowed, true) || ! self::licenseValid($member, $start)) {
            throw ValidationException::withMessages([
                'captain_id' => 'Esa persona no tiene una licencia vigente para la fecha de la salida.',
            ]);
        }
        if (self::overlaps($member, $start, $duration, $ignoreRouteId, $ignoreAirId)) {
            throw ValidationException::withMessages([
                'captain_id' => 'Esa persona ya está en una salida que se cruza con este horario.',
            ]);
        }
    }
}
