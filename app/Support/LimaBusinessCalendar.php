<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Plazo del Libro de Reclamaciones en días hábiles de Lima.
 * La lista de feriados está en un solo lugar para actualizarla cuando el gobierno publique el calendario.
 */
class LimaBusinessCalendar
{
    public const RESPONSE_BUSINESS_DAYS = 15;

    /**
     * Feriados nacionales. No incluye feriados solo regionales.
     *
     * @var list<string>
     */
    public const HOLIDAYS = [
        '2026-01-01',
        '2026-04-02',
        '2026-04-03',
        '2026-05-01',
        '2026-06-29',
        '2026-07-28',
        '2026-07-29',
        '2026-08-30',
        '2026-10-08',
        '2026-11-01',
        '2026-12-08',
        '2026-12-09',
        '2026-12-25',
        '2027-01-01',
        '2027-03-25',
        '2027-03-26',
        '2027-05-01',
        '2027-06-29',
        '2027-07-28',
        '2027-07-29',
        '2027-08-30',
        '2027-10-08',
        '2027-11-01',
        '2027-12-08',
        '2027-12-09',
        '2027-12-25',
    ];

    public function dueOn(CarbonInterface $filedOn): Carbon
    {
        $day = Carbon::parse($filedOn->toDateString(), DepartureSchedule::ZONE)->startOfDay();
        $left = self::RESPONSE_BUSINESS_DAYS;
        while ($left > 0) {
            $day->addDay();
            if ($this->isBusinessDay($day)) {
                $left--;
            }
        }

        return $day;
    }

    public function isBusinessDay(CarbonInterface $day): bool
    {
        $local = Carbon::parse($day->toDateString(), DepartureSchedule::ZONE);
        if ($local->isWeekend()) {
            return false;
        }

        return ! in_array($local->toDateString(), self::HOLIDAYS, true);
    }

    /**
     * @return array{days: int, overdue: bool, tone: string}
     */
    public function countdown(string $dueOn, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? now())->copy()->timezone(DepartureSchedule::ZONE)->startOfDay();
        $due = Carbon::parse($dueOn, DepartureSchedule::ZONE)->startOfDay();
        if ($today->greaterThan($due)) {
            return ['days' => 0, 'overdue' => true, 'tone' => 'coral'];
        }

        $days = 0;
        $cursor = $today->copy();
        while ($cursor->lessThan($due)) {
            $cursor->addDay();
            if ($this->isBusinessDay($cursor)) {
                $days++;
            }
        }

        return [
            'days' => $days,
            'overdue' => false,
            'tone' => $days <= 5 ? 'sun' : 'leaf',
        ];
    }
}
