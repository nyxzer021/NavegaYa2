@extends('layouts.company')
@section('title', 'Control de embarque')
@section('page-title', 'Control de embarque')
@section('content')
@php($name = $company->commercial_name ?: $company->legal_name)
<div class="mx-auto max-w-5xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <p class="text-sm font-semibold text-emerald-800">Portal Counter · {{ now()->translatedFormat('d \d\e F') }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $base }}@if(in_array($company->modality, ['fluvial', 'mixto'], true)) · Río {{ $riverStatus }}@endif</p>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h3 class="text-lg font-black text-slate-950">Salidas de hoy</h3>
            <a href="{{ route('company.departures.index') }}" class="text-sm font-bold text-emerald-800">{{ $departures->isEmpty() ? 'Programar salida' : 'Ver salidas' }}</a>
        </header>
        @forelse($departures as $row)
            @php($freeSeats = max(0, $row['capacity'] - $row['confirmed_seats']))
            <article class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <strong class="text-lg font-black text-slate-950">{{ $row['departure_at']->format('H:i') }}</strong>
                    <span class="mt-0.5 block text-sm text-slate-800">{{ $row['origin'] }} → {{ $row['destination'] }}</span>
                    <span class="block text-sm text-slate-500">{{ $row['unit'] }} · {{ $freeSeats }} {{ $freeSeats === 1 ? 'libre' : 'libres' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ \App\Support\PanelLabel::status($row['status']) }}</span>
                    @if(! $row['is_air'])
                        <a href="{{ route('company.boarding.show', $row['model']) }}" class="inline-flex min-h-10 items-center rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white">Embarcar</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="px-5 py-12 text-center">
                <strong class="block text-slate-800">Hoy no hay un zarpe para embarcar</strong>
                <p class="mt-1 text-sm text-slate-500">El control de pasajeros se abre el día de la salida. Las rutas ya programadas están abajo.</p>
            </div>
        @endforelse
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-lg font-black text-slate-950">Rutas programadas</h3>
            <p class="text-sm text-slate-500">Pasajeros registrados en los próximos zarpes. El embarque se marca el mismo día.</p>
        </header>
        @forelse($upcoming as $row)
            <article class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <strong class="text-slate-950">{{ $row['departure_at']->translatedFormat('D d M · H:i') }}</strong>
                    <span class="mt-0.5 block text-sm text-slate-800">{{ $row['origin'] }} → {{ $row['destination'] }}</span>
                    <span class="block text-sm text-slate-500">{{ $row['unit'] }} · {{ $row['confirmed_seats'] }} de {{ $row['capacity'] }} pasajeros</span>
                </div>
                @if(! $row['is_air'])
                    <a href="{{ route('company.departures.manifest', $row['model']) }}" class="inline-flex min-h-10 items-center rounded-xl border border-emerald-800 px-4 text-sm font-bold text-emerald-900">Ver pasajeros</a>
                @endif
            </article>
        @empty
            <div class="px-5 py-10 text-center">
                <strong class="block text-slate-800">No hay rutas programadas</strong>
                <p class="mt-1 text-sm text-slate-500">Programa una salida para registrar pasajeros y controlar el embarque.</p>
                <a href="{{ route('company.departures.create') }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Programar salida</a>
            </div>
        @endforelse
    </section>
</div>
@endsection
