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
<div x-data="{ tab: 'resumen' }" class="w-full space-y-4 text-slate-800">
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
                <summary class="flex h-9 cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[10px] font-bold text-slate-700">📅 {{ $from->format('d/m') }}–{{ $to->format('d/m/Y') }} ▾</summary>
                <form method="GET" action="{{ route('admin.dashboard') }}" class="absolute right-0 z-40 mt-2 grid w-72 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
                    <input type="hidden" name="period" value="custom">
                    <label class="text-[9px] font-black uppercase text-slate-400">Desde<input type="date" name="from" value="{{ request('from', $from->toDateString()) }}" required class="mt-1 h-9 w-full rounded-lg border-slate-200 bg-slate-50 text-xs"></label>
                    <label class="text-[9px] font-black uppercase text-slate-400">Hasta<input type="date" name="to" value="{{ request('to', $to->toDateString()) }}" required class="mt-1 h-9 w-full rounded-lg border-slate-200 bg-slate-50 text-xs"></label>
                    <button class="h-9 rounded-lg bg-emerald-700 text-xs font-black text-white">Aplicar rango</button>
                </form>
            </details>
            <button type="button" onclick="window.print()" class="h-9 rounded-xl bg-amber-400 px-4 text-[10px] font-black text-slate-950 hover:bg-amber-300">Exportar</button>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @php
            $kpis = [
                ['GMV del período', 'S/ '.number_format($grossSales, 2), 'Ventas procesadas', '💳', 'admin.sales.index', 'slate'],
                ['Comisión generada', 'S/ '.number_format($netCommission, 2), 'Ingreso NavegaYA', '↗', 'admin.commissions.index', 'emerald'],
                ['Pasajes vendidos', number_format($totalTicketsSold), 'Fluvial '.$fluvialTicketsSold.' · Aéreo '.$airTicketsSold, '🎟️', 'admin.sales.index', 'sky'],
                ['Pasajes cancelados', number_format($cancelledTickets), 'En el período', '↩', 'admin.refunds.index', 'rose'],
                ['Empresas registradas', number_format($totalOperatorsCount), $activeOperatorsCount.' activas', '🏢', 'admin.companies.index', 'violet'],
                ['Nuevas afiliaciones', number_format($newOperatorsInPeriod), $pendingOperatorsCount.' por aprobar', '＋', 'admin.companies.index', 'amber'],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <a href="{{ route($kpi[4]) }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-2"><span class="text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $kpi[0] }}</span><span class="text-base">{{ $kpi[3] }}</span></div>
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

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div><h3 class="text-sm font-black text-slate-950">Análisis ejecutivo</h3><p class="text-[10px] text-slate-400">Selecciona una perspectiva para revisar indicadores y acceder al detalle.</p></div>
            <nav class="flex max-w-full gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1">
                @foreach(['resumen' => 'Resumen', 'ventas' => 'Ventas y rutas', 'empresas' => 'Empresas y flota', 'operacion' => 'Operación', 'carga' => 'Carga'] as $key => $label)
                    <button type="button" @click="tab='{{ $key }}'" :class="tab==='{{ $key }}' ? 'bg-white text-emerald-800 shadow-sm' : 'text-slate-500 hover:text-slate-900'" class="whitespace-nowrap rounded-lg px-3 py-2 text-[10px] font-black transition">{{ $label }}</button>
                @endforeach
            </nav>
        </div>

        <div x-show="tab==='resumen'" class="grid gap-4 p-4 lg:grid-cols-3">
            <div class="lg:col-span-2"><div class="flex justify-between"><div><h4 class="text-xs font-black uppercase text-slate-900">Evolución del GMV</h4><p class="text-[10px] text-slate-400">Fluvial, aéreo y carga</p></div><div class="flex gap-3 text-[9px] font-bold"><span class="text-emerald-700">● Fluvial</span><span class="text-cyan-700">● Aéreo</span><span class="text-amber-600">● Carga</span></div></div><div class="h-64 pt-3"><canvas id="revenueTrendChart"></canvas></div></div>
            <div><h4 class="text-xs font-black uppercase text-slate-900">Distribución comercial</h4><p class="text-[10px] text-slate-400">Participación por modalidad</p><div class="mx-auto h-44 max-w-56 py-3"><canvas id="modalityChart"></canvas></div><div class="grid grid-cols-3 gap-1 text-center text-[9px] font-bold"><span class="rounded-lg bg-emerald-50 p-2 text-emerald-700">Fluvial<br>{{ $modalityPercentages[0] }}%</span><span class="rounded-lg bg-cyan-50 p-2 text-cyan-700">Aéreo<br>{{ $modalityPercentages[1] }}%</span><span class="rounded-lg bg-amber-50 p-2 text-amber-700">Carga<br>{{ $modalityPercentages[2] }}%</span></div></div>
        </div>

        <div x-show="tab==='ventas'" x-cloak class="grid gap-4 p-4 xl:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-slate-200"><div class="flex justify-between bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Ventas por empresa</h4><p class="text-[9px] text-slate-400">Pasajes, GMV y comisión</p></div><a href="{{ route('admin.sales.index') }}" class="text-[9px] font-black text-emerald-700">Todas →</a></div><table class="w-full text-left text-[10px]"><thead class="border-y bg-white uppercase text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3 text-center">Pasajes</th><th class="p-3 text-right">GMV</th></tr></thead><tbody class="divide-y">@forelse($operatorRanking as $operator)<tr><td class="p-3"><a href="{{ route('admin.companies.show', $operator['id']) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $operator['name'] }}</a><span class="block text-[8px] text-slate-400">{{ $operator['modality'] }}</span></td><td class="p-3 text-center font-black">{{ $operator['tickets'] }}</td><td class="p-3 text-right font-black">S/ {{ number_format($operator['sales'], 2) }}</td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-400">Sin ventas en el período.</td></tr>@endforelse</tbody></table></div>
            <div class="overflow-hidden rounded-xl border border-slate-200"><div class="flex justify-between bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Rutas con mayor demanda</h4><p class="text-[9px] text-slate-400">Tramos ordenados por pasajes</p></div><a href="{{ route('admin.master-routes.index') }}" class="text-[9px] font-black text-emerald-700">Catálogo →</a></div><table class="w-full text-left text-[10px]"><thead class="border-y bg-white uppercase text-slate-400"><tr><th class="p-3">Ruta</th><th class="p-3">Modo</th><th class="p-3 text-center">Pasajes</th></tr></thead><tbody class="divide-y">@forelse($topRoutes as $route)<tr><td class="p-3 font-black text-slate-900">{{ $route['name'] }}</td><td class="p-3">{{ $route['type'] === 'Aéreo' ? '✈️' : '🚤' }} {{ $route['type'] }}</td><td class="p-3 text-center font-black">{{ $route['tickets'] }}</td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-400">Sin demanda registrada.</td></tr>@endforelse</tbody></table></div>
        </div>

        <div x-show="tab==='empresas'" x-cloak class="grid gap-4 p-4 xl:grid-cols-[1fr_1.6fr]">
            <div><div class="grid grid-cols-2 gap-2">@foreach([['Activas',$activeOperatorsCount,'emerald'],['Pendientes',$pendingOperatorsCount,'amber'],['Rechazadas',$rejectedOperatorsCount,'rose'],['Unidades',$registeredVesselsCount+$registeredAircraftCount,'sky']] as $item)<a href="{{ route('admin.companies.index') }}" class="rounded-xl border border-slate-200 p-3"><span class="text-[9px] font-bold uppercase text-slate-400">{{ $item[0] }}</span><strong class="mt-1 block text-xl font-black text-slate-950">{{ $item[1] }}</strong></a>@endforeach</div><div class="mt-4 h-48"><canvas id="affiliationsChart"></canvas></div></div>
            <div class="overflow-hidden rounded-xl border border-slate-200"><div class="flex justify-between bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Flota registrada por empresa</h4><p class="text-[9px] text-slate-400">{{ $registeredVesselsCount }} embarcaciones · {{ $registeredAircraftCount }} aeronaves</p></div><a href="{{ route('admin.companies.index') }}" class="text-[9px] font-black text-emerald-700">Expedientes →</a></div><div class="max-h-64 overflow-auto"><table class="w-full text-left text-[10px]"><thead class="sticky top-0 bg-white uppercase text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3 text-center">🚤</th><th class="p-3 text-center">✈️</th><th class="p-3 text-center">Total</th></tr></thead><tbody class="divide-y">@forelse($fleetByOperator as $operator)<tr><td class="p-3"><a href="{{ route('admin.companies.show', $operator['id']) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $operator['name'] }}</a></td><td class="p-3 text-center">{{ $operator['vessels'] }}</td><td class="p-3 text-center">{{ $operator['aircraft'] }}</td><td class="p-3 text-center font-black">{{ $operator['total'] }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-400">Sin flota registrada.</td></tr>@endforelse</tbody></table></div></div>
        </div>

        <div x-show="tab==='operacion'" x-cloak class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Salidas canceladas hoy', $cancelledDeparturesToday, 'Requieren atención inmediata', '⛔'],
                ['Canceladas en período', $cancelledDeparturesInPeriod, 'Fluviales y aéreas', '📅'],
                ['Reprogramaciones', $rescheduledDeparturesInPeriod, 'Cambios comunicados', '🔄'],
                ['Alertas técnicas', $technicalAlerts, 'DICAPI / DGAC', '🛡️'],
            ] as $item)<a href="{{ route('admin.itineraries.index') }}" class="rounded-xl border border-slate-200 p-4 hover:border-emerald-300"><span class="text-2xl">{{ $item[3] }}</span><strong class="mt-3 block text-2xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[10px] font-black text-slate-700">{{ $item[0] }}</span><p class="mt-1 text-[9px] text-slate-400">{{ $item[2] }}</p></a>@endforeach
        </div>

        <div x-show="tab==='carga'" x-cloak class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Envíos registrados', $cargoShipmentsCount, 'Guías del período', '📦'],
                ['Remitentes únicos', $cargoCustomersCount, 'Usuarios que enviaron carga', '👤'],
                ['Paquetes movilizados', $cargoPackagesCount, 'Bultos declarados', '▣'],
                ['Ingreso por carga', 'S/ '.number_format($cargoRevenue, 2), 'Operaciones pagadas', '💰'],
            ] as $item)<a href="{{ route('admin.cargo.index') }}" class="rounded-xl border border-slate-200 p-4 hover:border-emerald-300"><span class="text-2xl">{{ $item[3] }}</span><strong class="mt-3 block text-2xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[10px] font-black text-slate-700">{{ $item[0] }}</span><p class="mt-1 text-[9px] text-slate-400">{{ $item[2] }}</p></a>@endforeach
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
window.initAdminDashboardCharts = function () {
    if (typeof Chart === 'undefined') return;
    ['nyRevenueTrendChart','nyModalityChart','nyAffiliationsChart'].forEach(key => { if (window[key]) window[key].destroy(); });
    const common = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} } };
    const revenue = document.getElementById('revenueTrendChart');
    if (revenue) window.nyRevenueTrendChart = new Chart(revenue,{type:'line',data:{labels:@json($chartLabels),datasets:[{label:'Fluvial',data:@json($fluvialSeries),borderColor:'#059669',backgroundColor:'rgba(5,150,105,.08)',fill:true,tension:.35},{label:'Aéreo',data:@json($airSeries),borderColor:'#0891b2',backgroundColor:'rgba(8,145,178,.05)',fill:true,tension:.35},{label:'Carga',data:@json($cargoSeries),borderColor:'#d97706',backgroundColor:'rgba(217,119,6,.05)',fill:true,tension:.35}]},options:{...common,interaction:{mode:'index',intersect:false},scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{font:{size:9},callback:v=>'S/ '+v}},x:{grid:{display:false},ticks:{font:{size:9}}}}}});
    const modality = document.getElementById('modalityChart');
    if (modality) window.nyModalityChart = new Chart(modality,{type:'doughnut',data:{labels:['Fluvial','Aéreo','Carga'],datasets:[{data:@json($modalityValues),backgroundColor:['#059669','#0891b2','#d97706'],borderWidth:0}]},options:{...common,cutout:'74%'}});
    const affiliations = document.getElementById('affiliationsChart');
    if (affiliations) window.nyAffiliationsChart = new Chart(affiliations,{type:'bar',data:{labels:@json($affiliationLabels),datasets:[{data:@json($affiliationSeries),backgroundColor:'#10b981',borderRadius:5}]},options:{...common,scales:{y:{beginAtZero:true,ticks:{precision:0,font:{size:8}},grid:{color:'#f1f5f9'}},x:{grid:{display:false},ticks:{font:{size:8}}}}}});
};
document.addEventListener('DOMContentLoaded', window.initAdminDashboardCharts);
document.addEventListener('turbo:load', window.initAdminDashboardCharts);
</script>
@endsection
