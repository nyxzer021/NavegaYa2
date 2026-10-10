@extends('layouts.admin')

@section('title', 'Inventario e Itinerarios · NavegaYA')
@section('content')
@php
    $activeTab = in_array(request('tab'), ['resumen', 'fluvial', 'aereo'], true) ? request('tab') : 'resumen';
@endphp
<x-admin.module-workspace
    eyebrow="Supervisión operativa"
    title="Capacidad y comercio por operador"
    description="Supervisa la oferta publicada por las empresas sin intervenir en la administración diaria de sus itinerarios."
>
    <x-slot:actions>
        <span class="inline-flex h-10 items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black text-emerald-800">{{ $operatorsCount }} operadores activos</span>
        <a href="{{ route('admin.companies.index', ['tab' => 'rendimiento']) }}" class="inline-flex h-10 items-center rounded-xl bg-[#062c21] px-4 text-xs font-black text-white transition hover:bg-emerald-900">Ver empresas →</a>
    </x-slot:actions>

    <x-slot:metrics>
        @php
            $occupancy = $totalPublishedSeats > 0 ? number_format(($totalSoldSeats / $totalPublishedSeats) * 100, 1) : '0.0';
            $metrics = [
                ['Asientos publicados hoy', number_format($totalPublishedSeats), 'Capacidad comercial disponible', '▦', 'text-slate-950'],
                ['Boletos vendidos hoy', number_format($totalSoldSeats), "{$occupancy}% de ocupación", '🎟️', 'text-slate-950'],
                ['Comisión estimada hoy', 'S/ '.number_format($totalCommissionToday, 2), 'Comisión NavegaYA sobre ventas', '%', 'text-amber-600'],
                ['Operadores con salidas', number_format($activeOperatorsCount).' de '.number_format($operatorsCount), 'Con inventario publicado hoy', '●', 'text-emerald-700'],
            ];
        @endphp
        @foreach($metrics as $metric)
            <article class="min-h-32 border-b border-r border-slate-200 p-4 xl:border-b-0"><div class="flex items-start justify-between gap-3"><span class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $metric[0] }}</span><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-emerald-700">{{ $metric[3] }}</span></div><p class="mt-3 text-2xl font-black {{ $metric[4] }}">{{ $metric[1] }}</p><span class="text-[10px] font-semibold text-slate-400">{{ $metric[2] }}</span></article>
        @endforeach
    </x-slot:metrics>

    <x-slot:navigation>
        <a href="{{ route('admin.itineraries.index', ['tab' => 'resumen']) }}" class="whitespace-nowrap rounded-lg px-4 py-2.5 text-xs font-black transition {{ $activeTab === 'resumen' ? 'bg-white text-[#062c21] shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">Resumen</a>
        <a href="{{ route('admin.itineraries.index', ['tab' => 'fluvial']) }}" class="whitespace-nowrap rounded-lg px-4 py-2.5 text-xs font-black transition {{ $activeTab === 'fluvial' ? 'bg-white text-[#062c21] shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">🚤 Fluvial <span class="ml-1 text-[10px] text-slate-400">{{ $fluvialOperators->count() }}</span></a>
        <a href="{{ route('admin.itineraries.index', ['tab' => 'aereo']) }}" class="whitespace-nowrap rounded-lg px-4 py-2.5 text-xs font-black transition {{ $activeTab === 'aereo' ? 'bg-white text-[#062c21] shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">✈️ Aéreo <span class="ml-1 text-[10px] text-slate-400">{{ $airOperators->count() }}</span></a>
    </x-slot:navigation>

    @if(session('success'))<div class="mx-5 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">✓ {{ session('success') }}</div>@endif

    <div class="border-b border-slate-200 p-4 lg:px-6">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <input name="search" value="{{ request('search') }}" class="h-10 min-w-64 flex-1 rounded-xl border-slate-200 bg-slate-50 px-3 text-xs" placeholder="Buscar empresa o RUC">
            <select name="modality" class="h-10 rounded-xl border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700"><option value="">Todas las modalidades</option><option value="fluvial" @selected(request('modality') === 'fluvial')>Fluvial</option><option value="aereo" @selected(request('modality') === 'aereo')>Aéreo</option><option value="mixto" @selected(request('modality') === 'mixto')>Mixto</option></select>
            <button class="h-10 rounded-xl bg-[#062c21] px-5 text-xs font-black text-white hover:bg-emerald-900">Aplicar filtros</button>
            <a href="{{ route('admin.itineraries.index') }}" class="inline-flex h-10 items-center px-3 text-xs font-bold text-emerald-700">Limpiar</a>
            <span class="ml-auto text-[10px] font-semibold text-slate-400">Actualizado {{ now()->translatedFormat('d M Y · H:i') }}</span>
        </form>
    </div>

    @if($activeTab === 'resumen')
    <section class="min-h-[360px]">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4 lg:px-6"><div><h3 class="font-black text-slate-950">Todos los operadores</h3><p class="text-xs text-slate-500">Vista consolidada de capacidad, ventas y control del marketplace.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-600">{{ $operators->count() }} resultados</span></header>
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
