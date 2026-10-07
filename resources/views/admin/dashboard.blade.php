@extends('layouts.admin')

@section('title', 'Centro Ejecutivo · NavegaYA')
@section('main-class', 'flex-1 bg-slate-50 p-4 lg:p-5')
@section('header')
<div class="flex items-center gap-3">
    <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-lg">📊</span>
    <div>
        <h1 class="text-sm font-black text-slate-900">Centro Ejecutivo</h1>
        <p class="text-[10px] font-semibold text-slate-400">Marketplace NavegaYA · Loreto</p>
    </div>
</div>
@endsection

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<div x-data="{ tab: 'resumen', changeTab(name) { this.tab = name; this.$nextTick(() => window.initDashboardTab(name)); } }" class="w-full space-y-4 text-slate-800">
    <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:flex-row xl:items-center xl:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Marketplace operativo</span>
                <span class="text-[10px] font-semibold text-slate-400">Actualizado {{ now()->format('d/m/Y · H:i') }}</span>
            </div>
            <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Panorama corporativo</h2>
            <p class="mt-0.5 text-xs text-slate-500">Ventas, demanda, empresas afiliadas y continuidad operacional.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <nav class="flex rounded-xl bg-slate-100 p-1 text-[10px] font-black">
                @foreach(['today' => 'Hoy', 'week' => '7 días', 'month' => 'Este mes'] as $key => $label)
                    <a href="{{ route('admin.dashboard', ['period' => $key]) }}" class="rounded-lg px-3 py-2 transition {{ $period === $key ? 'bg-white text-emerald-800 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <details class="relative">
                <summary class="flex h-9 cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[10px] font-bold text-slate-700 hover:border-emerald-300">
                    <span class="text-sm">📅</span><span><span class="block text-[8px] uppercase text-slate-400">Período analizado</span>{{ $from->translatedFormat('d M') }} – {{ $to->translatedFormat('d M Y') }}</span><span class="text-slate-400">Cambiar ▾</span>
                </summary>
                <form method="GET" action="{{ route('admin.dashboard') }}" class="absolute right-0 z-[1000] mt-2 grid w-72 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
                    <input type="hidden" name="period" value="custom">
                    <label class="text-[9px] font-black uppercase text-slate-400">Desde<input type="date" name="from" value="{{ request('from', $from->toDateString()) }}" required class="mt-1 h-9 w-full rounded-lg border-slate-200 bg-slate-50 text-xs"></label>
                    <label class="text-[9px] font-black uppercase text-slate-400">Hasta<input type="date" name="to" value="{{ request('to', $to->toDateString()) }}" required class="mt-1 h-9 w-full rounded-lg border-slate-200 bg-slate-50 text-xs"></label>
                    <button class="h-9 rounded-lg bg-emerald-700 text-xs font-black text-white">Aplicar período</button>
                </form>
            </details>
            <details class="relative">
                <summary class="flex h-9 cursor-pointer list-none items-center gap-2 rounded-xl bg-amber-400 px-4 text-[10px] font-black text-slate-950 hover:bg-amber-300">⬇ Descargar reporte ▾</summary>
                <div class="absolute right-0 z-[1000] mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-2xl">
                    <button type="button" onclick="window.downloadDashboardCsv()" class="w-full rounded-lg px-3 py-2 text-left text-[10px] font-bold text-slate-700 hover:bg-slate-50">📊 Descargar resumen CSV</button>
                    <button type="button" onclick="window.print()" class="w-full rounded-lg px-3 py-2 text-left text-[10px] font-bold text-slate-700 hover:bg-slate-50">🖨️ Imprimir / guardar PDF</button>
                    <a href="{{ route('admin.sales.index') }}" class="block rounded-lg px-3 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50">📋 Abrir detalle de ventas</a>
                </div>
            </details>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @php
            $kpis = [
                ['GMV del período', 'S/ '.number_format($grossSales, 2), 'Ventas procesadas', '💳', 'admin.sales.index', 'border-blue-200 bg-blue-50/70', 'bg-blue-100 text-blue-700', 'group-hover:border-blue-300'],
                ['Comisión generada', 'S/ '.number_format($netCommission, 2), 'Ingreso NavegaYA', '%', 'admin.commissions.index', 'border-emerald-200 bg-emerald-50/70', 'bg-emerald-100 text-emerald-700', 'group-hover:border-emerald-300'],
                ['Pasajes vendidos', number_format($totalTicketsSold), 'Fluvial '.$fluvialTicketsSold.' · Aéreo '.$airTicketsSold, '🎟️', 'admin.sales.index', 'border-cyan-200 bg-cyan-50/70', 'bg-cyan-100 text-cyan-700', 'group-hover:border-cyan-300'],
                ['Pasajes cancelados', number_format($cancelledTickets), 'En el período', '↩', 'admin.refunds.index', 'border-rose-200 bg-rose-50/70', 'bg-rose-100 text-rose-700', 'group-hover:border-rose-300'],
                ['Empresas registradas', number_format($totalOperatorsCount), $activeOperatorsCount.' activas', '🏢', 'admin.companies.index', 'border-violet-200 bg-violet-50/70', 'bg-violet-100 text-violet-700', 'group-hover:border-violet-300'],
                ['Nuevas afiliaciones', number_format($newOperatorsInPeriod), $pendingOperatorsCount.' por aprobar', '＋', 'admin.companies.index', 'border-amber-200 bg-amber-50/70', 'bg-amber-100 text-amber-700', 'group-hover:border-amber-300'],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <a href="{{ route($kpi[4]) }}" class="group rounded-2xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $kpi[5] }} {{ $kpi[7] }}">
                <div class="flex items-start justify-between gap-2"><span class="text-[9px] font-black uppercase tracking-wider text-slate-600">{{ $kpi[0] }}</span><span class="grid h-8 w-8 place-items-center rounded-xl text-sm {{ $kpi[6] }}">{{ $kpi[3] }}</span></div>
                <strong class="mt-2 block text-xl font-black text-slate-950">{{ $kpi[1] }}</strong>
                <span class="mt-1 flex items-center justify-between text-[9px] font-semibold text-slate-400"><span>{{ $kpi[2] }}</span><span class="text-emerald-600 opacity-0 transition group-hover:opacity-100">Ver →</span></span>
            </a>
        @endforeach
    </section>

    <section class="grid gap-3 lg:grid-cols-[1.4fr_1fr]">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between"><div><p class="text-[9px] font-black uppercase tracking-wider text-emerald-700">Pulso de hoy</p><h3 class="text-sm font-black text-slate-900">Continuidad del marketplace</h3></div><a href="{{ route('admin.itineraries.index') }}" class="text-[9px] font-black text-emerald-700">Abrir operación →</a></div>
            <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                @foreach([
                    ['Salidas', $todaysDeparturesCount, '🕒'], ['En río', $riverUnitsInTransit, '🚤'], ['En vuelo', $airUnitsInFlight, '✈️'],
                    ['Canceladas', $cancelledDeparturesToday, '⛔'], ['Alertas', $technicalAlerts, '🛡️'], ['Ocupación', number_format($todayOccupancyRate, 1).'%', '◔']
                ] as $pulse)
                    <div class="rounded-xl bg-slate-50 px-2 py-3 text-center"><span class="text-base">{{ $pulse[2] }}</span><strong class="mt-1 block text-base font-black text-slate-950">{{ $pulse[1] }}</strong><span class="text-[8px] font-bold uppercase text-slate-400">{{ $pulse[0] }}</span></div>
                @endforeach
            </div>
        </article>
        <article class="flex items-center justify-between gap-3 rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 to-white p-4 shadow-sm">
            <div><p class="text-[9px] font-black uppercase tracking-wider text-cyan-700">Clima y navegabilidad</p><h3 class="mt-1 text-sm font-black text-slate-900">Condiciones de Loreto</h3><p class="mt-1 text-[10px] text-slate-500">Consulta lluvia, temperatura y nivel del río antes de supervisar rutas.</p><a href="{{ route('weather.index') }}" target="_blank" class="mt-3 inline-flex text-[10px] font-black text-cyan-800">Ver monitoreo climático →</a></div><span class="text-4xl">🌦️</span>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-fuchsia-200 bg-gradient-to-r from-fuchsia-950 via-violet-950 to-indigo-950 text-white shadow-sm">
        <div class="grid gap-4 p-4 lg:grid-cols-[1.1fr_1.4fr_auto] lg:items-center">
            <div>
                <div class="flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-lg">📣</span><div><p class="text-[9px] font-black uppercase tracking-[.18em] text-fuchsia-200">Publicidad B2B</p><h3 class="text-sm font-black">Negocios que anuncian en NavegaYA</h3></div></div>
                <p class="mt-2 text-[10px] leading-relaxed text-violet-200">Controla contratos, solicitudes y rendimiento publicitario sin mezclarlo con la venta de pasajes.</p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-xl bg-white/10 p-3"><span class="text-[8px] font-bold uppercase text-fuchsia-200">Anunciantes</span><strong class="mt-1 block text-lg font-black">{{ $activeAdvertisersCount }}</strong><span class="text-[8px] text-white/60">negocios activos</span></div>
                <div class="rounded-xl bg-white/10 p-3"><span class="text-[8px] font-bold uppercase text-amber-200">Solicitudes</span><strong class="mt-1 block text-lg font-black">{{ $pendingAdLeadsCount }}</strong><span class="text-[8px] text-white/60">por evaluar</span></div>
                <div class="rounded-xl bg-white/10 p-3"><span class="text-[8px] font-bold uppercase text-emerald-200">Ingreso mensual</span><strong class="mt-1 block text-lg font-black">S/ {{ number_format($monthlyAdvertisingRevenue, 2) }}</strong><span class="text-[8px] text-white/60">contratado</span></div>
                <div class="rounded-xl bg-white/10 p-3"><span class="text-[8px] font-bold uppercase text-cyan-200">Rendimiento</span><strong class="mt-1 block text-lg font-black">{{ number_format($advertisingCtr, 1) }}%</strong><span class="text-[8px] text-white/60">CTR · {{ $advertisingClicks }} clics</span></div>
            </div>
            <a href="{{ route('admin.advertisements.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl bg-fuchsia-400 px-4 text-[10px] font-black text-fuchsia-950 transition hover:bg-fuchsia-300">Gestionar pautas →</a>
        </div>
        <div class="grid border-t border-white/10 sm:grid-cols-3">
            @foreach([
                ['Esencial', '1 ubicación publicitaria', $advertisingPlans['essential']['count'], 'text-sky-200'],
                ['Destacado', '2 ubicaciones publicitarias', $advertisingPlans['featured']['count'], 'text-violet-200'],
                ['Cobertura total', '3 o más ubicaciones', $advertisingPlans['total']['count'], 'text-amber-200']
            ] as $plan)
                <button type="button" @click="changeTab('publicidad')" class="flex items-center justify-between border-white/10 px-4 py-3 text-left transition hover:bg-white/10 sm:border-r">
                    <span><strong class="block text-[10px] font-black {{ $plan[3] }}">{{ $plan[0] }}</strong><span class="text-[8px] text-white/55">{{ $plan[1] }}</span></span>
                    <span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-black">{{ $plan[2] }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div><h3 class="text-sm font-black text-slate-950">Análisis ejecutivo</h3><p class="text-[10px] text-slate-400">Selecciona una perspectiva para revisar indicadores y acceder al detalle.</p></div>
            <nav class="flex max-w-full gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1">
                @foreach(['resumen' => '▦ Resumen', 'ventas' => '↗ Ventas y rutas', 'empresas' => '▥ Empresas y flota', 'operacion' => '⚠ Operación', 'carga' => '◆ Carga', 'publicidad' => '📣 Publicidad B2B'] as $key => $label)
                    <button type="button" @click="changeTab('{{ $key }}')" :class="tab==='{{ $key }}' ? '{{ $key === 'publicidad' ? 'bg-fuchsia-700 text-white' : 'bg-emerald-700 text-white' }} shadow-sm' : 'text-slate-500 hover:bg-white hover:text-slate-900'" class="whitespace-nowrap rounded-lg px-3 py-2 text-[10px] font-black transition">{{ $label }}</button>
                @endforeach
            </nav>
        </div>

        <div x-show="tab==='resumen'" class="grid gap-4 p-4 xl:grid-cols-[minmax(0,2fr)_minmax(290px,.75fr)]">
            <article class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap justify-between gap-2"><div><h4 class="text-xs font-black uppercase text-slate-900">Evolución de ventas</h4><p class="text-[10px] text-slate-400">Monto vendido por modalidad durante el período</p></div><div class="flex gap-3 text-[9px] font-bold"><span class="text-emerald-700">● Fluvial</span><span class="text-cyan-700">● Aéreo</span><span class="text-amber-600">● Carga</span></div></div>
                <div class="relative h-56 pt-3"><canvas id="revenueTrendChart" class="{{ array_sum($modalityValues) <= 0 ? 'opacity-25' : '' }}"></canvas>@if(array_sum($modalityValues) <= 0)<div class="pointer-events-none absolute inset-0 grid place-items-center"><div class="rounded-xl border border-slate-200 bg-white/95 px-5 py-3 text-center shadow-sm"><span class="text-xl">📊</span><strong class="block text-[11px] text-slate-700">Aún no hay ventas en este período</strong><span class="text-[9px] text-slate-400">El gráfico aparecerá cuando se confirme la primera venta.</span></div></div>@endif</div>
            </article>
            <article class="flex flex-col rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div><h4 class="text-xs font-black uppercase text-slate-900">Distribución de ventas</h4><p class="text-[10px] text-slate-400">Participación fluvial, aérea y carga</p></div>
                <div class="relative mx-auto h-36 w-36 py-2"><canvas id="modalityChart"></canvas><div class="pointer-events-none absolute inset-0 grid place-items-center pt-1 text-center"><span class="text-[9px] font-bold text-slate-400">Total<br><strong class="text-sm text-slate-800">S/ {{ number_format($grossSales + $cargoRevenue, 2) }}</strong></span></div></div>
                <div class="mt-auto grid grid-cols-3 gap-1 text-center text-[9px] font-bold"><span class="rounded-lg bg-emerald-50 p-2 text-emerald-700">Fluvial<br>{{ $modalityPercentages[0] }}%</span><span class="rounded-lg bg-cyan-50 p-2 text-cyan-700">Aéreo<br>{{ $modalityPercentages[1] }}%</span><span class="rounded-lg bg-amber-50 p-2 text-amber-700">Carga<br>{{ $modalityPercentages[2] }}%</span></div>
                @if(array_sum($modalityValues) <= 0)<p class="mt-2 text-center text-[9px] font-semibold text-slate-400">Sin ventas confirmadas</p>@endif
            </article>
        </div>

        <div x-show="tab==='ventas'" x-cloak class="space-y-4 p-4">
            <div class="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                <article class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Mapa comercial de rutas</h4><p class="text-[9px] text-slate-400">Haz clic en una línea para ver operador, pasajes y ventas</p></div><div class="flex gap-2 text-[9px] font-bold"><span class="rounded-full bg-emerald-50 px-2 py-1 text-emerald-700">━ Fluvial</span><span class="rounded-full bg-cyan-50 px-2 py-1 text-cyan-700">┄ Aéreo</span></div></div>
                    <div id="loretoRoutesMap" class="h-[360px] w-full bg-slate-100"></div>
                </article>
                <article class="rounded-xl border border-slate-200 p-4"><h4 class="text-xs font-black text-slate-900">Pasajes por modalidad</h4><p class="text-[9px] text-slate-400">Comparación del período seleccionado</p><div class="h-36"><canvas id="ticketModalityChart"></canvas></div><h4 class="mt-4 border-t pt-4 text-xs font-black text-slate-900">Empresas con más pasajes</h4><div class="h-36"><canvas id="operatorTicketsChart"></canvas></div></article>
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200"><div class="flex justify-between bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Ventas por empresa</h4><p class="text-[9px] text-slate-400">Pasajes, GMV y comisión</p></div><a href="{{ route('admin.sales.index') }}" class="text-[9px] font-black text-emerald-700">Ver todas →</a></div><table class="w-full text-left text-[10px]"><thead class="border-y bg-white uppercase text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3 text-center">Pasajes</th><th class="p-3 text-right">GMV</th></tr></thead><tbody class="divide-y">@forelse($operatorRanking as $operator)<tr><td class="p-3"><a href="{{ route('admin.companies.show', $operator['id']) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $operator['name'] }}</a><span class="block text-[8px] text-slate-400">{{ $operator['modality'] }}</span></td><td class="p-3 text-center font-black">{{ $operator['tickets'] }}</td><td class="p-3 text-right font-black">S/ {{ number_format($operator['sales'], 2) }}</td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-400">Sin ventas en el período.</td></tr>@endforelse</tbody></table></div>
                <div class="overflow-hidden rounded-xl border border-slate-200"><div class="flex justify-between bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Rutas con mayor demanda</h4><p class="text-[9px] text-slate-400">Tramos ordenados por pasajes</p></div><a href="{{ route('admin.master-routes.index') }}" class="text-[9px] font-black text-emerald-700">Ver catálogo →</a></div><table class="w-full text-left text-[10px]"><thead class="border-y bg-white uppercase text-slate-400"><tr><th class="p-3">Ruta</th><th class="p-3">Modo</th><th class="p-3 text-center">Pasajes</th></tr></thead><tbody class="divide-y">@forelse($topRoutes as $route)<tr><td class="p-3 font-black text-slate-900">{{ $route['name'] }}</td><td class="p-3">{{ $route['type'] === 'Aéreo' ? '✈️' : '🚤' }} {{ $route['type'] }}</td><td class="p-3 text-center font-black">{{ $route['tickets'] }}</td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-400">Sin demanda registrada.</td></tr>@endforelse</tbody></table></div>
            </div>
        </div>

        <div x-show="tab==='empresas'" x-cloak class="grid gap-4 p-4 xl:grid-cols-[1fr_1.4fr]">
            <div><div class="grid grid-cols-2 gap-2">@foreach([['Activas',$activeOperatorsCount],['Pendientes',$pendingOperatorsCount],['Rechazadas',$rejectedOperatorsCount],['Unidades',$registeredVesselsCount+$registeredAircraftCount]] as $item)<a href="{{ route('admin.companies.index') }}" class="rounded-xl border border-slate-200 p-3 hover:border-emerald-300"><span class="text-[9px] font-bold uppercase text-slate-400">{{ $item[0] }}</span><strong class="mt-1 block text-xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[8px] font-bold text-emerald-700">Ver detalle →</span></a>@endforeach</div><div class="mt-4 grid gap-3 sm:grid-cols-2"><div><h4 class="text-[10px] font-black text-slate-800">Afiliaciones por mes</h4><div class="h-44"><canvas id="affiliationsChart"></canvas></div></div><div><h4 class="text-[10px] font-black text-slate-800">Flota por modalidad</h4><div class="h-44"><canvas id="fleetChart"></canvas></div></div></div></div>
            <div class="overflow-hidden rounded-xl border border-slate-200"><div class="flex justify-between bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Flota registrada por empresa</h4><p class="text-[9px] text-slate-400">{{ $registeredVesselsCount }} embarcaciones · {{ $registeredAircraftCount }} aeronaves</p></div><a href="{{ route('admin.companies.index') }}" class="text-[9px] font-black text-emerald-700">Ver expedientes →</a></div><div class="max-h-72 overflow-auto"><table class="w-full text-left text-[10px]"><thead class="sticky top-0 bg-white uppercase text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3 text-center">🚤</th><th class="p-3 text-center">✈️</th><th class="p-3 text-center">Total</th></tr></thead><tbody class="divide-y">@forelse($fleetByOperator as $operator)<tr><td class="p-3"><a href="{{ route('admin.companies.show', $operator['id']) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $operator['name'] }}</a></td><td class="p-3 text-center">{{ $operator['vessels'] }}</td><td class="p-3 text-center">{{ $operator['aircraft'] }}</td><td class="p-3 text-center font-black">{{ $operator['total'] }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-400">Sin flota registrada.</td></tr>@endforelse</tbody></table></div></div>
        </div>

        <div x-show="tab==='operacion'" x-cloak class="space-y-4 p-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach([['Salidas canceladas hoy',$cancelledDeparturesToday,'Requieren atención','⛔'],['Canceladas en período',$cancelledDeparturesInPeriod,'Fluviales y aéreas','📅'],['Reprogramaciones',$rescheduledDeparturesInPeriod,'Cambios comunicados','🔄'],['Alertas técnicas',$technicalAlerts,'DICAPI / DGAC','🛡️']] as $item)<a href="{{ route('admin.itineraries.index') }}" class="rounded-xl border border-slate-200 p-4 hover:border-emerald-300"><span class="text-2xl">{{ $item[3] }}</span><strong class="mt-3 block text-2xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[10px] font-black text-slate-700">{{ $item[0] }}</span><p class="mt-1 text-[9px] text-slate-400">{{ $item[2] }}</p></a>@endforeach</div>
            <div class="grid gap-4 rounded-xl bg-slate-50 p-4 lg:grid-cols-[1.4fr_1fr]"><div><h4 class="text-xs font-black text-slate-900">Incidencias operativas del período</h4><p class="text-[9px] text-slate-400">Cancelaciones, reprogramaciones y alertas técnicas</p><div class="h-48"><canvas id="operationsChart"></canvas></div></div><div class="flex items-center justify-between rounded-xl border border-cyan-200 bg-white p-4"><div><span class="text-[9px] font-black uppercase text-cyan-700">Apoyo para decisiones</span><h4 class="mt-1 text-sm font-black">Clima y navegabilidad</h4><p class="mt-1 text-[10px] text-slate-500">Revisa las condiciones antes de evaluar una incidencia.</p><a href="{{ route('weather.index') }}" target="_blank" class="mt-3 inline-flex text-[10px] font-black text-cyan-800">Abrir monitoreo →</a></div><span class="text-4xl">🌦️</span></div></div>
        </div>

        <div x-show="tab==='carga'" x-cloak class="grid gap-4 p-4 lg:grid-cols-[1fr_1.2fr]">
            <div class="grid grid-cols-2 gap-3">@foreach([['Envíos registrados',$cargoShipmentsCount,'Guías del período','📦'],['Remitentes únicos',$cargoCustomersCount,'Personas que enviaron','👤'],['Paquetes movilizados',$cargoPackagesCount,'Bultos declarados','▣'],['Ingreso por carga','S/ '.number_format($cargoRevenue,2),'Operaciones pagadas','💰']] as $item)<a href="{{ route('admin.cargo.index') }}" class="rounded-xl border border-slate-200 p-4 hover:border-emerald-300"><span class="text-xl">{{ $item[3] }}</span><strong class="mt-2 block text-xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[9px] font-black text-slate-700">{{ $item[0] }}</span><p class="text-[8px] text-slate-400">{{ $item[2] }}</p></a>@endforeach</div><div class="rounded-xl bg-slate-50 p-4"><h4 class="text-xs font-black text-slate-900">Actividad de carga</h4><p class="text-[9px] text-slate-400">Envíos, remitentes y paquetes en el período</p><div class="h-56"><canvas id="cargoChart"></canvas></div></div>
        </div>

        <div x-show="tab==='publicidad'" x-cloak class="space-y-4 bg-fuchsia-50/40 p-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['Anunciantes activos',$activeAdvertisersCount,'Negocios con pauta vigente','📣','border-fuchsia-200 bg-fuchsia-50'],
                    ['Campañas activas',$activeCampaignsCount,'Piezas publicadas','🟣','border-violet-200 bg-violet-50'],
                    ['Solicitudes pendientes',$pendingAdLeadsCount,'Requieren evaluación','⏳','border-amber-200 bg-amber-50'],
                    ['Ingreso mensual','S/ '.number_format($monthlyAdvertisingRevenue,2),'Tarifa mensual contratada','💰','border-emerald-200 bg-emerald-50']
                ] as $item)
                    <a href="{{ route('admin.advertisements.index') }}" class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md {{ $item[4] }}"><span class="text-xl">{{ $item[3] }}</span><strong class="mt-2 block text-xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[9px] font-black text-slate-700">{{ $item[0] }}</span><p class="text-[8px] text-slate-500">{{ $item[2] }}</p></a>
                @endforeach
            </div>
            <div class="grid gap-4 xl:grid-cols-[1fr_1fr_1.1fr]">
                <article class="rounded-xl border border-violet-200 bg-white p-4"><h4 class="text-xs font-black text-slate-900">Contratos por plan</h4><p class="text-[9px] text-slate-400">Distribución según ubicaciones contratadas</p><div class="h-52"><canvas id="advertisingPlansChart"></canvas></div></article>
                <article class="rounded-xl border border-fuchsia-200 bg-white p-4"><h4 class="text-xs font-black text-slate-900">Estado de campañas</h4><p class="text-[9px] text-slate-400">Activas, pendientes, pausadas y vencidas</p><div class="h-52"><canvas id="advertisingPipelineChart"></canvas></div></article>
                <article class="rounded-xl bg-slate-950 p-4 text-white">
                    <p class="text-[9px] font-black uppercase tracking-wider text-fuchsia-300">Rendimiento publicitario</p>
                    <div class="mt-3 grid grid-cols-2 gap-2"><div class="rounded-xl bg-white/10 p-3"><span class="text-[8px] text-slate-300">Impresiones</span><strong class="block text-xl font-black">{{ number_format($advertisingViews) }}</strong></div><div class="rounded-xl bg-white/10 p-3"><span class="text-[8px] text-slate-300">Clics</span><strong class="block text-xl font-black">{{ number_format($advertisingClicks) }}</strong></div></div>
                    <div class="mt-3 rounded-xl bg-fuchsia-500/20 p-3"><span class="text-[8px] font-bold uppercase text-fuchsia-200">Tasa de clics (CTR)</span><strong class="mt-1 block text-2xl font-black">{{ number_format($advertisingCtr,1) }}%</strong></div>
                    <p class="mt-3 text-[9px] leading-relaxed text-slate-300">Los planes se clasifican por cobertura: Esencial usa 1 ubicación, Destacado 2 y Cobertura total 3 o más.</p>
                    <a href="{{ route('admin.advertisements.index') }}" class="mt-3 inline-flex rounded-lg bg-fuchsia-400 px-3 py-2 text-[9px] font-black text-fuchsia-950">Abrir gestión de anuncios →</a>
                </article>
            </div>
        </div>

    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
