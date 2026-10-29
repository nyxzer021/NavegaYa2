@extends('layouts.admin')

@section('title', 'Inventario e Itinerarios · NavegaYA')
@section('header')
    <div>
        <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">Supervisión operativa</p>
        <h1 class="ny-page-title">Supervisión de itinerarios</h1>
    </div>
@endsection
@section('content')
@php
    $activeTab = in_array(request('tab'), ['resumen', 'fluvial', 'aereo'], true) ? request('tab') : 'resumen';
@endphp
<x-admin.module-workspace
    eyebrow="Supervisión operativa"
    title="Supervisión de itinerarios"
    description="Controla salidas, capacidad publicada, ocupación y disponibilidad del marketplace."
    :show-header="false"
>
    <x-slot:navigation>
        <a href="{{ route('admin.itineraries.index', ['tab' => 'resumen']) }}" class="whitespace-nowrap rounded-lg px-4 py-2.5 text-xs font-black transition {{ $activeTab === 'resumen' ? 'bg-white text-[#062c21] shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">Resumen</a>
        <a href="{{ route('admin.itineraries.index', ['tab' => 'fluvial']) }}" class="whitespace-nowrap rounded-lg px-4 py-2.5 text-xs font-black transition {{ $activeTab === 'fluvial' ? 'bg-white text-[#062c21] shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">🚤 Fluvial <span class="ml-1 text-[10px] text-slate-400">{{ $fluvialOperators->count() }}</span></a>
        <a href="{{ route('admin.itineraries.index', ['tab' => 'aereo']) }}" class="whitespace-nowrap rounded-lg px-4 py-2.5 text-xs font-black transition {{ $activeTab === 'aereo' ? 'bg-white text-[#062c21] shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">✈️ Aéreo <span class="ml-1 text-[10px] text-slate-400">{{ $airOperators->count() }}</span></a>
        <span class="ml-auto hidden items-center rounded-lg bg-emerald-50 px-3 text-[10px] font-black text-emerald-800 sm:inline-flex">{{ $operators->where('published_departures_count', '>', 0)->count() }} operadores con salidas</span>
    </x-slot:navigation>

    @if(session('success'))<div class="mx-5 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">✓ {{ session('success') }}</div>@endif

    <section class="grid gap-3 border-b border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2 xl:grid-cols-5 lg:px-6" aria-label="Resultado operativo de la fecha">
        @foreach([
            ['Programadas', $scheduledDeparturesCount, 'Salidas registradas', 'text-slate-900', 'bg-slate-100', '▦'],
            ['En curso o completadas', $departedDeparturesCount, 'Operación iniciada o finalizada', 'text-emerald-700', 'bg-emerald-50', '✓'],
            ['Por iniciar', $pendingDeparturesCount, 'Programadas o en embarque', 'text-sky-700', 'bg-sky-50', '◷'],
            ['Canceladas', $cancelledDeparturesCount, 'No realizaron la salida', 'text-rose-700', 'bg-rose-50', '×'],
            ['Reprogramadas', $rescheduledDeparturesCount, 'Cambio de fecha u hora', 'text-amber-700', 'bg-amber-50', '↻'],
        ] as $operation)
            <article class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $operation[4] }} text-sm font-black {{ $operation[3] }}">{{ $operation[5] }}</span>
                <div class="min-w-0"><div class="flex items-baseline gap-2"><strong class="text-xl font-black {{ $operation[3] }}">{{ number_format($operation[1]) }}</strong><span class="truncate text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $operation[0] }}</span></div><p class="truncate text-[9px] text-slate-400">{{ $operation[2] }}</p></div>
            </article>
        @endforeach
    </section>

    <div class="border-b border-slate-200 bg-white p-4 lg:px-6">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div><p class="text-[9px] font-black uppercase tracking-[.16em] text-emerald-700">Consulta operativa</p><h3 class="mt-0.5 text-sm font-black text-slate-950">Encuentra una empresa o revisa una fecha</h3></div>
            <span class="text-[10px] font-semibold text-slate-400">Actualizado {{ now()->translatedFormat('d M Y · H:i') }}</span>
        </div>
        <form method="GET" class="grid items-end gap-2 md:grid-cols-2 xl:grid-cols-[minmax(230px,1.5fr)_150px_170px_190px_auto_auto]">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <label class="block min-w-0"><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-400">Empresa o RUC</span><span class="relative block"><span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-slate-400">⌕</span><input name="search" value="{{ request('search') }}" class="h-10 rounded-xl border-slate-200 bg-slate-50 pl-9 pr-3 text-xs" placeholder="Ej. Selva Fast o 20600513959"></span></label>
            <label class="block"><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-400">Fecha de salida</span><input type="date" name="date" value="{{ request('date', $selectedDate->toDateString()) }}" class="h-10 rounded-xl border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700"></label>
            <label class="block"><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-400">Modalidad</span><select name="modality" class="h-10 rounded-xl border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700"><option value="">Todas</option><option value="fluvial" @selected(request('modality') === 'fluvial')>🚤 Fluvial</option><option value="aereo" @selected(request('modality') === 'aereo')>✈️ Aéreo</option><option value="mixto" @selected(request('modality') === 'mixto')>Mixto</option></select></label>
            <label class="block"><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-400">Estado comercial</span><select name="status" class="h-10 rounded-xl border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700"><option value="">Todos</option><option value="published" @selected(request('status') === 'published')>Con salidas publicadas</option><option value="without_inventory" @selected(request('status') === 'without_inventory')>Sin inventario</option><option value="paused" @selected(request('status') === 'paused')>Ventas pausadas</option></select></label>
            <button class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#062c21] px-5 text-xs font-black text-white shadow-sm hover:bg-emerald-900"><span>⌕</span> Consultar</button>
            <a href="{{ route('admin.itineraries.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 hover:bg-slate-50">Limpiar</a>
        </form>
    </div>

    @if($activeTab === 'resumen')
    <section class="min-h-[360px]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4 lg:px-6"><div><h3 class="font-black text-slate-950">Itinerarios por operador</h3><p class="text-xs text-slate-500">Salidas y capacidad publicadas para {{ $selectedDate->translatedFormat('d \d\e F \d\e Y') }}.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-600">{{ $operators->count() }} resultados</span></header>
        @include('admin.itineraries._operator-table', ['sectionOperators' => $operators, 'isAirSection' => false, 'operatorHeading' => 'Operador', 'routesHeading' => 'Destinos principales', 'departuresHeading' => 'Salidas hoy', 'emptyMessage' => 'No hay operadores para los filtros seleccionados.'])
    </section>
