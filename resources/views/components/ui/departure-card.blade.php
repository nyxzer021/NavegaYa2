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
    $when = $departure->departure_at->copy()->timezone('America/Lima');
    $days = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    if (app()->getLocale() === 'en') {
        $days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    }
    $adultFare = \App\Support\FareTable::price($route, 'seat', 'adult', $departure->departure_at, (float) $departure->fare);
    $pills = [];
    if ($adultFare !== null) {
        $pills[] = __('public.card_seat').' S/ '.number_format(\App\Support\DepartureSchedule::webTotal($adultFare, $organization), 2);
    }
    if (! $isAir) {
        $kinds = $vehicle->relationLoaded('seats') ? $vehicle->seats->pluck('space_type') : $vehicle->seats()->distinct()->pluck('space_type');
        if ($kinds->contains('hammock')) {
            $hammockFare = \App\Support\FareTable::price($route, 'hammock', 'adult', $departure->departure_at, null);
            if ($hammockFare !== null) {
                $pills[] = __('public.card_hammock').' S/ '.number_format(\App\Support\DepartureSchedule::webTotal($hammockFare, $organization), 2);
            }
        }
        if ($kinds->contains('cabin_bed')) {
            $cabinFare = \App\Support\FareTable::price($route, 'cabin_bed', 'adult', $departure->departure_at, null);
            if ($cabinFare !== null) {
                $pills[] = __('public.card_cabin').' S/ '.number_format(\App\Support\DepartureSchedule::webTotal($cabinFare, $organization), 2);
            }
        }
    }
    $reserved = $departure->relationLoaded('reservations')
        ? $departure->reservations->whereIn('status', ['pending', 'confirmed'])->sum(fn ($reservation) => $reservation->seats->count())
        : $departure->reservations()->whereIn('status', ['pending', 'confirmed'])->withCount('seats')->get()->sum('seats_count');
    $available = max(0, (int) $vehicle->seat_capacity - $reserved);
    $capacity = max(1, (int) $vehicle->seat_capacity);
    $low = $available > 0 && ($available / $capacity) <= 0.3;
    $rescheduled = in_array($departure->status, ['rescheduled', 'delayed'], true);
    $kind = $isAir ? 'plane' : match (mb_strtolower((string) $vehicle->vessel_type)) {
        'rapido', 'rápido', 'rapida' => 'fast',
        'lancha' => 'boat',
        'ferry', 'motonave' => 'mixed',
        default => 'other',
    };
    $stopCount = $route->relationLoaded('stops') ? $route->stops->count() : $route->stops()->count();
    $meta = collect([
        $origin.' → '.$destination,
        $duration > 0 ? \App\Models\MasterRoute::formatDuration($duration) : null,
        $stopCount > 0 ? trans_choice('public.card_stops', $stopCount, ['count' => $stopCount]) : null,
    ])->filter()->implode(' · ');
    $stopsLabel = $stopCount > 0 ? \App\Support\RouteStops::label($route) : null;
    $bags = \App\Support\BaggageAllowance::for($organization);
    $baggageTip = $isAir
        ? __('public.card_baggage_air', ['air' => $bags['air']])
        : __('public.card_baggage_river', ['hand' => $bags['hand'], 'hold' => $bags['hold']]);
    $name = $organization->commercial_name ?: $organization->legal_name;
    $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
    $initials = mb_strtoupper(mb_substr($parts[0] ?? 'N', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    $bookingUrl = $isAir ? route('air-bookings.create', $departure) : route('bookings.create', $departure);
    $canBook = $available >= (int) $passengers;
@endphp
<article class="ny-trip">
    <div class="ny-trip-date">
        <span>{{ $days[$when->dayOfWeek] }}</span>
        <strong>{{ $when->format('j') }}</strong>
        <span>{{ $months[$when->month - 1] }}</span>
    </div>
    <div class="ny-trip-main">
    <div class="ny-trip-who">
        @if($organization->logo_path)
            <img class="ny-trip-logo" src="{{ $organization->logo_path }}" alt="">
        @else
            <span class="ny-trip-logo" aria-hidden="true">{{ $initials }}</span>
        @endif
        <div class="min-w-0">
            <strong class="ny-trip-vessel">{{ $vehicle->name }}</strong>
            <span class="ny-trip-kind">{{ __('public.card_kind_'.$kind) }}</span>
            @if($rescheduled)<span class="ny-trip-flag">{{ __('public.card_rescheduled') }}</span>@endif
            @if($low)<span class="ny-trip-flag">{{ __('public.card_last') }}</span>@endif
        </div>
    </div>
    <div class="ny-trip-when">
        <div class="ny-trip-clocks">
            <time>{{ $when->format('H:i') }}</time>
            @if($arrival)<span aria-hidden="true"></span><time>{{ $arrival->copy()->timezone('America/Lima')->format('H:i') }}</time>@endif
        </div>
        <p @if($stopsLabel) title="{{ $stopsLabel }}" @endif>{{ $meta }}</p>
    </div>
    </div>
    <div class="ny-trip-buy">
    <div class="ny-trip-offer">
        @if($available > 0)<strong class="{{ $low ? 'is-low' : '' }}">{{ __('public.card_free', ['count' => $available]) }}</strong>@endif
        @if($pills !== [])<p class="ny-trip-pills">@foreach($pills as $pill)<span>{{ $pill }}</span>@endforeach</p>@endif
    </div>
    <div class="ny-trip-actions">
        <a class="ny-trip-icon is-bag" href="{{ route('companies.show', $organization) }}?politica=1" data-open-policy title="{{ $baggageTip }}" aria-label="{{ $baggageTip }}">
            <x-ui.icon name="luggage" />
        </a>
        <x-company-whatsapp :company="$organization" :departure="$departure" icon />
        @if($canBook)
            <a class="ny-trip-go" href="{{ $bookingUrl }}">{{ __('public.card_choose') }}</a>
        @else
            <span class="ny-trip-gone">{{ __('public.card_sold_out') }}</span>
        @endif
    </div>
    </div>
</article>
@endif