window.dashboardData = {
    chartLabels: @json($chartLabels), fluvialSeries: @json($fluvialSeries), airSeries: @json($airSeries), cargoSeries: @json($cargoSeries),
    modalityValues: @json($modalityValues), affiliationLabels: @json($affiliationLabels), affiliationSeries: @json($affiliationSeries),
    operatorNames: @json($operatorRanking->pluck('name')), operatorTickets: @json($operatorRanking->pluck('tickets')),
    fleetNames: @json($fleetByOperator->take(8)->pluck('name')), vesselSeries: @json($fleetByOperator->take(8)->pluck('vessels')), aircraftSeries: @json($fleetByOperator->take(8)->pluck('aircraft')),
    routes: @json($mapRoutes), tickets: [{{ $fluvialTicketsSold }}, {{ $airTicketsSold }}], operations: [{{ $cancelledDeparturesInPeriod }}, {{ $rescheduledDeparturesInPeriod }}, {{ $technicalAlerts }}],
    cargo: [{{ $cargoShipmentsCount }}, {{ $cargoCustomersCount }}, {{ $cargoPackagesCount }}],
    advertisingPlans: @json($advertisingPlans->pluck('count')->values()),
    advertisingPipeline: @json($advertisingPipeline)
};
@php
    $dashboardSummary = [
        ['Indicador', 'Valor'],
        ['Período', $from->format('d/m/Y').' - '.$to->format('d/m/Y')],
        ['Ventas procesadas', $grossSales],
        ['Comisión NavegaYA', $netCommission],
        ['Pasajes fluviales', $fluvialTicketsSold],
        ['Pasajes aéreos', $airTicketsSold],
        ['Pasajes cancelados', $cancelledTickets],
        ['Empresas registradas', $totalOperatorsCount],
        ['Empresas pendientes', $pendingOperatorsCount],
        ['Empresas rechazadas', $rejectedOperatorsCount],
        ['Envíos de carga', $cargoShipmentsCount],
        ['Anunciantes activos', $activeAdvertisersCount],
        ['Campañas publicitarias activas', $activeCampaignsCount],
        ['Ingreso mensual por publicidad', $monthlyAdvertisingRevenue],
    ];
