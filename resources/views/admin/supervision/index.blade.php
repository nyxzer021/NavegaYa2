@extends('layouts.admin')

@section('header')
    <h1 class="ny-page-title">Itinerarios del marketplace</h1>
@endsection

@section('content')
<div class="space-y-4">
    <section class="rounded-2xl bg-[#062c21] px-5 py-5 text-white shadow-sm sm:px-6">
        <span class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-300">Inventario comercial regional</span>
        <div class="mt-1 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
                <h2 class="text-xl font-black sm:text-2xl">Disponibilidad publicada en NavegaYA</h2>
                <p class="mt-1 text-xs text-emerald-100/70">Supervisa la oferta visible, las ventas y la disponibilidad de los operadores afiliados.</p>
            </div>
            <span class="w-fit rounded-full border border-emerald-400/20 bg-white/5 px-3 py-1 text-[10px] font-bold text-emerald-100">{{ $departures->total() }} salidas en inventario</span>
        </div>
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Asientos disponibles hoy</span>
            <div class="mt-1 text-xl font-black text-slate-900">{{ number_format($availableSeatsToday ?? 0) }}</div>
            <span class="text-[11px] font-semibold text-emerald-600">Publicados para venta</span>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Boletos vendidos hoy</span>
            <div class="mt-1 text-xl font-black text-slate-900">{{ number_format($ticketsSoldToday ?? 0) }}</div>
            <span class="text-[11px] text-slate-400">Reservas confirmadas</span>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Rutas con venta abierta</span>
            <div class="mt-1 text-xl font-black text-slate-900">{{ number_format($openRoutesCount ?? 0) }}</div>
            <span class="text-[11px] text-slate-400">Tramos únicos activos</span>
        </article>
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 shadow-sm">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-700">Estado del marketplace</span>
            <div class="mt-1 flex items-center gap-2 text-lg font-black text-emerald-900"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>100% Operativo</div>
            <span class="text-[11px] font-semibold text-emerald-700">Venta pública disponible</span>
        </article>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="organization_id" class="h-9 w-64 rounded-lg border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700">
                <option value="">Todas las empresas</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('organization_id') == $company->id)>{{ $company->commercial_name ?: $company->legal_name }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ request('date') }}" class="h-9 w-44 rounded-lg border-slate-200 bg-slate-50 px-3 text-xs">
            <select name="marketplace_status" class="h-9 w-40 rounded-lg border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700">
                <option value="">Todos los estados</option>
                <option value="selling" @selected(request('marketplace_status') === 'selling')>Venta abierta</option>
                <option value="paused" @selected(request('marketplace_status') === 'paused')>Pausados</option>
            </select>
            <button class="h-9 rounded-lg bg-amber-500 px-5 text-xs font-black text-slate-950 transition hover:bg-amber-600">Filtrar</button>
            <a href="{{ route('admin.itineraries.index') }}" class="inline-flex h-9 items-center px-2 text-xs font-bold text-emerald-700 hover:text-emerald-900">Limpiar</a>
            <span class="ml-auto text-[11px] font-semibold text-slate-400">Filtro: {{ $filterLabel }}</span>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1020px] text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Salida</th><th class="px-4 py-3">Modalidad</th><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Ruta / tramo</th><th class="px-4 py-3">Disponibilidad en web</th><th class="px-4 py-3">Estado marketplace</th><th class="px-4 py-3 text-right">Acción Superadmin</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($departures as $departure)
                    @php
                        $confirmed = $departure['reservations']->where('status', 'confirmed');
                        $sold = $confirmed->sum(fn ($reservation) => $reservation->seats->count());
                        $capacity = max(0, (int) ($departure['vehicle']->seat_capacity ?? 0));
                        $available = max(0, $capacity - $sold);
                        $selling = ($departure['is_published'] ?? true) && in_array($departure['status'], ['scheduled', 'boarding'], true);
                        $company = $departure['company'];
                    @endphp
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-4 py-3"><strong class="block text-sm text-slate-900">{{ $departure['departure_at']?->format('d/m/Y') }}</strong><span class="text-[10px] font-semibold text-slate-500">{{ $departure['departure_at']?->format('H:i') }} h</span></td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-bold {{ $departure['type'] === 'aereo' ? 'bg-violet-50 text-violet-700 ring-1 ring-violet-200' : 'bg-sky-50 text-sky-700 ring-1 ring-sky-200' }}">{{ $departure['type'] === 'aereo' ? '✈️ Aéreo' : '🚤 Fluvial' }}</span></td>
                        <td class="px-4 py-3"><strong class="block max-w-44 truncate text-slate-900">{{ $company->commercial_name ?? $company->legal_name ?? 'Sin operador' }}</strong><span class="text-[10px] text-slate-400">{{ $company->base_city ?? 'Loreto' }}</span></td>
                        <td class="px-4 py-3"><strong class="block text-slate-800">{{ $departure['route'] }}</strong><span class="text-[10px] text-slate-400">{{ $departure['route_code'] ?? 'Sin código' }}</span></td>
                        <td class="px-4 py-3"><strong class="block text-slate-800">{{ $sold }} vendidos / {{ $available }} disponibles</strong><span class="text-[10px] text-slate-400">Capacidad total: {{ $capacity }}</span></td>
                        <td class="px-4 py-3">@if($selling)<span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">Venta Abierta</span>@else<span class="rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">Pausado</span>@endif</td>
                        <td class="px-4 py-3 text-right">
                            @if($selling)
                                <form method="POST" action="{{ route('admin.itineraries.publication', ['departure' => $departure['id'], 'type' => $departure['type']]) }}" class="inline">@csrf @method('PATCH')<button class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-[10px] font-bold text-rose-700 hover:bg-rose-100">Pausar en Web</button></form>
                            @elseif(($departure['is_published'] ?? true) === false)
                                <form method="POST" action="{{ route('admin.itineraries.publication', ['departure' => $departure['id'], 'type' => $departure['type']]) }}" class="inline">@csrf @method('PATCH')<button class="inline-flex h-8 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100">Reactivar en Web</button></form>
                            @else
                                <a href="{{ $departure['public_url'] }}" target="_blank" class="inline-flex h-8 items-center rounded-lg border border-slate-200 px-3 text-[10px] font-bold text-slate-700 hover:bg-slate-50">Ver en Web →</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">No hay itinerarios para los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($departures->hasPages())<div class="border-t border-slate-100 p-3">{{ $departures->links() }}</div>@endif
    </section>
</div>
@endsection
