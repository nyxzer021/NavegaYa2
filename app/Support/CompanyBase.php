<?php

namespace App\Support;

use App\Models\Organization;

class CompanyBase
{
    public static function label(Organization $organization): ?string
    {
        $organization->loadMissing(['vessels.basePort']);
        $ports = $organization->vessels
            ->map(fn ($vessel) => $vessel->basePort)
            ->filter()
            ->unique('id')
            ->values();

        if ($ports->count() === 1) {
            $port = $ports->first();

            return $port->city ? $port->name.' · '.$port->city : $port->name;
        }

        if ($ports->count() > 1) {
            return null;
        }

        $city = trim((string) $organization->base_city);

        return $city !== '' ? $city : null;
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

    private static function countLabel(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }
}
