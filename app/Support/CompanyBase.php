<?php

namespace App\Support;

use App\Models\Organization;
use Carbon\CarbonInterface;

class CompanyBase
{
    public static function label(Organization $organization): ?string
    {
        $organization->loadMissing('basePort');
        if ($organization->basePort) {
            $port = $organization->basePort;

            return $port->city ? $port->name.' · '.$port->city : $port->name;
        }

        return null;
    }

    public static function fleetPhrase(Organization $organization): string
    {
        $organization->loadMissing(['vessels', 'aircraft']);
        $units = $organization->vessels->concat($organization->aircraft);
        $registered = $units->count();
        $ready = $units->where('status', 'ready')->count();

        return self::countLabel($registered, 'registrada', 'registradas')
            .' · '
            .self::countLabel($ready, 'habilitada', 'habilitadas');
    }

    public static function sellable(object $unit): bool
    {
        if (($unit->status ?? null) !== 'ready') {
            return false;
        }

        $maintenance = $unit->maintenance_scheduled_on ?? null;

        return ! ($maintenance instanceof CarbonInterface && $maintenance->isSameDay(now()));
    }

    public static function publicFleetLabel(Organization $organization): ?string
    {
        $organization->loadMissing(['vessels', 'aircraft']);
        $parts = [];
        $boats = $organization->vessels->filter(fn ($unit) => self::sellable($unit))->count();
        $planes = $organization->aircraft->filter(fn ($unit) => self::sellable($unit))->count();
        if ($boats > 0) {
            $parts[] = trans_choice('public.fleet_boats', $boats, ['count' => $boats], 'es');
        }
        if ($planes > 0) {
            $parts[] = trans_choice('public.fleet_planes', $planes, ['count' => $planes], 'es');
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function countLabel(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }
}