@endif

@if($activeTab === 'fluvial')
    <section class="min-h-[360px]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4 lg:px-6"><div><h3 class="font-black text-slate-950">Operación fluvial</h3><p class="text-xs text-slate-500">Rutas amazónicas, salidas, capacidad publicada y ventas por empresa.</p></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black text-emerald-800">{{ $fluvialOperators->count() }} operadores</span></header>
        @include('admin.itineraries._operator-table', ['sectionOperators' => $fluvialOperators, 'isAirSection' => false, 'operatorHeading' => 'Operador fluvial', 'routesHeading' => 'Rutas principales', 'departuresHeading' => 'Salidas hoy', 'emptyMessage' => 'No hay operadores fluviales para los filtros seleccionados.'])
    </section>
@endif

@if($activeTab === 'aereo')
    <section class="min-h-[360px]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4 lg:px-6"><div><h3 class="font-black text-slate-950">Operación aérea</h3><p class="text-xs text-slate-500">Conexiones regionales, vuelos, capacidad publicada y ventas por empresa.</p></div><span class="rounded-full bg-sky-50 px-3 py-1 text-[10px] font-black text-sky-800">{{ $airOperators->count() }} operadores</span></header>
        @include('admin.itineraries._operator-table', ['sectionOperators' => $airOperators, 'isAirSection' => true, 'operatorHeading' => 'Operador aéreo', 'routesHeading' => 'Conexiones aéreas', 'departuresHeading' => 'Vuelos hoy', 'emptyMessage' => 'No hay operadores aéreos para los filtros seleccionados.'])
    </section>
@endif
</x-admin.module-workspace>
@endsection
