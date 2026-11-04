@extends('layouts.company')
@section('title', 'Dash')
@section('page-title', 'Dash')
@section('content')
@php
    $name = $company->commercial_name ?: $company->legal_name;
    $hasAir = $company->aircraft->isNotEmpty();
    $hasRiver = $company->vessels->isNotEmpty();
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
            @if($company->status !== 'active')
                <a href="{{ route('company.fleet.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Completar flota</a>
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

        <section class="grid grid-cols-2 gap-2 lg:grid-cols-3">
            <a href="{{ route('company.sales.index') }}" class="rounded-xl border border-[#6ee7b7] bg-[#ecfdf5] px-3 py-2">
                <span class="text-xs font-semibold text-emerald-800">Pasajes vendidos</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-[#064e3b]">{{ $month['ticket_count'] }}</strong>
                <span class="mt-1 block text-xs text-emerald-900">S/ {{ number_format($month['ticket_amount'], 2) }} en {{ now()->translatedFormat('F') }}</span>
            </a>
            <a href="{{ route('company.fleet.index') }}" class="rounded-xl border border-[#d5e6df] bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-600">Embarcaciones activas</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-slate-950">{{ $month['active_vessels'] }}</strong>
                <span class="mt-1 block text-xs text-slate-500">Habilitadas para vender</span>
            </a>
            <a href="{{ route('company.fleet.index') }}" class="rounded-xl border border-orange-200 bg-orange-50 px-3 py-2">
                <span class="text-xs font-semibold text-orange-800">En mantenimiento</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-orange-950">{{ $month['maintenance_vessels'] }}</strong>
                <span class="mt-1 block text-xs text-orange-800">No salen a vender</span>
            </a>
            <a href="{{ route('company.departures.index') }}" class="rounded-xl border border-[#fbbf24] bg-[#fffbeb] px-3 py-2">
                <span class="text-xs font-semibold text-amber-800">Reprogramadas</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-[#78350f]">{{ $month['rescheduled'] }}</strong>
                <span class="mt-1 block text-xs text-amber-900">Salidas del mes</span>
            </a>
            <article class="rounded-xl border border-[#d5e6df] bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-600">Pasajes pendientes</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-slate-950">{{ $month['pending_tickets'] }}</strong>
                <span class="mt-1 block text-xs text-slate-500">Aún no se pagaron</span>
            </article>
            <a href="{{ route('support.index') }}" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2">
                <span class="text-xs font-semibold text-rose-800">Reclamos del mes</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-rose-950">{{ $month['claims'] }}</strong>
                <span class="mt-1 block text-xs text-rose-800">Soporte de la empresa</span>
            </a>
            <article class="col-span-2 rounded-xl border border-orange-200 bg-orange-50 px-3 py-2 lg:col-span-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-orange-800">Mantenimiento programado</span>
                    <a href="{{ route('company.fleet.index') }}" class="text-xs font-bold text-orange-900">Anotar fecha</a>
                </div>
                @forelse($month['maintenance'] as $item)
                    <p class="mt-1 text-xs text-orange-950"><strong>{{ $item['on']->translatedFormat('d M') }}</strong> · {{ $item['name'] }} · {{ $item['kind'] }}</p>
                @empty
                    <p class="mt-1 text-xs text-orange-900">Ninguna fecha programada.</p>
                @endforelse
            </article>
        </section>

        <section class="rounded-xl border border-[#d5e6df] bg-white px-3 py-3 shadow-sm">
            <h3 class="text-sm font-black text-slate-950">Salidas diarias por embarcación</h3>
            <p class="text-xs text-slate-500">Últimos 14 días. Cada color es una embarcación.</p>
            <div class="mt-2 h-44">
                <canvas id="vesselDeparturesChart" aria-label="Salidas diarias por embarcación"></canvas>
            </div>
        </section>

        <section class="grid grid-cols-3 gap-2">
            <a href="{{ route('company.departures.index') }}" class="rounded-xl border border-[#6ee7b7] bg-[#ecfdf5] px-3 py-2">
                <span class="text-xs font-semibold text-emerald-800">Salidas de hoy</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-[#064e3b]">{{ $todayDepartureCount }}</strong>
            </a>
            <article class="rounded-xl border border-[#fbbf24] bg-[#fffbeb] px-3 py-2">
                <span class="text-xs font-semibold text-amber-800">Ocupación</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-[#78350f]">{{ $todayOccupancyLabel }}%</strong>
                <span class="mt-1 block text-xs text-amber-900">{{ $todayConfirmedSeats }} de {{ $todaySeatCapacity }} {{ $todaySeatCapacity === 1 ? 'asiento' : 'asientos' }}</span>
            </article>
            <a href="{{ route('company.commissions.index') }}" class="rounded-xl bg-[#062c21] px-3 py-2 text-white">
                <span class="text-xs font-semibold text-emerald-100">Por cobrar</span>
                <strong class="mt-0.5 block text-lg font-black leading-none text-[#fbbf24]">S/ {{ number_format($commissionReceivable, 2) }}</strong>
                <span class="mt-1 block text-xs text-emerald-100">Comisión del convenio</span>
            </a>
        </section>

        @if($upcomingDepartures->isNotEmpty())
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-lg font-black text-slate-950">Próximas salidas</h3>
                    <p class="text-sm text-slate-500">Rutas ya programadas. La venta en muelle y el embarque se abren el día del zarpe.</p>
                </header>
                @foreach($upcomingDepartures as $row)
                    <article class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <strong class="text-slate-950">{{ $row['departure_at']->translatedFormat('D d M · H:i') }}</strong>
                            <span class="mt-0.5 block text-sm text-slate-700">{{ $row['origin'] }} → {{ $row['destination'] }}</span>
                            <span class="block text-sm text-slate-500">{{ $row['unit'] }} · {{ $row['confirmed_seats'] }} {{ $row['confirmed_seats'] === 1 ? 'pasajero' : 'pasajeros' }}</span>
                        </div>
                        <x-company-status :status="$row['status']" />
                    </article>
                @endforeach
            </section>
        @endif

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="text-lg font-black text-slate-950">Salidas de hoy</h3>
                    <p class="text-sm text-slate-500">Ocupación de los asientos confirmados en los zarpes de hoy.</p>
                </div>
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
                        <x-company-status :status="$row['status']" />
                        @if(! $row['is_air'])
                            <a href="{{ route('company.boarding.show', $row['model']) }}" class="inline-flex min-h-10 items-center rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white">Embarcar</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="px-5 py-12 text-center">
                    <strong class="block text-slate-800">No hay salidas para hoy</strong>
                    <p class="mt-1 text-sm text-slate-500">Programa una salida para comenzar la operación.</p>
                    <a href="{{ route('company.departures.create') }}" class="mt-4 inline-flex h-10 items-center text-sm font-bold text-emerald-800">Programar salida</a>
                </div>
            @endforelse
        </section>
    @endif
</div>
@if($company->status === 'active')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var canvas = document.getElementById('vesselDeparturesChart');
        var chart = @json($departureChart);
        if (!canvas || typeof Chart === 'undefined' || chart.datasets.length === 0) {
            if (canvas) canvas.insertAdjacentHTML('afterend', '<p class="text-sm text-slate-500">No hay salidas en los últimos 14 días.</p>');
            return;
        }
        new Chart(canvas, {
            type: 'bar',
            data: chart,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    });
</script>
@endif
@endsection
