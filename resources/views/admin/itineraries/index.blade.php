@extends('layouts.admin')

@section('header')
    <h1 class="ny-page-title">Inventario comercial por operador</h1>
@endsection

@section('content')
<div class="space-y-4">
    <section class="rounded-2xl bg-[#062c21] px-6 py-5 text-white shadow-sm">
        <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
            <div>
                <span class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-300">Marketplace NavegaYA · Resumen ejecutivo diario</span>
                <h2 class="mt-1 text-2xl font-black">Capacidad y comercio por operador</h2>
                <p class="mt-1 text-xs text-emerald-100/70">Una fila por empresa afiliada con inventario, ventas y comisión consolidada de hoy.</p>
            </div>
            <span class="w-fit rounded-full border border-emerald-400/20 bg-white/5 px-3 py-1.5 text-[10px] font-bold text-emerald-100">{{ $operators->count() }} operadores activos registrados</span>
        </div>
    </section>

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Asientos publicados hoy</span>
            <div class="mt-1 text-2xl font-black text-slate-900">{{ number_format($totalPublishedSeats) }}</div>
            <span class="text-[11px] text-slate-400">Capacidad comercial total</span>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Boletos vendidos hoy</span>
            <div class="mt-1 text-2xl font-black text-slate-900">{{ number_format($totalSoldSeats) }}</div>
            <span class="text-[11px] font-semibold text-emerald-600">{{ $totalPublishedSeats > 0 ? number_format(($totalSoldSeats / $totalPublishedSeats) * 100, 1) : '0.0' }}% de ocupación</span>
        </article>
        <article class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Comisión estimada hoy</span>
            <div class="mt-1 text-2xl font-black text-amber-950">S/ {{ number_format($totalCommissionToday, 2) }}</div>
            <span class="text-[11px] text-amber-700">Comisión NavegaYA sobre ventas</span>
        </article>
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Operadores vendiendo hoy</span>
            <div class="mt-1 text-2xl font-black text-emerald-950">{{ number_format($activeOperatorsCount) }} <span class="text-sm font-bold text-emerald-700">de {{ number_format($operatorsCount) }} activos</span></div>
            <span class="text-[11px] font-semibold text-emerald-700">Con salidas publicadas hoy</span>
        </article>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input name="search" value="{{ request('search') }}" class="h-9 w-72 rounded-lg border-slate-200 bg-slate-50 px-3 text-xs" placeholder="Buscar empresa o RUC">
            <select name="modality" class="h-9 w-44 rounded-lg border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700">
                <option value="">Todas las modalidades</option>
                <option value="fluvial" @selected(request('modality') === 'fluvial')>🚤 Fluvial</option>
                <option value="aereo" @selected(request('modality') === 'aereo')>✈️ Aéreo</option>
                <option value="mixto" @selected(request('modality') === 'mixto')>🚤✈️ Mixto</option>
            </select>
            <button class="h-9 rounded-lg bg-amber-500 px-5 text-xs font-black text-slate-950 hover:bg-amber-600">Filtrar</button>
            <a href="{{ route('admin.itineraries.index') }}" class="inline-flex h-9 items-center px-2 text-xs font-bold text-emerald-700">Limpiar</a>
            <span class="ml-auto text-[11px] font-semibold text-slate-400">Corte: {{ now()->translatedFormat('d M Y · H:i') }}</span>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-emerald-200/60 bg-white shadow-sm">
        <header class="flex flex-col justify-between gap-3 px-5 py-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-xl">🚤</span><div><h3 class="font-black text-slate-900">Transporte Fluvial (Rutas Amazónicas)</h3><p class="mt-0.5 text-[11px] text-slate-500">Lanchas rápidas, deslizadores y motonaves (Iquitos, Nauta, Yurimaguas, Requena)</p></div></div>
            <span class="w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-800">{{ $fluvialOperators->count() }} empresas fluviales</span>
        </header>
        @include('admin.itineraries._operator-table', ['sectionOperators' => $fluvialOperators, 'isAirSection' => false, 'operatorHeading' => 'Operador fluvial', 'routesHeading' => 'Rutas principales', 'departuresHeading' => 'Salidas hoy', 'emptyMessage' => 'No hay operadores fluviales para los filtros seleccionados.'])
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-cyan-200/60 bg-white shadow-sm">
        <header class="flex flex-col justify-between gap-3 px-5 py-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-50 text-xl">✈️</span><div><h3 class="font-black text-slate-900">Transporte Aéreo (Aerotaxis y Vuelos Regionales)</h3><p class="mt-0.5 text-[11px] text-slate-500">Avionetas Cessna Caravan, Twin Otter e hidroaviones (Angamos, Contamana, San Lorenzo)</p></div></div>
            <span class="w-fit rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[10px] font-bold text-sky-800">{{ $airOperators->count() }} empresas aéreas</span>
        </header>
        @include('admin.itineraries._operator-table', ['sectionOperators' => $airOperators, 'isAirSection' => true, 'operatorHeading' => 'Operador aéreo', 'routesHeading' => 'Conexiones aéreas', 'departuresHeading' => 'Vuelos hoy', 'emptyMessage' => 'No hay operadores aéreos para los filtros seleccionados.'])
    </section>
</div>
@endsection
