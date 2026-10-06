@props(['departure'])
@php
    $isAir = $departure instanceof AppModelsAirDeparture;
    $route = $isAir ? $departure->airRoute : $departure->transportRoute;
    $vehicle = $isAir ? $departure->aircraft : $departure->vessel;
    $organization = $vehicle->organization;
    $operator = $organization->commercial_name ?: $organization->legal_name;
    $origin = $isAir ? $route->origin_city : $route->originPort->city;
    $destination = $isAir ? $route->destination_city : $route->destinationPort->city;
    $terminal = $isAir ? 'Terminal aéreo regional' : $route->originPort->name;
    $duration = (int) ($route->estimated_duration_minutes ?: 0);
    $arrival = $duration ? $departure->departure_at->copy()->addMinutes($duration) : $departure->estimated_arrival_at;
    $hours = intdiv($duration, 60);
    $minutes = $duration % 60;
    $durationLabel = $duration ? ($hours ? $hours.'h ' : '').($minutes ? $minutes.'m' : '') : 'Por confirmar';
    $reserved = $departure->reservations->whereIn('status', ['pending','confirmed'])->sum(fn ($reservation) => $reservation->seats->count());
    $available = max(0, (int) $vehicle->seat_capacity - $reserved);
    $luggage = $isAir ? '10 kg máx.' : (($departure->included_baggage_kg ?: 15).' kg incluidos');
    $bookingUrl = $isAir ? route('air-bookings.create', $departure) : route('bookings.create', $departure);
@endphp

<article class="group mb-3 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm transition-all duration-150 hover:border-emerald-500/60 hover:shadow-md sm:p-5">
<div class="grid items-center gap-4 lg:grid-cols-[180px_minmax(0,1fr)_180px]">
<div class="flex min-w-0 items-center gap-3 lg:self-start">
<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-base {{$isAir?'bg-sky-50 text-sky-800':'bg-emerald-50 text-emerald-800'}}">{{$isAir?'✈️':'🚤'}}</span>
<div class="min-w-0"><div class="flex items-center gap-1.5"><strong class="truncate text-xs font-extrabold text-slate-900">{{$operator}}</strong><span class="rounded-full px-1.5 py-0.5 text-[9px] font-bold {{$isAir?'bg-sky-100 text-sky-800':'bg-emerald-100 text-emerald-800'}}">{{$isAir?'Avioneta':'Rápida'}}</span></div><span class="mt-0.5 block truncate text-[10px] text-slate-400">{{$vehicle->name}}</span><span class="mt-1 inline-flex items-center gap-1 text-[9px] font-bold text-emerald-700">✓ Operador verificado</span></div>
</div>

<div class="min-w-0">
<div class="grid grid-cols-[minmax(72px,1fr)_minmax(95px,1.3fr)_minmax(72px,1fr)] items-center gap-2">
<div><time class="text-lg font-black leading-none text-slate-900">{{$departure->departure_at->format('H:i')}}</time><strong class="mt-1 block truncate text-xs text-slate-800">{{$origin}}</strong><span class="block truncate text-[9px] text-slate-400">{{$terminal}}</span></div>
<div class="text-center"><span class="rounded-full bg-slate-50 px-2 py-1 text-[9px] font-bold text-slate-500">{{$durationLabel}}</span><div class="mt-1.5 flex items-center"><i class="h-1.5 w-1.5 rounded-full {{$isAir?'bg-sky-600':'bg-emerald-600'}}"></i><i class="flex-1 border-t border-dashed {{$isAir?'border-sky-300':'border-emerald-300'}}"></i><span class="px-1 text-[10px] font-bold {{$isAir?'text-sky-600':'text-emerald-600'}}">➔</span><i class="flex-1 border-t border-dashed {{$isAir?'border-sky-300':'border-emerald-300'}}"></i><i class="h-1.5 w-1.5 rounded-full {{$isAir?'bg-sky-600':'bg-emerald-600'}}"></i></div><span class="mt-1 block text-[9px] font-semibold {{$isAir?'text-sky-700':'text-emerald-700'}}">Directo</span></div>
<div class="text-right"><time class="text-lg font-black leading-none text-slate-900">{{$arrival?->format('H:i') ?: '—'}}</time><strong class="mt-1 block truncate text-xs text-slate-800">{{$destination}}</strong><span class="block text-[9px] text-slate-400">Llegada estimada</span></div>
</div>
<div class="mt-3 flex flex-wrap gap-1.5 border-t border-slate-100 pt-2.5 text-[10px] font-semibold text-slate-600"><span class="rounded-lg bg-slate-50 px-2 py-1">💺 {{$available}} asientos libres</span><span class="rounded-lg bg-slate-50 px-2 py-1">🧳 {{$luggage}}</span><span class="rounded-lg bg-slate-50 px-2 py-1">🪪 Abordaje con DNI</span></div>
</div>

<div class="flex items-center justify-between gap-4 border-t border-slate-100 pt-3 lg:flex-col lg:items-end lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
<div class="lg:text-right"><span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-400">Desde</span><strong class="text-xl font-black leading-none text-slate-900">S/ {{number_format((float)$departure->fare,2)}}</strong><span class="mt-0.5 block text-[9px] text-slate-400">por asiento</span></div>
<a href="{{$bookingUrl}}" class="inline-flex h-9 shrink-0 items-center justify-center gap-1 rounded-xl bg-amber-500 px-4 text-xs font-bold text-slate-950 shadow-sm transition hover:bg-amber-600 active:scale-95">Ver asientos <span>→</span></a>
</div>
</div>
</article>
