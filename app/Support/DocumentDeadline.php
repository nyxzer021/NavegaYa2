<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DocumentDeadline
{
    /**
     * @return array{tone: string, text: string}|null
     */
    public static function badge(?CarbonInterface $date): ?array
    {
        if ($date === null) {
            return null;
        }

        $day = $date->copy()->startOfDay();
        if ($day->lt(today())) {
            return ['tone' => 'rose', 'text' => __('admin_portal.fleet.expired', [], 'es')];
        }

        $days = (int) today()->diffInDays($day);
        if ($days > 30) {
            return null;
        }

        if ($days === 0) {
            return ['tone' => 'amber', 'text' => __('admin_portal.fleet.expires_today', [], 'es')];
        }

        if ($days === 1) {
            return ['tone' => 'amber', 'text' => __('admin_portal.fleet.expires_in_one', [], 'es')];
        }

        return ['tone' => 'amber', 'text' => __('admin_portal.fleet.expires_in', ['days' => $days], 'es')];
    }

    public static function isExpired(?CarbonInterface $date): bool
    {
        return $date !== null && $date->copy()->startOfDay()->lt(today());
    }
}
