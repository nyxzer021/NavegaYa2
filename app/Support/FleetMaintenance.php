<?php

namespace App\Support;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\RouteDeparture;
use App\Models\Vessel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FleetMaintenance
{
    /**
     * @return Collection<int, RouteDeparture|AirDeparture>
     */
    public static function overlappingDepartures(Model $unit, string $startsOn, string $endsOn): Collection
    {
        $query = $unit instanceof Vessel
            ? $unit->departures()->with('transportRoute.originPort', 'transportRoute.destinationPort')
            : $unit->airDepartures()->with('airRoute');

        return $query
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->whereDate('departure_at', '>=', $startsOn)
            ->whereDate('departure_at', '<=', $endsOn)
            ->orderBy('departure_at')
            ->get();
    }

    public static function enabledSeatCapacity(?int $organizationId = null): int
    {
        return self::schedulableCapacity(Vessel::query(), $organizationId, true)
            + self::schedulableCapacity(Aircraft::query(), $organizationId, false);
    }

    /**
     * @param  Builder<Vessel>|Builder<Aircraft>  $query
     */
    private static function schedulableCapacity(Builder $query, ?int $organizationId, bool $vessel): int
    {
        $today = now()->toDateString();
        $units = $query
            ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->where('status', 'ready')
            ->when($vessel, function (Builder $query) use ($today): void {
                $query->where(function (Builder $query) use ($today): void {
                    $query->whereNull('maintenance_scheduled_on')->orWhereDate('maintenance_scheduled_on', '!=', $today);
                });
            })
            ->whereDoesntHave('maintenancePeriods', function (Builder $query) use ($today): void {
                $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
            })
            ->with('seats')
            ->get();

        return (int) $units->filter(fn (Model $unit): bool => FleetPlan::isComplete($unit))->sum('seat_capacity');
    }

    public static function blockedToday(object $unit): bool
    {
        $maintenance = $unit->maintenance_scheduled_on ?? null;
        if ($maintenance instanceof CarbonInterface && $maintenance->isSameDay(now())) {
            return true;
        }

        if (! method_exists($unit, 'maintenancePeriods')) {
            return false;
        }

        return $unit->maintenancePeriods()
            ->whereDate('starts_on', '<=', now()->toDateString())
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->exists();
    }
}
