<?php

namespace App\Support;

use App\Models\Organization;

class CompanyBase
{
    public static function label(Organization $organization): ?string
    {
        $organization->loadMissing('basePort');
        if ($organization->basePort) {
            $port = $organization->basePort;

            return $port->city ? $port->name.' · '.$port->city : $port->name;
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
