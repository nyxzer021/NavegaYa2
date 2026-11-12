<?php

namespace App\Support;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\RouteDeparture;
use App\Models\Vessel;
use Carbon\CarbonInterface;
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

    public static function enabledSeatCapacity(): int
    {
        $today = now()->toDateString();

        $vessels = Vessel::query()
            ->where('status', 'ready')
            ->where(function ($query) use ($today) {
                $query->whereNull('maintenance_scheduled_on')->orWhereDate('maintenance_scheduled_on', '!=', $today);
            })
            ->whereDoesntHave('maintenancePeriods', function ($query) use ($today) {
                $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
            })
            ->sum('seat_capacity');

        $aircraft = Aircraft::query()
            ->where('status', 'ready')
            ->whereDoesntHave('maintenancePeriods', function ($query) use ($today) {
                $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
            })
            ->sum('seat_capacity');

        return (int) $vessels + (int) $aircraft;
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
