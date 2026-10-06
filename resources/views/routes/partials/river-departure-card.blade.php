@php
    $route = $departure->transportRoute;
    $duration = (int) ($route->estimated_duration_minutes ?: 0);
    $arrival = $duration ? $departure->departure_at->copy()->addMinutes($duration) : $departure->estimated_arrival_at;
    $hours = intdiv($duration, 60);
    $minutes = $duration % 60;
    $durationLabel = $duration ? ($hours ? $hours.'h ' : '').($minutes ? $minutes.'m' : '') : 'Por confirmar';
    $reserved = $departure->reservations->whereIn('status', ['pending','confirmed'])->sum(fn($reservation) => $reservation->seats->count());
    $available = max(0, (int) $departure->vessel->seat_capacity - $reserved);
    $operator = $departure->vessel->organization->commercial_name ?: $departure->vessel->organization->legal_name;
    $vesselType = str($departure->vessel->vessel_type)->lower()->contains('ferry') ? '⛴️ Ferry' : '🚤 Rápida';
@endphp

@once
<style>
    .ny-departure-body { display: block; }
    .ny-departure-fare { margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 1rem; }
    @media (min-width: 640px) {
        .ny-departure-body { display: grid; grid-template-columns: minmax(0, 1fr) 230px; align-items: center; gap: 1.25rem; }
        .ny-departure-fare { margin-top: 0; border-top: 0; border-left: 1px solid #f1f5f9; padding-top: 0; padding-left: 1.25rem; }
    }
</style>
@endonce

<article class="mb-4 rounded-3xl border border-slate-200/90 bg-white p-4 shadow-sm transition-all duration-200 hover:border-emerald-600/70 hover:shadow-xl sm:p-5">
    <header class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-lg">🚤</span>
            <div><strong class="text-sm font-extrabold text-slate-900">{{$operator}}</strong><span class="ml-1.5 text-xs font-medium text-slate-400">· {{$departure->vessel->name}}</span></div>
            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">✓ Verificado</span>
            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800">{{$vesselType}}</span>
        </div>
        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">Salida confirmada</span>
    </header>

    <div class="ny-departure-body">
        <div class="space-y-3">
            <div class="grid grid-cols-3 items-center gap-2 sm:gap-4">
                <div><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{$route->originPort->city}}</span><time class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{{$departure->departure_at->format('H:i')}}</time><span class="mt-0.5 block text-[10px] text-slate-500 sm:text-xs">📍 {{$route->originPort->name}}</span></div>
                <div class="flex min-w-0 flex-col items-center px-1 sm:px-4"><span class="mb-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-0.5 text-[10px] font-extrabold text-emerald-900 sm:text-[11px]">{{$durationLabel}}</span><div class="flex w-full items-center"><span class="h-2 w-2 shrink-0 rounded-full bg-emerald-600"></span><span class="flex-1 border-t-2 border-dashed border-emerald-300"></span><span class="px-1 text-xs font-bold text-emerald-600">➔</span><span class="flex-1 border-t-2 border-dashed border-emerald-300"></span><span class="h-2 w-2 shrink-0 rounded-full bg-emerald-600"></span></div><span class="mt-1 text-[9px] font-medium text-slate-400 sm:text-[10px]">Directo fluvial</span></div>
                <div class="text-right"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{$route->destinationPort->city}}</span><time class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{{$arrival?->format('H:i') ?: '—'}}</time><span class="mt-0.5 block text-[10px] text-slate-500 sm:text-xs">{{$route->destinationPort->name}}</span></div>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-[11px]"><span class="rounded-xl bg-slate-100 px-2.5 py-1 font-medium text-slate-700">💺 <strong>{{$available}}</strong> asientos libres</span><span class="rounded-xl bg-slate-100 px-2.5 py-1 font-medium text-slate-700">🧳 <strong>{{$departure->included_baggage_kg ?: 15}} kg</strong> incluidos</span><span class="rounded-xl bg-slate-100 px-2.5 py-1 font-medium text-slate-700">🪪 Abordaje con DNI</span></div>
        </div>

        <aside class="ny-departure-fare flex flex-col items-center justify-center text-center sm:items-end sm:text-right"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Precio final por asiento</span><strong class="mt-0.5 text-2xl font-black text-slate-900">S/ {{number_format($departure->fare,2)}}</strong><span class="mb-2 text-[10px] font-semibold text-emerald-700">Sin cargos sorpresa</span><a href="{{route('bookings.create',$departure)}}" class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 text-xs font-black text-slate-950 shadow-md transition hover:bg-amber-600 active:scale-95 sm:w-auto"><span>Seleccionar asiento</span><span>→</span></a></aside>
    </div>
</article>
