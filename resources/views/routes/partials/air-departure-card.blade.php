@php
    $duration = (int) ($flight->airRoute->estimated_duration_minutes ?: 0);
    $arrival = $flight->estimated_arrival_at ?: ($duration ? $flight->departure_at->copy()->addMinutes($duration) : null);
    $durationLabel = $duration ? (intdiv($duration, 60) ? intdiv($duration, 60).'h ' : '').($duration % 60 ? ($duration % 60).'m' : '') : 'Por confirmar';
    $reserved = $flight->reservations->whereIn('status', ['pending','confirmed'])->sum(fn($reservation) => $reservation->seats->count());
    $available = max(0, (int) $flight->aircraft->seat_capacity - $reserved);
    $operator = $flight->aircraft->organization->commercial_name ?: $flight->aircraft->organization->legal_name;
@endphp
<article class="mb-4 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-lg">
    <div class="grid gap-5 p-5 lg:grid-cols-[1fr_220px] lg:items-stretch">
        <div>
            <header class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-extrabold text-slate-900">{{$operator}}</h3><span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-sky-800">✈️ Avioneta</span><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700">✓ Operador verificado</span></header>
            <p class="mt-1 text-xs text-slate-500">{{$flight->aircraft->name}}</p>
            <div class="mt-5 grid grid-cols-[auto_1fr_auto] items-center gap-3"><div><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{$flight->airRoute->origin_city}}</span><time class="text-xl font-extrabold text-slate-950">{{$flight->departure_at->format('H:i')}}</time></div><div class="text-center"><span class="rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-sky-700">{{$durationLabel}}</span><div class="mt-2 flex items-center"><span class="h-2 w-2 rounded-full border-2 border-sky-700"></span><span class="h-px flex-1 bg-slate-300"></span><span class="text-sky-700">➜</span></div></div><div class="text-right"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{$flight->airRoute->destination_city}}</span><time class="text-xl font-extrabold text-slate-950">{{$arrival?->format('H:i') ?: '—'}}</time></div></div>
            <p class="mt-4 text-xs text-slate-500">🛫 Embarque: <strong class="text-slate-700">Aeropuerto / terminal aéreo regional</strong></p>
        </div>
        <aside class="flex flex-col justify-between border-t border-slate-100 pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0"><div><span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Precio final por asiento</span><p class="mt-1 text-2xl font-extrabold text-slate-950">S/ {{number_format($flight->fare,2)}}</p></div><a href="{{route('air-bookings.create',$flight)}}" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-amber-500 px-4 py-3 text-xs font-bold text-slate-950 shadow-sm transition hover:bg-amber-600 active:scale-[.98]">Seleccionar asiento →</a></aside>
    </div>
    <footer class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3 text-[10px] font-semibold text-slate-600"><span class="rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-200">💺 {{$available}} asientos libres</span><span class="rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-200">🧳 15 kg incluidos</span><span class="rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-200">🪪 Abordaje con DNI</span></footer>
</article>
