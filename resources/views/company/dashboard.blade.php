@extends('layouts.company')
@section('title', 'Centro de operación')
@section('page-title', 'Centro de operación')
@section('content')
@php
    $name = $company->commercial_name ?: $company->legal_name;
    $hasAir = $company->aircraft->isNotEmpty();
    $hasRiver = $company->vessels->isNotEmpty();
    $statusLabels = ['scheduled' => 'Programada', 'boarding' => 'Embarcando', 'in_transit' => 'En tránsito', 'departed' => 'En tránsito', 'completed' => 'Completada', 'delayed' => 'Retrasada', 'rescheduled' => 'Reprogramada', 'suspended' => 'Suspendida', 'cancelled' => 'Cancelada'];
    $modality = $hasAir && ! $hasRiver ? 'Aéreo' : ($hasAir && $hasRiver ? 'Fluvial y aéreo' : 'Fluvial');
@endphp
<div class="mx-auto max-w-5xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold {{ $company->status === 'active' ? 'text-emerald-800' : 'text-amber-800' }}">{{ $company->status === 'active' ? 'Operación en línea' : 'Validación pendiente' }}</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $modality }} · {{ now()->translatedFormat('l d \d\e F') }}</p>
            </div>
            @if($company->status === 'active')
                <a href="{{ route('company.pos.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Vender pasaje</a>
            @else
                <a href="{{ route('company.fleet.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Completar flota</a>
            @endif
        </div>
    </section>

    @if($company->status !== 'active')
        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-black text-slate-950">Completa la flota</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Registra unidades, capacidad, matrícula y plano de asientos.</p>
                <a href="{{ route('company.fleet.index') }}" class="mt-4 inline-flex text-sm font-bold text-emerald-800">Configurar flota</a>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-black text-slate-950">Espera la validación</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">NavegaYA revisará el RUC y los permisos operativos antes de habilitar ventas.</p>
                <a href="{{ route('support.index') }}" class="mt-4 inline-flex text-sm font-bold text-emerald-800">Consultar estado</a>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-black text-slate-950">Inicia la operación</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Al ser aprobada podrás programar salidas, vender pasajes y controlar el embarque.</p>
            </article>
        </section>
    @else
        @if($attentionCount > 0)
            <a href="{{ route('company.departures.index') }}" class="flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950">
                <span>
                    <strong class="block text-sm">{{ \App\Support\SpanishCount::phrase($attentionCount, 'asunto requiere atención', 'asuntos requieren atención') }}</strong>
                    <span class="text-sm text-amber-800">Salidas con cambios o encomiendas pendientes.</span>
                </span>
                <span class="text-sm font-bold">Revisar</span>
            </a>
        @endif

        <section class="grid gap-3 sm:grid-cols-3">
            <a href="{{ route('company.departures.index') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-sm font-semibold text-slate-500">Salidas de hoy</span>
                <strong class="mt-2 block text-3xl font-black text-slate-950">{{ $todayDepartureCount }}</strong>
            </a>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-sm font-semibold text-slate-500">Ocupación</span>
                <strong class="mt-2 block text-3xl font-black text-slate-950">{{ $todayOccupancyLabel }}%</strong>
                <span class="mt-1 block text-sm text-slate-500">{{ $todayConfirmedSeats }} de {{ $todaySeatCapacity }} {{ $todaySeatCapacity === 1 ? 'asiento' : 'asientos' }}</span>
            </article>
            <a href="{{ route('company.commissions.index') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-sm font-semibold text-slate-500">Por cobrar</span>
                <strong class="mt-2 block text-3xl font-black text-slate-950">S/ {{ number_format($commissionReceivable, 2) }}</strong>
                <span class="mt-1 block text-sm text-slate-500">Comisión del convenio</span>
            </a>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <h3 class="text-lg font-black text-slate-950">Salidas de hoy</h3>
                <a href="{{ route('company.departures.create') }}" class="text-sm font-bold text-emerald-800">Nueva salida</a>
            </header>
            @forelse($todayDepartures as $row)
                @php($freeSeats = max(0, $row['capacity'] - $row['confirmed_seats']))
                <article class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <strong class="text-lg font-black text-slate-950">{{ $row['departure_at']->format('H:i') }}</strong>
                        <span class="mt-0.5 block text-sm text-slate-800">{{ $row['origin'] }} → {{ $row['destination'] }}</span>
                        <span class="block text-sm text-slate-500">{{ $row['unit'] }} · {{ $freeSeats }} {{ $freeSeats === 1 ? 'libre' : 'libres' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $statusLabels[$row['status']] ?? \App\Support\PanelLabel::status($row['status']) }}</span>
                        @if(! $row['is_air'])
                            <a href="{{ route('company.boarding.show', $row['model']) }}" class="inline-flex min-h-10 items-center rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white">Embarcar</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="px-5 py-12 text-center">
                    <strong class="block text-slate-800">No hay salidas para hoy</strong>
                    <p class="mt-1 text-sm text-slate-500">Programa una salida para comenzar la operación.</p>
                    <a href="{{ route('company.departures.create') }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl border border-emerald-800 px-5 text-sm font-bold text-emerald-900">Programar salida</a>
                </div>
            @endforelse
        </section>
    @endif
</div>
@endsection
