<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class FleetPlan
{
    public static function drawn(Model $unit): int
    {
        if (array_key_exists('seats_count', $unit->getAttributes())) {
            return (int) $unit->seats_count;
        }

        if ($unit->relationLoaded('seats')) {
            return $unit->seats->count();
        }

        return $unit->seats()->count();
    }

    public static function isComplete(Model $unit): bool
    {
        return self::drawn($unit) >= (int) $unit->seat_capacity;
    }

    public static function missingText(Model $unit): ?string
    {
        if (self::isComplete($unit)) {
            return null;
        }

        return __('company_portal.fleet.plan_missing', [
            'drawn' => self::drawn($unit),
            'capacity' => (int) $unit->seat_capacity,
        ], 'es');
    }
}
