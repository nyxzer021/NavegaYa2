@props(['departure', 'passengers' => 1])
@php
    $isAir = $departure instanceof \App\Models\AirDeparture;
    $route = $isAir ? $departure->airRoute : $departure->transportRoute;
    $vehicle = $isAir ? $departure->aircraft : $departure->vessel;
    $organization = $vehicle?->organization;
@endphp
@if($route && $vehicle && $organization && $departure->departure_at)
@php
    $operator = $organization->commercial_name ?: $organization->legal_name;
    $origin = $isAir ? ($route->origin_city ?: '—') : ($route->originPort?->city ?: '—');
    $destination = $isAir ? ($route->destination_city ?: '—') : ($route->destinationPort?->city ?: '—');
    $terminal = $isAir ? 'Terminal aéreo regional' : ($route->originPort?->name ?: 'Muelle');
    $duration = (int) ($route->estimated_duration_minutes ?: 0);
    $arrival = $duration ? $departure->departure_at->copy()->addMinutes($duration) : $departure->estimated_arrival_at;
    $fare = round((float) $departure->fare, 2);
    $percent = round((float) ($organization->commission_rate ?? 0), 2);
    $webPrice = round($fare + round($fare * $percent / 100, 2), 2);
    $reserved = $departure->reservations->whereIn('status', ['pending', 'confirmed'])->sum(fn ($reservation) => $reservation->seats->count());
    $available = max(0, (int) $vehicle->seat_capacity - $reserved);
    $capacity = max(1, (int) $vehicle->seat_capacity);
    $low = $available > 0 && ($available / $capacity) <= 0.3;
    $bookingUrl = $isAir ? route('air-bookings.create', $departure) : route('bookings.create', $departure);
@endphp
<article class="mb-3 rounded-2xl border border-slate-200 bg-white p-3 text-[13px] shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0 flex-1">
            <strong class="block truncate text-sm text-slate-900">{{ $operator }}</strong>
            <span class="mt-0.5 block text-slate-600">{{ $vehicle->name }} · {{ $origin }} → {{ $destination }}</span>
            <span class="block text-slate-500">{{ $terminal }}</span>
        </div>
        <time class="shrink-0 font-black text-slate-900">{{ $departure->departure_at->format('H:i') }}@if($arrival) – {{ $arrival->format('H:i') }}@endif</time>
    </div>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-1">
            <span class="rounded-full bg-emerald-50 px-2 py-1 font-bold text-emerald-900">Asiento · S/ {{ number_format($webPrice, 2) }}</span>
            @if($low)<span class="rounded-full bg-amber-100 px-2 py-1 font-bold text-amber-950">Últimos lugares</span>@endif
        </div>
        <div class="flex items-center gap-2">
            @if($available >= (int) $passengers)
                <x-company-whatsapp :company="$organization" :departure="$departure" compact />
                <a href="{{ $bookingUrl }}" class="inline-flex h-10 items-center rounded-xl bg-[#F4B820] px-3 font-extrabold text-[#10231D]">Elegir lugar</a>
            @else
                <span class="inline-flex h-10 items-center rounded-xl bg-slate-100 px-3 font-bold text-slate-500">Agotado</span>
            @endif
        </div>
    </div>
    <p class="mt-2 font-semibold text-amber-950">La salida puede demorar 1 o 2 horas respecto a la hora citada.</p>
</article>
@endif