@endphp
window.dashboardSummary = @json($dashboardSummary);
window.downloadDashboardCsv = function () { const csv = window.dashboardSummary.map(row => row.map(value => '"'+String(value).replaceAll('"','""')+'"').join(';')).join('\n'); const blob = new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='navegaya-resumen-{{ $from->format('Ymd') }}-{{ $to->format('Ymd') }}.csv'; a.click(); URL.revokeObjectURL(a.href); };
window.nyCharts = window.nyCharts || {};
const baseOptions = {responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}};
window.makeChart = function(id, config) { const el=document.getElementById(id); if(!el) return; if(window.nyCharts[id]) window.nyCharts[id].destroy(); window.nyCharts[id]=new Chart(el,config); };
window.initDashboardTab = function(tab) {
    const d=window.dashboardData;
    if(typeof Chart==='undefined') return;
    if(tab==='resumen') {
        window.makeChart('revenueTrendChart',{type:'line',data:{labels:d.chartLabels,datasets:[{label:'Fluvial',data:d.fluvialSeries,borderColor:'#059669',backgroundColor:'rgba(5,150,105,.08)',fill:true,tension:.35},{label:'Aéreo',data:d.airSeries,borderColor:'#0891b2',backgroundColor:'rgba(8,145,178,.05)',fill:true,tension:.35},{label:'Carga',data:d.cargoSeries,borderColor:'#d97706',backgroundColor:'rgba(217,119,6,.05)',fill:true,tension:.35}]},options:{...baseOptions,interaction:{mode:'index',intersect:false},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}}}});
        window.makeChart('modalityChart',{type:'doughnut',data:{labels:['Fluvial','Aéreo','Carga'],datasets:[{data:d.modalityValues.some(Number)?d.modalityValues:[1],backgroundColor:d.modalityValues.some(Number)?['#059669','#0891b2','#d97706']:['#cbd5e1'],borderWidth:0}]},options:{...baseOptions,cutout:'72%'}});
    }
    if(tab==='ventas') { window.makeChart('ticketModalityChart',{type:'bar',data:{labels:['Fluvial','Aéreo'],datasets:[{data:d.tickets,backgroundColor:['#059669','#0891b2'],borderRadius:7}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}}); window.makeChart('operatorTicketsChart',{type:'bar',data:{labels:d.operatorNames,datasets:[{data:d.operatorTickets,backgroundColor:'#0f766e',borderRadius:6}]},options:{...baseOptions,indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{precision:0}},y:{grid:{display:false}}}}}); window.initLoretoMap(); }
    if(tab==='empresas') { window.makeChart('affiliationsChart',{type:'bar',data:{labels:d.affiliationLabels,datasets:[{data:d.affiliationSeries,backgroundColor:'#10b981',borderRadius:5}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}}); window.makeChart('fleetChart',{type:'doughnut',data:{labels:['Embarcaciones','Aeronaves'],datasets:[{data:[{{ $registeredVesselsCount }},{{ $registeredAircraftCount }}],backgroundColor:['#059669','#0891b2'],borderWidth:0}]},options:{...baseOptions,cutout:'65%',plugins:{legend:{display:true,position:'bottom',labels:{boxWidth:8,font:{size:9}}}}}}); }
    if(tab==='operacion') window.makeChart('operationsChart',{type:'bar',data:{labels:['Canceladas','Reprogramadas','Alertas técnicas'],datasets:[{data:d.operations,backgroundColor:['#f43f5e','#f59e0b','#0ea5e9'],borderRadius:7}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}});
    if(tab==='carga') window.makeChart('cargoChart',{type:'bar',data:{labels:['Envíos','Remitentes','Paquetes'],datasets:[{data:d.cargo,backgroundColor:['#0f766e','#0891b2','#d97706'],borderRadius:8}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}});
    if(tab==='publicidad') {
        window.makeChart('advertisingPlansChart',{type:'bar',data:{labels:['Esencial','Destacado','Cobertura total'],datasets:[{data:d.advertisingPlans,backgroundColor:['#38bdf8','#8b5cf6','#f59e0b'],borderRadius:8}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}});
        window.makeChart('advertisingPipelineChart',{type:'doughnut',data:{labels:['Activas','Pendientes','Pausadas','Vencidas'],datasets:[{data:d.advertisingPipeline.some(Number)?d.advertisingPipeline:[1],backgroundColor:d.advertisingPipeline.some(Number)?['#10b981','#f59e0b','#8b5cf6','#94a3b8']:['#cbd5e1'],borderWidth:0}]},options:{...baseOptions,cutout:'65%',plugins:{legend:{display:true,position:'bottom',labels:{boxWidth:8,font:{size:9}}}}}});
    }
};
window.initLoretoMap = function() {
    if(typeof L==='undefined') return; const el=document.getElementById('loretoRoutesMap'); if(!el) return;
    if(window.nyLoretoMap){setTimeout(()=>window.nyLoretoMap.invalidateSize(),50);return;}
    const map=L.map(el,{zoomControl:true}).setView([-4.45,-74.3],6); window.nyLoretoMap=map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'© OpenStreetMap'}).addTo(map);
    const bounds=[]; window.dashboardData.routes.forEach(route=>{if(!route.origin_coords||!route.destination_coords)return; const color=route.type==='Aéreo'?'#0891b2':'#059669'; const weight=Math.min(9,3+Number(route.tickets||0)/10); const line=L.polyline([route.origin_coords,route.destination_coords],{color,weight,dashArray:route.type==='Aéreo'?'8 7':null,opacity:.85}).addTo(map); line.bindPopup(`<strong>${route.type==='Aéreo'?'✈️':'🚤'} ${route.origin} → ${route.destination}</strong><br>${route.operator}<br><b>${route.tickets}</b> pasajes · S/ ${Number(route.sales).toFixed(2)}`); bounds.push(route.origin_coords,route.destination_coords); }); if(bounds.length) map.fitBounds(bounds,{padding:[25,25],maxZoom:8}); else L.popup().setLatLng([-3.75,-73.25]).setContent('Registra coordenadas en Puertos y Aeródromos para visualizar las rutas.').openOn(map); setTimeout(()=>map.invalidateSize(),100);
};
document.addEventListener('DOMContentLoaded',()=>window.initDashboardTab('resumen'));
document.addEventListener('turbo:load',()=>window.initDashboardTab('resumen'));
</script>
@endsection
