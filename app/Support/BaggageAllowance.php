<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\SystemSetting;

class BaggageAllowance
{
    /**
     * @return list<string>
     */
    public static function lines(Organization $organization): array
    {
        $amounts = self::for($organization);
        $modality = $organization->modality ?: 'fluvial';
        $lines = [];
        if (in_array($modality, ['fluvial', 'mixto'], true)) {
            $lines[] = trans('public.baggage_river', [
                'hand' => $amounts['hand'],
                'hold' => $amounts['hold'],
                'cargo' => $amounts['cargo'],
            ], 'es');
        }
        if (in_array($modality, ['aereo', 'mixto'], true)) {
            $lines[] = trans('public.baggage_air', ['air' => $amounts['air']], 'es');
        }

        return $lines;
    }

    /**
     * @return array{hand: int, hold: int, air: int, cargo: int}
     */
    public static function for(Organization $organization): array
    {
        return [
            'hand' => self::value($organization->baggage_hand_kg, 'baggage_hand_kg', 10),
            'hold' => self::value($organization->baggage_hold_kg, 'baggage_hold_kg', 20),
            'air' => self::value($organization->baggage_air_kg, 'baggage_allowance_air_kg', 10),
            'cargo' => self::value($organization->cargo_threshold_kg, 'parcel_weight_threshold_kg', 50),
        ];
    }

    private static function value(mixed $override, string $setting, int $fallback): int
    {
        if ($override !== null && $override !== '') {
            return (int) $override;
        }

        return (int) (SystemSetting::value($setting, (string) $fallback) ?? $fallback);
    }
}
