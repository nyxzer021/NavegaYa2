<?php

namespace App\Support;

use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Database\Eloquent\Model;

class FleetPlan
{
    public static function drawn(Model $unit): int
    {
        if ($unit instanceof Vessel) {
            return self::people($unit);
        }

        if (array_key_exists('seats_count', $unit->getAttributes())) {
            return (int) $unit->seats_count;
        }

        if ($unit->relationLoaded('seats')) {
            return $unit->seats->count();
        }

        return $unit->seats()->count();
    }

    public static function people(Vessel $vessel): int
    {
        $seats = $vessel->relationLoaded('seats') ? $vessel->seats : $vessel->seats()->get();

        return (int) $seats->sum(fn (VesselSeat $seat): int => self::occupants($seat));
    }

    public static function occupants(VesselSeat $seat): int
    {
        if ($seat->space_type === 'cabin_bed' && $seat->cabin_sold_as !== 'per_bed') {
            return (int) $seat->bed_count;
        }

        return 1;
    }

    public static function isComplete(Model $unit): bool
    {
        return self::drawn($unit) >= (int) $unit->seat_capacity;
    }

    public static function vestShortfall(Vessel $vessel): int
    {
        return max(0, (int) $vessel->seat_capacity - (int) ($vessel->life_vest_count ?? 0));
    }

    public static function vestShortfallLabel(Vessel $vessel): ?string
    {
        $short = self::vestShortfall($vessel);
        if ($short < 1) {
            return null;
        }

        return trans_choice('admin_portal.fleet.vests_missing', $short, ['count' => $short], 'es');
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
