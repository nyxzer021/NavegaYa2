@extends('layouts.admin')

@section('title', 'Dashboard Gerencial · NavegaYA')
@section('header')
<div>
    <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">Inteligencia comercial</p>
    <h1 class="ny-page-title">Dashboard Gerencial</h1>
</div>
@endsection

@section('content')
<div x-data="{ tab: 'resumen' }" class="mx-auto max-w-[1600px] space-y-4 pb-8 text-slate-800">

    <header class="relative overflow-hidden rounded-[1.75rem] bg-[#062c21] px-5 py-5 text-white shadow-xl sm:px-6">
        <div class="absolute -right-24 -top-28 h-72 w-72 rounded-full bg-emerald-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="max-w-xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1 text-[10px] font-black uppercase tracking-[.14em] text-emerald-200">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                        Marketplace operativo
                    </span>
                    <span class="text-[10px] font-semibold text-emerald-100/55">Actualizado {{ now()->format('d/m/Y · H:i') }}</span>
                </div>
                <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Control comercial de Loreto</h2>
                <p class="mt-1 text-xs text-emerald-100/65">Ventas del marketplace, comisiones y salud de la red de operadores.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <nav class="flex rounded-xl border border-white/10 bg-black/15 p-1 text-[11px] font-black">
                    @foreach(['today' => 'Hoy', 'week' => '7 días', 'month' => 'Este mes'] as $key => $label)
                        <a href="{{ route('admin.dashboard', ['period' => $key]) }}" class="rounded-lg px-3 py-2 transition {{ $period === $key ? 'bg-white text-[#062c21] shadow' : 'text-emerald-100/70 hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </nav>

                <details class="relative">
                    <summary class="flex h-9 cursor-pointer list-none items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 text-[11px] font-bold text-emerald-50 hover:bg-white/10">
                        📅 {{ $from->format('d/m') }}–{{ $to->format('d/m/Y') }} <span class="opacity-50">⌄</span>
                    </summary>
                    <form method="GET" action="{{ route('admin.dashboard') }}" class="absolute right-0 z-40 mt-2 grid w-72 gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-slate-800 shadow-2xl">
                        <input type="hidden" name="period" value="custom">
                        <label class="text-[9px] font-black uppercase text-slate-400">Desde<input type="date" name="from" value="{{ request('from', $from->toDateString()) }}" required class="mt-1 h-9 w-full rounded-lg border-slate-200 bg-slate-50 text-xs"></label>
                        <label class="text-[9px] font-black uppercase text-slate-400">Hasta<input type="date" name="to" value="{{ request('to', $to->toDateString()) }}" required class="mt-1 h-9 w-full rounded-lg border-slate-200 bg-slate-50 text-xs"></label>
                        <button class="h-9 rounded-lg bg-amber-500 text-xs font-black text-slate-950">Aplicar rango</button>
                    </form>
                </details>

                <button type="button" onclick="window.print()" class="h-9 rounded-xl bg-amber-500 px-4 text-[11px] font-black text-slate-950 hover:bg-amber-400">Exportar</button>
            </div>
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['GMV marketplace', $grossSales, 'Ventas generadas por operadores', 'S/', 'border-slate-200 bg-white text-slate-950'],
            ['Comisión generada', $netCommission, 'Según acuerdos comerciales', '↗', 'border-emerald-300 bg-emerald-50/60 text-emerald-800'],
            ['Comisión cobrada', $commissionCollected, 'Ingreso confirmado NavegaYA', '✓', 'border-cyan-200 bg-cyan-50/50 text-cyan-800'],
            ['Por cobrar', $commissionReceivable, 'Pendiente de facturación', '→', 'border-amber-200 bg-amber-50/50 text-amber-700'],
        ] as $metric)
            <article class="rounded-2xl border p-4 shadow-sm {{ $metric[4] }}">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-black uppercase tracking-[.12em] opacity-65">{{ $metric[0] }}</span>
                    <span class="text-sm font-black opacity-40">{{ $metric[3] }}</span>
                </div>
                <strong class="mt-2 block text-2xl font-black">S/ {{ number_format((float) $metric[1], 2) }}</strong>
                <span class="mt-1 block text-[10px] font-semibold opacity-65">{{ $metric[2] }}</span>
            </article>
        @endforeach

        <article class="rounded-2xl border border-sky-200 bg-sky-50/50 p-4 text-slate-950 shadow-sm">
            <div class="flex items-center justify-between"><span class="text-[9px] font-black uppercase tracking-[.12em] text-sky-700">Boletos emitidos hoy</span><span class="text-base">🎟️</span></div>
            <strong class="mt-2 block text-2xl font-black">{{ number_format($todaysTicketsCount) }} <small class="text-xs font-bold text-slate-400">pasajes</small></strong>
            <span class="mt-1 block text-[10px] font-semibold text-slate-500">Ocupación {{ number_format($todayOccupancyRate, 1) }}%</span>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid md:grid-cols-[1.25fr_1fr]">
            <div class="border-b border-slate-100 p-4 md:border-b-0 md:border-r">
                <div class="flex items-center justify-between">
                    <div><p class="text-[9px] font-black uppercase tracking-[.13em] text-emerald-700">Monetización contractual</p><h3 class="text-sm font-black text-slate-900">Comisiones generadas por ventas</h3></div>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black text-emerald-700">Sin custodia de fondos</span>
                </div>
                <div class="mt-3 grid grid-cols-3 divide-x divide-slate-100">
                    @foreach([
                        ['Hoy', $profitToday ?? 0],
                        [ucfirst(now()->translatedFormat('F')), $profitMonth ?? 0],
                        [(string) now()->year, $profitYear ?? 0],
                    ] as $profit)
                        <div class="px-3 first:pl-0"><span class="text-[9px] font-bold uppercase text-slate-400">{{ $profit[0] }}</span><strong class="mt-1 block text-lg font-black text-slate-900">S/ {{ number_format((float) $profit[1], 2) }}</strong></div>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-5 divide-x divide-slate-100">
                @foreach([
                    ['🕒', 'Salidas', $todaysDeparturesCount],
                    ['🚤', 'En río', $riverUnitsInTransit],
                    ['✈️', 'En vuelo', $airUnitsInFlight],
                    ['🛡️', 'Alertas', $technicalAlerts],
                    ['🏢', 'Empresas', $activeOperatorsCount],
                ] as $pulse)
                    <div class="flex min-w-0 flex-col items-center justify-center px-1 py-4 text-center"><span class="text-lg">{{ $pulse[0] }}</span><strong class="mt-1 text-sm font-black text-slate-900">{{ $pulse[2] }}</strong><span class="text-[8px] font-bold uppercase text-slate-400">{{ $pulse[1] }}</span></div>
                @endforeach
            </div>
        </div>
    </section>

    <nav class="grid grid-cols-2 gap-1 rounded-2xl border border-slate-200 bg-slate-100 p-1.5 sm:grid-cols-4">
        @foreach([
            'resumen' => ['📈', 'Ventas'],
            'operadores' => ['🏢', 'Operadores'],
            'rutas' => ['🧭', 'Corredores'],
            'canales' => ['💳', 'Canales'],
        ] as $key => $item)
            <button type="button" @click="tab='{{ $key }}'" :class="tab==='{{ $key }}' ? 'bg-white text-[#062c21] shadow-sm' : 'text-slate-500 hover:text-slate-900'" class="rounded-xl px-3 py-2.5 text-[11px] font-black transition">{{ $item[0] }} {{ $item[1] }}</button>
        @endforeach
    </nav>

    <section x-show="tab==='resumen'" class="grid gap-4 lg:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3"><div><h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Evolución de ingresos</h3><p class="text-[10px] text-slate-400">GMV por unidad de negocio</p></div><div class="flex gap-3 text-[9px] font-black"><span class="text-emerald-700">● Fluvial</span><span class="text-cyan-700">● Aéreo</span><span class="text-amber-600">● Carga</span></div></div>
            <div class="h-56 pt-3"><canvas id="revenueTrendChart"></canvas></div>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="border-b border-slate-100 pb-3"><h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Mezcla de ventas</h3><p class="text-[10px] text-slate-400">Participación por modalidad</p></div>
            <div class="mx-auto h-40 max-w-56 py-2"><canvas id="modalityChart"></canvas></div>
            <div class="grid grid-cols-3 gap-1 text-center text-[9px] font-bold"><span class="rounded-lg bg-emerald-50 p-2 text-emerald-700">Fluvial<br><strong>{{ $modalityPercentages[0] }}%</strong></span><span class="rounded-lg bg-cyan-50 p-2 text-cyan-700">Aéreo<br><strong>{{ $modalityPercentages[1] }}%</strong></span><span class="rounded-lg bg-amber-50 p-2 text-amber-700">Carga<br><strong>{{ $modalityPercentages[2] }}%</strong></span></div>
        </article>
    </section>

    <section x-show="tab==='operadores'" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between border-b px-5 py-3"><div><h3 class="text-xs font-black uppercase text-slate-900">Rendimiento por operador</h3><p class="text-[10px] text-slate-400">GMV y comisión contractual del período</p></div><a href="{{ route('admin.companies.index') }}" class="text-[10px] font-black text-emerald-700">Ver empresas →</a></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-xs"><thead class="bg-slate-50 text-[9px] font-black uppercase text-slate-400"><tr><th class="p-3">Operador</th><th class="p-3">Modalidad</th><th class="p-3 text-center">Boletos</th><th class="p-3 text-right">GMV</th><th class="p-3 text-right">Comisión</th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($operatorRanking as $operator)
            <tr class="hover:bg-slate-50"><td class="p-3"><a href="{{ route('admin.companies.show', $operator['id']) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $operator['name'] }}</a></td><td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-bold">{{ $operator['modality'] }}</span></td><td class="p-3 text-center font-black">{{ $operator['tickets'] }}</td><td class="p-3 text-right font-bold">S/ {{ number_format($operator['sales'], 2) }}</td><td class="p-3 text-right font-black text-emerald-700">S/ {{ number_format($operator['commission'], 2) }}</td></tr>
        @empty<tr><td colspan="5" class="p-10 text-center text-slate-400">Sin ventas confirmadas en este período.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section x-show="tab==='rutas'" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between border-b px-5 py-3"><div><h3 class="text-xs font-black uppercase text-slate-900">Corredores con mayor demanda</h3><p class="text-[10px] text-slate-400">Pasajeros y venta por tramo</p></div><a href="{{ route('admin.master-routes.index') }}" class="text-[10px] font-black text-emerald-700">Ver catálogo →</a></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-xs"><thead class="bg-slate-50 text-[9px] font-black uppercase text-slate-400"><tr><th class="p-3">Ruta</th><th class="p-3">Modalidad</th><th class="p-3 text-center">Pasajeros</th><th class="p-3 text-right">Venta</th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($topRoutes as $route)
            <tr class="hover:bg-slate-50"><td class="p-3 font-black text-slate-900">{{ $route['name'] }}</td><td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-bold">{{ $route['type'] === 'Aéreo' ? '✈ Aéreo' : '🚤 Fluvial' }}</span></td><td class="p-3 text-center font-black">{{ $route['tickets'] }}</td><td class="p-3 text-right font-black">S/ {{ number_format($route['sales'], 2) }}</td></tr>
        @empty<tr><td colspan="4" class="p-10 text-center text-slate-400">Sin demanda registrada en el período.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section x-show="tab==='canales'" x-cloak class="grid gap-4 lg:grid-cols-3">
        @foreach([
            ['Yape / Plin', $paymentMethodStats['yape_percent'] ?? 0, 'bg-violet-500', 'Billetera móvil', '📱'],
            ['Tarjetas Culqi', $paymentMethodStats['card_percent'] ?? 0, 'bg-emerald-500', 'Débito y crédito', '💳'],
            ['Efectivo / Counter', $paymentMethodStats['cash_percent'] ?? 0, 'bg-slate-500', 'Venta presencial', '💵'],
        ] as $channel)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between"><div><span class="text-[9px] font-black uppercase text-slate-400">{{ $channel[3] }}</span><h3 class="text-sm font-black text-slate-900">{{ $channel[0] }}</h3></div><span class="text-2xl">{{ $channel[4] }}</span></div>
                <strong class="mt-5 block text-3xl font-black text-slate-900">{{ number_format((float) $channel[1], 1) }}%</strong>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $channel[2] }}" style="width: {{ min(100, (float) $channel[1]) }}%"></div></div>
            </article>
        @endforeach
    </section>

    <footer class="flex flex-wrap items-center justify-between gap-2 px-1 text-[10px] text-slate-400">
        <span>{{ number_format($totalTicketsSold) }} boletos · Carga S/ {{ number_format($cargoRevenue, 2) }}</span>
        <span>Período {{ $from->format('d/m/Y') }}–{{ $to->format('d/m/Y') }}</span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
