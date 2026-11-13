@extends('layouts.company')
@section('title', 'Embarque y manifiestos')
@section('page-title', 'Embarque y manifiestos')
@section('content')
@php
    $name = $company->commercial_name ?: $company->legal_name;
    $isCounter = auth()->user()->roles()->where('code', 'company_counter')->exists();
@endphp
<div class="mx-auto max-w-5xl space-y-5">
    <section class="rounded-2xl border border-[#d5e6df] bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-emerald-800">Portal Counter · {{ now()->translatedFormat('d \d\e F') }}</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $base }}@if(in_array($company->modality, ['fluvial', 'mixto'], true)) · Río {{ $riverStatus }}@endif</p>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="text-lg font-black text-slate-950">Salidas</h3>
                <p class="text-sm text-slate-500">Hoy y los próximos 14 días salen de la misma lista. El embarque se abre el día del zarpe.</p>
            </div>
            @if($schedule->isEmpty())
                @unless($isCounter)
                    <a href="{{ route('company.departures.create') }}" class="text-sm font-bold text-emerald-800">Programar salida</a>
                @endunless
            @else
                <a href="{{ route('company.departures.index') }}" class="text-sm font-bold text-emerald-800">Ver salidas</a>
            @endif
        </header>
        @forelse($schedule as $row)
            @php($freeSeats = max(0, $row['capacity'] - $row['confirmed_seats']))
            <article class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <strong class="text-lg font-black text-slate-950">{{ $row['when'] === 'today' ? $row['departure_at']->copy()->timezone('America/Lima')->format('H:i') : $row['departure_at']->copy()->timezone('America/Lima')->translatedFormat('D d M · H:i') }}</strong>
                    <span class="mt-0.5 block text-sm text-slate-800">{{ $row['origin'] }} → {{ $row['destination'] }}</span>
                    <span class="block text-sm text-slate-500">{{ $row['unit'] }} · {{ $row['when'] === 'today' ? $freeSeats.' '.($freeSeats === 1 ? 'libre' : 'libres') : $row['confirmed_seats'].' de '.$row['capacity'].' pasajeros' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $row['when'] === 'today' ? 'Hoy · ' : '' }}{{ \App\Support\PanelLabel::status($row['status']) }}</span>
                    @if(! $row['is_air'] && $row['when'] === 'today' && ($row['can_board'] ?? false))
                        <a href="{{ route('company.boarding.show', $row['model']) }}" class="inline-flex min-h-10 items-center rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white">Embarcar</a>
                    @elseif(! $row['is_air'] && $row['when'] === 'today')
                        <span class="text-sm font-bold text-amber-800">Asigna un capitán con licencia vigente</span>
                    @elseif(! $row['is_air'])
                        <a href="{{ route('company.departures.manifest', $row['model']) }}" class="inline-flex min-h-10 items-center rounded-xl border border-emerald-800 px-4 text-sm font-bold text-emerald-900">Ver pasajeros</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="px-5 py-12 text-center">
                <strong class="block text-slate-800">No hay salidas para embarcar</strong>
                <p class="mt-1 text-sm text-slate-500">Cuando exista un zarpe de hoy o de los próximos 14 días, aparece en esta lista.</p>
            </div>
        @endforelse
    </section>
</div>
@endsection
