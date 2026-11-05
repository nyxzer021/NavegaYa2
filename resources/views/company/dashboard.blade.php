@extends('layouts.company')
@section('title', 'Inicio')
@section('page-title', 'Inicio')
@section('content')
@php
    $name = $company->commercial_name ?: $company->legal_name;
    $hasAir = $company->aircraft->isNotEmpty();
    $hasRiver = $company->vessels->isNotEmpty();
    $modality = $hasAir && ! $hasRiver ? 'Aéreo' : ($hasAir && $hasRiver ? 'Fluvial y aéreo' : 'Fluvial');
@endphp
<style>
    .ny-board{display:grid;gap:1rem}
    .ny-panel{background:#fff;border:1px solid #e1e8e4;border-radius:18px;padding:1.1rem 1.15rem}
    .ny-panel h3{margin:0;font-size:1rem;font-weight:800;color:#10231D}
    .ny-lead{margin:.25rem 0 0;font-size:.85rem;line-height:1.45;color:#607069}
    .ny-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin-top:.85rem}
    .ny-metric{display:flex;min-height:5.4rem;flex-direction:column;justify-content:center;border:1px solid #e1e8e4;border-radius:16px;background:#fff;padding:.8rem .95rem;color:#10231D;text-decoration:none}
    .ny-metric strong{display:block;margin-top:.2rem;font-size:1.45rem;line-height:1;font-weight:800;color:#10231D}
    .ny-k{font-size:.75rem;font-weight:700;color:#607069}
    .ny-h{display:block;margin-top:.35rem;font-size:.75rem;color:#7d8c86}
    .ny-metric.is-mint{background:#f4faf7}
    .ny-metric.is-sand{background:#fbf8f4}
    .ny-metric.is-rose{background:#fbf7f7}
    .ny-alert{display:flex;align-items:center;justify-content:space-between;gap:.75rem;border:1px solid #eadfca;border-radius:16px;background:#fbf8f4;padding:.85rem 1rem;color:#5c4a2a;text-decoration:none}
    .ny-maintain{margin:.45rem 0 0;font-size:.9rem;color:#10231D}
    @media (min-width:900px){
        .ny-metrics.cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}
        .ny-metrics.cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}
        .ny-metrics.cols-5{grid-template-columns:repeat(5,minmax(0,1fr))}
    }
</style>
<div class="ny-board">
    <section class="ny-panel">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold {{ $company->status === 'active' ? 'text-emerald-800' : 'text-amber-800' }}">{{ $company->status === 'active' ? 'Operación en línea' : 'Validación pendiente' }}</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $modality }}@if($baseLabel) · {{ $baseLabel }}@endif · {{ now()->translatedFormat('l d \d\e F') }}</p>
            </div>
        </div>
    </section>

    @if($steps !== [])
        <section class="ny-panel">
            <h3>{{ __('company_portal.home.steps') }}</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
                @foreach($steps as $step)
                    <article class="rounded-2xl border border-[#e1e8e4] p-4">
                        <h4 class="font-black text-slate-950">{{ $step['title'] }}</h4>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $step['detail'] }}</p>
                        @if($step['href'])
                            <a href="{{ $step['href'] }}" class="mt-4 inline-flex text-sm font-bold text-emerald-800">Abrir</a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($company->status === 'active')
        @if($attention->isNotEmpty())
            <section class="ny-panel">
                <h3>{{ __('company_portal.home.attention') }}</h3>
                <div class="mt-3 grid gap-2">
                    @foreach($attention as $item)
                        <a href="{{ $item['href'] }}" class="ny-alert">
                            <span>
                                <strong class="block text-sm">{{ $item['title'] }}</strong>
                                <span class="text-sm">{{ $item['detail'] }}</span>
                            </span>
                            <span class="text-sm font-bold">Abrir</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ny-panel">
            <h3>Este mes</h3>
            <p class="ny-lead">Cifras calculadas con los pagos confirmados. El embarque está en Salidas.</p>
            <div class="ny-metrics cols-4">
                <a href="{{ route('company.sales.index') }}" class="ny-metric">
                    <span class="ny-k">Pasajes vendidos</span>
                    <strong>{{ $month['ticket_count'] }}</strong>
                    <span class="ny-h">S/ {{ number_format($month['ticket_amount'], 2) }} en {{ now()->translatedFormat('F') }}</span>
                </a>
                <article class="ny-metric">
                    <span class="ny-k">Pasajes pendientes</span>
                    <strong>{{ $month['pending_tickets'] }}</strong>
                    <span class="ny-h">Aún no se pagaron</span>
                </article>
                <a href="{{ route('company.departures.index') }}" class="ny-metric">
                    <span class="ny-k">Reprogramadas</span>
                    <strong>{{ $month['rescheduled'] }}</strong>
                    <span class="ny-h">Salidas del mes</span>
                </a>
                <a href="{{ route('support.index') }}" class="ny-metric">
                    <span class="ny-k">Reclamos del mes</span>
                    <strong>{{ $month['claims'] }}</strong>
                    <span class="ny-h">Soporte de la empresa</span>
                </a>
            </div>
            <h4 class="mt-4 text-sm font-black text-slate-950">{{ __('company_portal.home.channels') }}</h4>
            <div class="ny-metrics cols-3">
                <article class="ny-metric">
                    <span class="ny-k">{{ __('company_portal.home.online') }}</span>
                    <strong>S/ {{ number_format($month['web_sales'], 2) }}</strong>
                </article>
                <article class="ny-metric">
                    <span class="ny-k">{{ __('company_portal.home.dock') }}</span>
                    <strong>S/ {{ number_format($month['counter_sales'], 2) }}</strong>
                </article>
            </div>
        </section>

        <section class="ny-panel">
            <h3>Flota</h3>
            <p class="ny-lead">{{ $fleetPhrase }}. El mismo conteo que la ficha pública.</p>
            <div class="ny-metrics cols-3">
                <a href="{{ route('company.fleet.index') }}" class="ny-metric">
                    <span class="ny-k">Embarcaciones</span>
                    <strong class="!text-base !leading-snug">{{ $fleetPhrase }}</strong>
                </a>
                <a href="{{ route('company.fleet.index') }}" class="ny-metric">
                    <span class="ny-k">En mantenimiento</span>
                    <strong>{{ $month['maintenance_vessels'] }}</strong>
                    <span class="ny-h">No salen a vender</span>
                </a>
                <article class="ny-metric">
                    <span class="ny-k">Asientos de la flota</span>
                    <strong>{{ $month['seat_capacity'] }}</strong>
                    <span class="ny-h">Capacidad total</span>
                </article>
            </div>
        </section>

        <section class="ny-panel">
            <h3>Lectura de hoy</h3>
            <p class="ny-lead">Solo el resultado del día. Para marcar pasajeros, abre Embarque.</p>
            <div class="ny-metrics cols-3">
                <a href="{{ route('company.departures.index') }}" class="ny-metric">
                    <span class="ny-k">Salidas de hoy</span>
                    <strong>{{ $todayDepartureCount }}</strong>
                    <span class="ny-h">Zarpes con fecha de hoy</span>
                </a>
                <article class="ny-metric">
                    <span class="ny-k">Ocupación</span>
                    <strong>{{ $todayOccupancyLabel }}%</strong>
                    <span class="ny-h">{{ $todayConfirmedSeats }} de {{ $todaySeatCapacity }} {{ $todaySeatCapacity === 1 ? 'asiento' : 'asientos' }}</span>
                </article>
            </div>
        </section>

        <section class="ny-panel">
            <h3>{{ __('company_portal.home.upcoming') }}</h3>
            @forelse($upcomingDepartures as $row)
                <div class="mt-3 flex flex-col gap-2 border-t border-[#e1e8e4] pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-800"><strong>{{ $row['departure_at']->translatedFormat('D d M · H:i') }}</strong> · {{ $row['origin'] }} → {{ $row['destination'] }} · {{ $row['unit'] }}</p>
                    <a href="{{ route('company.departures.index') }}" class="text-sm font-bold text-emerald-800">{{ __('company_portal.home.see_departure') }}</a>
                </div>
            @empty
                <p class="ny-lead">{{ __('company_portal.home.upcoming_empty') }}</p>
            @endforelse
        </section>

        <section class="ny-panel">
            <div class="flex items-center justify-between gap-3">
                <h3>Mantenimiento programado</h3>
                <a href="{{ route('company.fleet.index') }}" class="text-sm font-bold text-[#087F5B]">Anotar fecha</a>
            </div>
            @forelse($month['maintenance'] as $item)
                <p class="ny-maintain"><strong>{{ $item['on']->translatedFormat('d M') }}</strong> · {{ $item['name'] }} · {{ $item['kind'] }}</p>
            @empty
                <p class="ny-lead">Ninguna fecha programada.</p>
            @endforelse
        </section>
    @endif
</div>
@endsection
