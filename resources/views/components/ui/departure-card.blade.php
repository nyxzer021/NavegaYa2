@props(['departure', 'passengers' => 1])
@php
    $isAir = $departure instanceof \App\Models\AirDeparture;
    $route = $isAir ? $departure->airRoute : $departure->transportRoute;
    $vehicle = $isAir ? $departure->aircraft : $departure->vessel;
    $organization = $vehicle?->organization;
@endphp
@if($route && $vehicle && $organization && $departure->departure_at)
@php
    $origin = $isAir ? ($route->origin_city ?: '—') : ($route->originPort?->city ?: '—');
    $destination = $isAir ? ($route->destination_city ?: '—') : ($route->destinationPort?->city ?: '—');
    $duration = (int) ($route->estimated_duration_minutes ?: 0);
    $arrival = $duration ? $departure->departure_at->copy()->addMinutes($duration) : $departure->estimated_arrival_at;
    $adultFare = \App\Support\FareTable::price($route, 'seat', 'adult', $departure->departure_at, (float) $departure->fare);
    $webPrice = \App\Support\DepartureSchedule::webTotal((float) $adultFare, $organization);
    $classNotes = [];
    if (! $isAir) {
        $kinds = $vehicle->relationLoaded('seats') ? $vehicle->seats->pluck('space_type') : $vehicle->seats()->distinct()->pluck('space_type');
        if ($kinds->contains('hammock')) {
            $hammockFare = \App\Support\FareTable::price($route, 'hammock', 'adult', $departure->departure_at, null);
            $classNotes[] = $hammockFare === null ? 'Hamaca: No disponible' : 'Hamaca: S/ '.number_format(\App\Support\DepartureSchedule::webTotal($hammockFare, $organization), 2);
        }
        if ($kinds->contains('cabin_bed')) {
            $cabinFare = \App\Support\FareTable::price($route, 'cabin_bed', 'adult', $departure->departure_at, null);
            $classNotes[] = $cabinFare === null ? 'Camarote: No disponible' : 'Camarote: S/ '.number_format(\App\Support\DepartureSchedule::webTotal($cabinFare, $organization), 2);
        }
    }
    $reserved = $departure->reservations->whereIn('status', ['pending', 'confirmed'])->sum(fn ($reservation) => $reservation->seats->count());
    $available = max(0, (int) $vehicle->seat_capacity - $reserved);
    $capacity = max(1, (int) $vehicle->seat_capacity);
    $low = $available > 0 && ($available / $capacity) <= 0.3;
    $bookingUrl = $isAir ? route('air-bookings.create', $departure) : route('bookings.create', $departure);
    $policyUrl = route('companies.show', $organization).'?politica=1';
    $weekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $when = $departure->departure_at->copy()->timezone('America/Lima');
    $weekday = $weekdays[$when->dayOfWeek].' '.$when->format('j').' '.$months[$when->month - 1];
    $dateLabel = $when->isToday() ? 'Hoy, '.$weekday : ($when->isTomorrow() ? 'Mañana, '.$weekday : ucfirst($weekday));
    $hours = intdiv($duration, 60);
    $minutes = $duration % 60;
    $durationLabel = $duration > 0 ? trim(($hours ? $hours.' h' : '').($minutes ? ' '.$minutes.' min' : '')) : '';
    $dicapi = ! $isAir && filled($vehicle->dicapi_certificate_number ?? null) && $vehicle->inspection_expires_at && ! $vehicle->inspection_expires_at->isPast();
    $freeTone = $low ? 'is-few' : 'is-many';
    $stopsLabel = \App\Support\RouteStops::label($route);
@endphp
<article class="ny-departure {{ $when->isToday() ? 'is-today' : 'is-later' }}">
    <div class="ny-departure-date">
        <span>{{ $dateLabel }}</span>
        @if($low)<span class="ny-departure-last">Últimos lugares</span>@endif
    </div>
    <div class="ny-departure-main">
        <div class="ny-departure-track">
            <div class="ny-departure-times">
                <time>{{ $when->format('H:i') }}</time>
                <span class="ny-departure-line" aria-hidden="true"><i></i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 15h18M5 15l2-6h10l2 6M8 9V6h8v3"/></svg><i></i></span>
                <time>{{ $arrival ? $arrival->copy()->timezone('America/Lima')->format('H:i') : '—' }}</time>
            </div>
            <div class="ny-departure-cities">
                <span>{{ $origin }}</span>
                <span>{{ $durationLabel }}</span>
                <span>{{ $destination }}</span>
            </div>
        </div>
        <span class="ny-fare is-seat"><small>Asiento</small><strong>S/ {{ number_format($webPrice, 2) }}</strong></span>
    </div>
    <div class="ny-departure-foot">
        <p>{{ $vehicle->name }}@if($dicapi) · DICAPI vigente @endif @if($available > 0) · <b class="{{ $freeTone }}">{{ $available }} libres</b>@endif</p>
        <div class="ny-departure-actions">
            <a href="{{ $policyUrl }}" data-open-policy>Equipaje y condiciones</a>
            @if($available >= (int) $passengers)
                <x-company-whatsapp :company="$organization" :departure="$departure" compact secondary />
                <a href="{{ $bookingUrl }}" class="ny-departure-go">Elegir lugar</a>
            @else
                <span class="ny-departure-gone">Agotado</span>
            @endif
        </div>
    </div>
    @if($classNotes !== [])<p class="ny-departure-note">{{ implode(' · ', $classNotes) }}</p>@endif
    @if($stopsLabel)<p class="ny-departure-note">{{ $stopsLabel }}</p>@endif
    <p class="ny-departure-note">La salida puede demorar 1 o 2 horas respecto a la hora citada.</p>