window.initAdminDashboardCharts = function () {
    if (typeof Chart === 'undefined' || !document.getElementById('revenueTrendChart')) return;
    if (window.nyRevenueTrendChart) window.nyRevenueTrendChart.destroy();
    if (window.nyModalityChart) window.nyModalityChart.destroy();
    const money = value => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(value);
    window.nyRevenueTrendChart = new Chart(document.getElementById('revenueTrendChart'), {
        type: 'line',
        data: { labels: @json($chartLabels), datasets: [
            { label: 'Fluvial', data: @json($fluvialSeries), borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.08)', tension: .35, fill: true },
            { label: 'Aéreo', data: @json($airSeries), borderColor: '#0891b2', backgroundColor: 'rgba(8,145,178,.06)', tension: .35, fill: true },
            { label: 'Carga', data: @json($cargoSeries), borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,.05)', tension: .35, fill: true }
        ]},
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + money(ctx.parsed.y) }}}, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 }, callback: value => 'S/ ' + value }}, x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 0 }}}}
    });
    window.nyModalityChart = new Chart(document.getElementById('modalityChart'), {
        type: 'doughnut',
        data: { labels: ['Fluvial', 'Aéreo', 'Carga'], datasets: [{ data: @json($modalityValues), backgroundColor: ['#059669','#0891b2','#d97706'], borderWidth: 0 }]},
        options: { responsive: true, maintainAspectRatio: false, cutout: '74%', plugins: { legend: { display: false }}}
    });
};
document.addEventListener('DOMContentLoaded', window.initAdminDashboardCharts);
document.addEventListener('turbo:load', window.initAdminDashboardCharts);
</script>
@endsection

