<?php

namespace App\Casts;

use App\Support\DepartureSchedule;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @implements CastsAttributes<Carbon|null, CarbonInterface|string|null>
 */
class AsLimaDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC')->timezone(DepartureSchedule::ZONE);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $moment = $value instanceof CarbonInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, 'UTC');

        return $moment->utc()->format('Y-m-d H:i:s');
    }
}
