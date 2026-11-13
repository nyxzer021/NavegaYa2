<?php

namespace App\Support;

use App\Mail\DepartureNoticeMail;
use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\DepartureNotice;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\RouteDeparture;
use App\Models\Vessel;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class DepartureSchedule
{
    public const ZONE = 'America/Lima';

    public static function parse(string $dateTime): Carbon
    {
        return Carbon::parse($dateTime, self::ZONE)->utc();
    }

    public static function at(string $date, string $time): Carbon
    {
        return self::parse($date.' '.$time);
    }

    public static function label(CarbonInterface $moment): string
    {
        return self::show($moment, 'd/m/Y H:i');
    }

    public static function show(?CarbonInterface $moment, string $format = 'd/m/Y · H:i'): string
    {
        if ($moment === null) {
            return '';
        }

        return $moment->copy()->timezone(self::ZONE)->locale('es')->translatedFormat($format);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayBounds(?CarbonInterface $moment = null): array
    {
        $day = ($moment ?? now())->copy()->timezone(self::ZONE);

        return [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()];
    }

    public static function onThisLimaDay(object $query, string $column = 'departure_at'): object
    {
        [$start, $end] = self::dayBounds();

        return $query->whereBetween($column, [$start, $end]);
    }

    public static function webTotal(float $fare, ?Organization $organization): float
    {
        $base = round($fare, 2);
        $percent = round((float) ($organization?->commission_rate ?? 0), 2);
        $fee = round($base * $percent / 100, 2);

        return round($base + $fee, 2);
    }

    /**
     * @param  list<int>  $weekdays
     * @return list<Carbon>
     */
    public static function dates(string $startsOn, string $endsOn, array $weekdays, string $time): array
    {
        $cursor = Carbon::parse($startsOn, self::ZONE)->startOfDay();
        $end = Carbon::parse($endsOn, self::ZONE)->startOfDay();
        $moments = [];
        while ($cursor->lte($end)) {
            if (in_array($cursor->dayOfWeekIso, $weekdays, true)) {
                $moments[] = $cursor->copy()->setTimeFromTimeString($time)->utc();
            }
            $cursor->addDay();
        }

        return $moments;
    }

    public static function blockedOn(Model $unit, CarbonInterface $moment): bool
    {
        $day = $moment->copy()->timezone(self::ZONE)->toDateString();
        $maintenance = $unit->maintenance_scheduled_on ?? null;
        if ($maintenance instanceof CarbonInterface && $maintenance->toDateString() === $day) {
            return true;
        }
        if (! method_exists($unit, 'maintenancePeriods')) {
            return false;
        }

        return $unit->maintenancePeriods()
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->exists();
    }

    /**
     * @return Collection<int, RouteDeparture|AirDeparture>
     */
    public static function conflicts(Vessel|Aircraft $unit, CarbonInterface $start, int $durationMinutes, ?int $ignoreId = null): Collection
    {
        $durationMinutes = max(1, $durationMinutes);
        $end = $start->copy()->addMinutes($durationMinutes);
        $query = $unit instanceof Vessel ? $unit->departures() : $unit->airDepartures();
        $rows = $query
            ->with($unit instanceof Vessel ? 'transportRoute' : 'airRoute')
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('departure_at', '<', $end)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get();

        return $rows->filter(function (RouteDeparture|AirDeparture $other) use ($start, $end): bool {
            $route = $other instanceof AirDeparture ? $other->airRoute : $other->transportRoute;
            $otherEnd = $other->departure_at->copy()->addMinutes(max(1, (int) ($route?->estimated_duration_minutes ?? 1)));

            return $start->lt($otherEnd) && $end->gt($other->departure_at);
        })->values();
    }

    /**
     * @return list<string>
     */
    public static function notify(RouteDeparture|AirDeparture $departure, string $reason, string $change): array
    {
        $links = [];
        $reservations = $departure->reservations()->whereIn('status', ['pending', 'confirmed'])->get();
        foreach ($reservations as $reservation) {
            if (filled($reservation->contact_email)) {
                Mail::to($reservation->contact_email)->send(new DepartureNoticeMail($reservation, $reason, $change));
                self::notice($departure, $reservation, 'email', 'sent', $reason, $reservation->contact_email);
            }
            $phone = PhoneNumber::peru($reservation->contact_phone);
            if ($phone) {
                $text = rawurlencode('NavegaYA: '.$change.'. Motivo: '.$reason.'. Reserva '.$reservation->code);
                $links[] = 'https://wa.me/'.$phone.'?text='.$text;
                self::notice($departure, $reservation, 'whatsapp', 'prepared', $reason, $phone);
            }
        }

        return $links;
    }

    private static function notice(RouteDeparture|AirDeparture $departure, Reservation $reservation, string $channel, string $status, string $reason, ?string $recipient): void
    {
        DepartureNotice::create([
            'route_departure_id' => $departure instanceof RouteDeparture ? $departure->id : null,
            'air_departure_id' => $departure instanceof AirDeparture ? $departure->id : null,
            'reservation_id' => $reservation->id,
            'channel' => $channel,
            'status' => $status,
            'reason' => $reason,
            'recipient' => $recipient,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