</article>
<style>
    .ny-departure{--ny-green:#064E3B;--ny-green-2:#085041;--ny-gold:#F4B820;--ny-ink:#10231D;margin-bottom:.75rem;overflow:hidden;border:1px solid #e4ebe7;border-radius:12px;background:#fff;color:var(--ny-ink);font-size:13px}
    .ny-departure-date{display:flex;align-items:center;justify-content:space-between;gap:.5rem;height:30px;padding:0 .75rem;background:var(--ny-green);color:#fff;font-weight:700}
    .ny-departure.is-later .ny-departure-date{background:var(--ny-green-2)}
    .ny-departure-last{border-radius:999px;background:var(--ny-gold);padding:0 .45rem;color:#412402;font-size:12px;line-height:20px}
    .ny-departure-main{display:flex;align-items:flex-start;gap:.5rem;padding:.35rem .75rem .15rem}
    .ny-departure-track{flex:1;min-width:0;display:grid;grid-template-columns:4.5rem minmax(0,1fr) 4.5rem;align-items:center;column-gap:.5rem;row-gap:.15rem}
    .ny-departure-times,.ny-departure-cities{display:contents}
    .ny-departure-times time{font-size:22px;font-weight:500;line-height:1}
    .ny-departure-times time:last-of-type,.ny-departure-cities span:last-child{text-align:right}
    .ny-departure-line{display:flex;align-items:center;gap:.35rem;min-width:0;color:#607069}
    .ny-departure-line i{height:2px;flex:1;min-width:0;background:#8aa399}
    .ny-departure-line svg{width:16px;height:16px;flex:none}
    .ny-fare{display:flex;flex-direction:column;align-items:flex-end;flex:none;border-radius:8px;padding:.2rem .45rem;line-height:1.1}
    .ny-fare small{font-size:12.5px;font-weight:700}
    .ny-fare strong{font-size:16px;font-weight:500}
    .ny-fare.is-seat{background:#E1F5EE;color:#04342C}
    .ny-departure-cities span{color:#10231D;font-size:13px}
    .ny-departure-cities span:nth-child(2){text-align:center;color:#607069;font-size:12.5px}
    .ny-departure-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;padding:.35rem .75rem .6rem}
    .ny-departure-foot p{min-width:0;font-weight:700}
    .ny-departure-foot .is-many{color:#0F6E56}
    .ny-departure-foot .is-few{color:#854F0B}
    .ny-departure-actions{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem}
    .ny-departure-actions a[data-open-policy]{font-weight:700;color:var(--ny-green);text-decoration:underline}
    .ny-departure-actions .ny-wa-secondary{display:inline-flex;height:34px;align-items:center;justify-content:center;border:1px solid #d5e0db;border-radius:8px;background:#fff;padding:0 .75rem;font-size:13px;font-weight:800;color:var(--ny-green);text-decoration:none}
    .ny-departure-go{display:inline-flex;height:34px;align-items:center;border-radius:8px;background:var(--ny-green);padding:0 .8rem;font-weight:800;color:#fff}
    .ny-departure-gone{display:inline-flex;height:34px;align-items:center;border-radius:8px;background:#f1f5f4;padding:0 .8rem;font-weight:800;color:#607069}
    .ny-departure-note{padding:0 .75rem .6rem;font-weight:600;color:#92400e}
    @media (max-width:639px){
        .ny-departure-main{flex-direction:column}
        .ny-departure-track{width:100%;grid-template-columns:4.2rem minmax(0,1fr) 4.2rem}
        .ny-fare{align-items:flex-start;width:100%}
        .ny-departure-go,.ny-departure-actions .ny-wa-secondary{height:38px;width:100%;justify-content:center}
        .ny-departure-actions{width:100%}
    }
</style>
@endif
