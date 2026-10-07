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
@php
    $isAdvertisingDashboard = request('area') === 'publicidad';
@endphp
<div id="executiveDashboard" data-server-area="{{ $isAdvertisingDashboard ? 'publicidad' : 'transporte' }}" class="w-full space-y-4 text-slate-800">
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

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-2 border-b border-slate-200 bg-slate-50 p-3 sm:grid-cols-2" role="tablist" aria-label="Líneas de negocio">
            <a href="{{ route('admin.dashboard',['area'=>'transporte','period'=>$period]) }}" aria-current="{{ $isAdvertisingDashboard ? 'false' : 'page' }}" class="flex min-h-16 items-center gap-3 rounded-2xl border px-4 text-left transition {{ $isAdvertisingDashboard ? 'border-transparent text-slate-700 hover:border-emerald-300 hover:bg-white' : 'border-emerald-600 bg-emerald-950 text-white shadow-lg' }}"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 text-xl">🚤</span><span><strong class="block text-sm font-black">Transporte de pasajeros</strong><small class="block text-[9px] font-semibold opacity-70">Fluvial, aéreo, empresas, carga y finanzas</small></span><span class="ml-auto">→</span></a>
            <a href="{{ route('admin.dashboard',['area'=>'publicidad','period'=>$period]) }}" aria-current="{{ $isAdvertisingDashboard ? 'page' : 'false' }}" class="flex min-h-16 items-center gap-3 rounded-2xl border px-4 text-left transition {{ $isAdvertisingDashboard ? 'border-fuchsia-500 bg-fuchsia-950 text-white shadow-lg' : 'border-transparent text-slate-700 hover:border-fuchsia-300 hover:bg-white' }}"><span class="grid h-10 w-10 place-items-center rounded-xl bg-fuchsia-100 text-xl">📣</span><span><strong class="block text-sm font-black">Publicidad y anunciantes</strong><small class="block text-[9px] font-semibold opacity-70">Contratos, campañas, planes e impacto</small></span><span class="ml-auto">→</span></a>
        </div>
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div><h3 data-analysis-title class="text-sm font-black text-slate-950">{{ $isAdvertisingDashboard ? 'Inteligencia publicitaria' : 'Control de transporte' }}</h3><p data-analysis-copy class="text-[10px] text-slate-400">{{ $isAdvertisingDashboard ? 'Empresas anunciantes, contratos, campañas, directorio y rendimiento.' : 'Selecciona una perspectiva operativa para revisar sus indicadores.' }}</p></div>
            @unless($isAdvertisingDashboard)
            <nav id="transportSubnav" class="flex max-w-full gap-1.5 overflow-x-auto rounded-2xl bg-slate-950 p-1.5 shadow-inner" role="tablist" aria-label="Áreas de transporte">
                @foreach([
                    ['fluvial','🚤','Fluvial','bg-emerald-500 text-emerald-950'],
                    ['aereo','✈️','Aéreo','bg-sky-400 text-sky-950'],
                    ['resumen','▦','Resumen','bg-white text-slate-950'],
                    ['finanzas','S/','Finanzas','bg-amber-400 text-amber-950'],
                    ['empresas','▥','Empresas','bg-violet-400 text-violet-950'],
                    ['carga','◆','Carga','bg-orange-400 text-orange-950']
                ] as [$key,$icon,$label,$activeClass])
                    <button type="button" role="tab" data-dashboard-tab="{{ $key }}" data-active-class="{{ $activeClass }}" aria-selected="{{ $key === 'fluvial' ? 'true' : 'false' }}" class="inline-flex min-h-10 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-[10px] font-black text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/70">
                        <span class="grid h-6 w-6 place-items-center rounded-lg bg-white/10">{{ $icon }}</span><span>{{ $label }}</span>
                    </button>
                @endforeach
            </nav>
            @endunless
        </div>

        @unless($isAdvertisingDashboard)
        <div data-dashboard-panel="resumen" data-dashboard-area="transporte" role="tabpanel" hidden class="space-y-4 p-4">
            <div class="grid gap-3 md:grid-cols-3">
                @foreach([
                    ['Pasajes vendidos', number_format($totalTicketsSold), 'Fluvial '.$fluvialTicketsSold.' · Aéreo '.$airTicketsSold, '🎟️', 'border-cyan-200 bg-cyan-50'],
                    ['Empresas activas', number_format($activeOperatorsCount), $pendingOperatorsCount.' solicitudes por evaluar', '🏢', 'border-violet-200 bg-violet-50'],
                    ['Alertas pendientes', number_format($technicalAlerts + $cancelledDeparturesToday), 'Técnicas y operativas', '⚠️', 'border-rose-200 bg-rose-50']
                ] as $item)
                    <article class="rounded-2xl border p-5 {{ $item[4] }}"><span class="text-2xl">{{ $item[3] }}</span><strong class="mt-3 block text-3xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[10px] font-black uppercase text-slate-700">{{ $item[0] }}</span><p class="mt-1 text-[9px] text-slate-500">{{ $item[2] }}</p></article>
                @endforeach
            </div>
            <div class="grid gap-4 xl:grid-cols-[1.45fr_1fr]">
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between"><div><p class="text-[9px] font-black uppercase tracking-wider text-emerald-700">Pulso de hoy</p><h4 class="text-sm font-black text-slate-900">Continuidad del marketplace</h4></div><a href="{{ route('admin.itineraries.index') }}" class="text-[9px] font-black text-emerald-700">Abrir operación →</a></div>
                    <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                        @foreach([['Salidas',$todaysDeparturesCount,'🕒'],['En río',$riverUnitsInTransit,'🚤'],['En vuelo',$airUnitsInFlight,'✈️'],['Canceladas',$cancelledDeparturesToday,'⛔'],['Alertas',$technicalAlerts,'🛡️'],['Ocupación',number_format($todayOccupancyRate,1).'%','◔']] as $pulse)
                            <div class="rounded-xl bg-slate-50 px-2 py-3 text-center"><span>{{ $pulse[2] }}</span><strong class="mt-1 block text-lg font-black">{{ $pulse[1] }}</strong><span class="text-[8px] font-bold uppercase text-slate-400">{{ $pulse[0] }}</span></div>
                        @endforeach
                    </div>
                </article>
                <article class="flex items-center justify-between rounded-xl border border-cyan-200 bg-cyan-50 p-4"><div><p class="text-[9px] font-black uppercase text-cyan-700">Clima y navegabilidad</p><h4 class="mt-1 text-sm font-black">Condiciones de Loreto</h4><p class="mt-1 text-[10px] text-slate-500">Lluvia, temperatura y nivel del río.</p><a href="{{ route('weather.index') }}" target="_blank" class="mt-3 inline-flex text-[10px] font-black text-cyan-800">Abrir monitoreo →</a></div><span class="text-4xl">🌦️</span></article>
            </div>
            <div class="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                <article class="rounded-xl border border-slate-200 p-4"><h4 class="text-xs font-black uppercase">Evolución general de ventas</h4><p class="text-[9px] text-slate-400">Fluvial, aéreo y carga durante el período</p><div class="h-52"><canvas id="revenueTrendChart"></canvas></div></article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><h4 class="text-xs font-black uppercase">Distribución comercial</h4><div class="mx-auto h-48 w-48"><canvas id="modalityChart"></canvas></div></article>
            </div>
        </div>

        <div data-dashboard-panel="fluvial" data-dashboard-area="transporte" role="tabpanel" class="space-y-4 bg-emerald-50/30 p-4">
            <div class="grid gap-3 md:grid-cols-3">
                @foreach([['Pasajes fluviales',$fluvialTicketsSold,'Vendidos en el período','🎟️'],['GMV fluvial','S/ '.number_format($fluvialGmv,2),$fluvialDeparturesCount.' salidas programadas','💳'],['Ocupación',number_format($fluvialOccupancyRate,1).'%',number_format($fluvialCapacity).' asientos publicados','◔']] as $item)
                    <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm"><span class="text-2xl">{{ $item[3] }}</span><strong class="mt-3 block text-3xl font-black text-emerald-950">{{ $item[1] }}</strong><span class="text-[10px] font-black uppercase text-emerald-800">{{ $item[0] }}</span><p class="mt-1 text-[9px] text-slate-500">{{ $item[2] }}</p></article>
                @endforeach
            </div>
            <div class="grid gap-4 xl:grid-cols-[1.55fr_1fr]">
                <article class="overflow-hidden rounded-xl border border-emerald-200 bg-white"><div class="flex justify-between bg-emerald-50 px-4 py-3"><div><h4 class="text-xs font-black">Mapa de rutas fluviales</h4><p class="text-[9px] text-slate-500">Puertos, operadores, demanda y ventas por tramo</p></div><span class="text-[9px] font-bold text-emerald-700">━ Ruta fluvial</span></div><div id="fluvialRoutesMap" class="h-[350px]"></div></article>
                <article class="rounded-xl border border-emerald-200 bg-white p-4"><h4 class="text-xs font-black">Ventas fluviales en el tiempo</h4><p class="text-[9px] text-slate-400">GMV por fecha del período</p><div class="h-40"><canvas id="fluvialSalesChart"></canvas></div><h4 class="mt-3 border-t pt-3 text-xs font-black">Capacidad y demanda</h4><div class="h-36"><canvas id="fluvialCapacityChart"></canvas></div></article>
            </div>
            <article class="overflow-hidden rounded-xl border border-emerald-200 bg-white"><div class="flex justify-between bg-emerald-50 px-4 py-3"><h4 class="text-xs font-black">Rutas fluviales con mayor demanda</h4><a href="{{ route('admin.master-routes.index') }}" class="text-[9px] font-black text-emerald-700">Ver catálogo →</a></div><table class="w-full text-left text-[10px]"><thead class="border-y uppercase text-slate-400"><tr><th class="p-3">Ruta</th><th class="p-3 text-center">Pasajes</th><th class="p-3 text-right">GMV</th><th class="p-3 text-center">Estado</th></tr></thead><tbody class="divide-y">@forelse($topRoutes->where('type','Fluvial') as $route)<tr><td class="p-3 font-black">{{ $route['name'] }}</td><td class="p-3 text-center">{{ $route['tickets'] }}</td><td class="p-3 text-right">S/ {{ number_format($route['sales'],2) }}</td><td class="p-3 text-center"><span class="rounded-full bg-emerald-50 px-2 py-1 font-bold text-emerald-700">Activa</span></td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-400">Sin ventas fluviales en este período.</td></tr>@endforelse</tbody></table></article>
        </div>

        <div data-dashboard-panel="aereo" data-dashboard-area="transporte" role="tabpanel" hidden class="space-y-4 bg-sky-50/30 p-4">
            <div class="grid gap-3 md:grid-cols-3">
                @foreach([['Pasajes aéreos',$airTicketsSold,'Vendidos en el período','🎟️'],['GMV aéreo','S/ '.number_format($airGmv,2),$airDeparturesCount.' vuelos programados','💳'],['Ocupación',number_format($airOccupancyRate,1).'%',number_format($airCapacity).' asientos publicados','◔']] as $item)
                    <article class="rounded-2xl border border-sky-200 bg-white p-5 shadow-sm"><span class="text-2xl">{{ $item[3] }}</span><strong class="mt-3 block text-3xl font-black text-sky-950">{{ $item[1] }}</strong><span class="text-[10px] font-black uppercase text-sky-800">{{ $item[0] }}</span><p class="mt-1 text-[9px] text-slate-500">{{ $item[2] }}</p></article>
                @endforeach
            </div>
            <div class="grid gap-4 xl:grid-cols-[1.55fr_1fr]">
                <article class="overflow-hidden rounded-xl border border-sky-200 bg-white"><div class="flex justify-between bg-sky-50 px-4 py-3"><div><h4 class="text-xs font-black">Mapa de conexiones aéreas</h4><p class="text-[9px] text-slate-500">Aeródromos, operadores y demanda regional</p></div><span class="text-[9px] font-bold text-sky-700">┄ Ruta aérea</span></div><div id="airRoutesMap" class="h-[350px]"></div></article>
                <article class="rounded-xl border border-sky-200 bg-white p-4"><h4 class="text-xs font-black">Ventas aéreas en el tiempo</h4><p class="text-[9px] text-slate-400">GMV por fecha del período</p><div class="h-40"><canvas id="airSalesChart"></canvas></div><h4 class="mt-3 border-t pt-3 text-xs font-black">Capacidad y demanda</h4><div class="h-36"><canvas id="airCapacityChart"></canvas></div></article>
            </div>
            <article class="overflow-hidden rounded-xl border border-sky-200 bg-white"><div class="flex justify-between bg-sky-50 px-4 py-3"><h4 class="text-xs font-black">Conexiones aéreas con mayor demanda</h4><a href="{{ route('admin.master-routes.index') }}" class="text-[9px] font-black text-sky-700">Ver catálogo →</a></div><table class="w-full text-left text-[10px]"><thead class="border-y uppercase text-slate-400"><tr><th class="p-3">Conexión</th><th class="p-3 text-center">Pasajes</th><th class="p-3 text-right">GMV</th><th class="p-3 text-center">Estado</th></tr></thead><tbody class="divide-y">@forelse($topRoutes->where('type','Aéreo') as $route)<tr><td class="p-3 font-black">{{ $route['name'] }}</td><td class="p-3 text-center">{{ $route['tickets'] }}</td><td class="p-3 text-right">S/ {{ number_format($route['sales'],2) }}</td><td class="p-3 text-center"><span class="rounded-full bg-sky-50 px-2 py-1 font-bold text-sky-700">Activa</span></td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-400">Sin ventas aéreas en este período.</td></tr>@endforelse</tbody></table></article>
        </div>

        <div data-dashboard-panel="finanzas" data-dashboard-area="transporte" role="tabpanel" hidden class="space-y-4 bg-amber-50/30 p-4">
            <div class="grid gap-3 md:grid-cols-3">
                @foreach([['GMV procesado','S/ '.number_format($grossSales,2),'Ventas confirmadas','💳'],['Comisión generada','S/ '.number_format($netCommission,2),'Ingreso NavegaYA','%'],['Comisión pendiente','S/ '.number_format($commissionReceivable,2),'Pendiente de cobro','⏳']] as $item)
                    <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm"><span class="text-2xl">{{ $item[3] }}</span><strong class="mt-3 block text-3xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[10px] font-black uppercase text-amber-800">{{ $item[0] }}</span><p class="mt-1 text-[9px] text-slate-500">{{ $item[2] }}</p></article>
                @endforeach
            </div>
            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]"><article class="rounded-xl border border-amber-200 bg-white p-4"><h4 class="text-xs font-black">GMV y comisiones</h4><p class="text-[9px] text-slate-400">Evolución comercial por modalidad</p><div class="h-56"><canvas id="financeTrendChart"></canvas></div></article><article class="rounded-xl border border-amber-200 bg-white p-4"><h4 class="text-xs font-black">Canales de cobro</h4><p class="text-[9px] text-slate-400">Participación de medios de pago</p><div class="h-56"><canvas id="paymentMethodsChart"></canvas></div></article></div>
            <article class="overflow-hidden rounded-xl border border-amber-200 bg-white"><div class="flex justify-between bg-amber-50 px-4 py-3"><h4 class="text-xs font-black">Rendimiento financiero por operador</h4><a href="{{ route('admin.commissions.index') }}" class="text-[9px] font-black text-amber-800">Abrir comisiones →</a></div><table class="w-full text-left text-[10px]"><thead class="border-y uppercase text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3">Modalidad</th><th class="p-3 text-center">Pasajes</th><th class="p-3 text-right">GMV</th><th class="p-3 text-right">Comisión</th></tr></thead><tbody class="divide-y">@forelse($operatorRanking as $operator)<tr><td class="p-3 font-black">{{ $operator['name'] }}</td><td class="p-3">{{ $operator['modality'] }}</td><td class="p-3 text-center">{{ $operator['tickets'] }}</td><td class="p-3 text-right">S/ {{ number_format($operator['sales'],2) }}</td><td class="p-3 text-right font-black text-emerald-700">S/ {{ number_format($operator['commission'],2) }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-slate-400">Sin transacciones en el período.</td></tr>@endforelse</tbody></table></article>
        </div>

        <div data-dashboard-panel="empresas" data-dashboard-area="transporte" role="tabpanel" hidden class="grid gap-4 p-4 xl:grid-cols-[1fr_1.4fr]">
            <div><div class="grid grid-cols-2 gap-2">@foreach([['Activas',$activeOperatorsCount],['Pendientes',$pendingOperatorsCount],['Rechazadas',$rejectedOperatorsCount],['Unidades',$registeredVesselsCount+$registeredAircraftCount]] as $item)<a href="{{ route('admin.companies.index') }}" class="rounded-xl border border-slate-200 p-3 hover:border-emerald-300"><span class="text-[9px] font-bold uppercase text-slate-400">{{ $item[0] }}</span><strong class="mt-1 block text-xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[8px] font-bold text-emerald-700">Ver detalle →</span></a>@endforeach</div><div class="mt-4 grid gap-3 sm:grid-cols-2"><div><h4 class="text-[10px] font-black text-slate-800">Afiliaciones por mes</h4><div class="h-44"><canvas id="affiliationsChart"></canvas></div></div><div><h4 class="text-[10px] font-black text-slate-800">Flota por modalidad</h4><div class="h-44"><canvas id="fleetChart"></canvas></div></div></div></div>
            <div class="overflow-hidden rounded-xl border border-slate-200"><div class="flex justify-between bg-slate-50 px-4 py-3"><div><h4 class="text-xs font-black text-slate-900">Flota registrada por empresa</h4><p class="text-[9px] text-slate-400">{{ $registeredVesselsCount }} embarcaciones · {{ $registeredAircraftCount }} aeronaves</p></div><a href="{{ route('admin.companies.index') }}" class="text-[9px] font-black text-emerald-700">Ver expedientes →</a></div><div class="max-h-72 overflow-auto"><table class="w-full text-left text-[10px]"><thead class="sticky top-0 bg-white uppercase text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3 text-center">🚤</th><th class="p-3 text-center">✈️</th><th class="p-3 text-center">Total</th></tr></thead><tbody class="divide-y">@forelse($fleetByOperator as $operator)<tr><td class="p-3"><a href="{{ route('admin.companies.show', $operator['id']) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $operator['name'] }}</a></td><td class="p-3 text-center">{{ $operator['vessels'] }}</td><td class="p-3 text-center">{{ $operator['aircraft'] }}</td><td class="p-3 text-center font-black">{{ $operator['total'] }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-400">Sin flota registrada.</td></tr>@endforelse</tbody></table></div></div>
        </div>

        <div data-dashboard-panel="carga" data-dashboard-area="transporte" role="tabpanel" hidden class="grid gap-4 p-4 lg:grid-cols-[1fr_1.2fr]">
            <div class="grid grid-cols-2 gap-3">@foreach([['Envíos registrados',$cargoShipmentsCount,'Guías del período','📦'],['Remitentes únicos',$cargoCustomersCount,'Personas que enviaron','👤'],['Paquetes movilizados',$cargoPackagesCount,'Bultos declarados','▣'],['Ingreso por carga','S/ '.number_format($cargoRevenue,2),'Operaciones pagadas','💰']] as $item)<a href="{{ route('admin.cargo.index') }}" class="rounded-xl border border-slate-200 p-4 hover:border-emerald-300"><span class="text-xl">{{ $item[3] }}</span><strong class="mt-2 block text-xl font-black text-slate-950">{{ $item[1] }}</strong><span class="text-[9px] font-black text-slate-700">{{ $item[0] }}</span><p class="text-[8px] text-slate-400">{{ $item[2] }}</p></a>@endforeach</div><div class="rounded-xl bg-slate-50 p-4"><h4 class="text-xs font-black text-slate-900">Actividad de carga</h4><p class="text-[9px] text-slate-400">Envíos, remitentes y paquetes en el período</p><div class="h-56"><canvas id="cargoChart"></canvas></div></div>
        </div>

        @else
        <div data-dashboard-panel="publicidad" data-dashboard-area="publicidad" role="tabpanel" hidden class="bg-fuchsia-50/40 p-4">
            <nav class="mb-4 flex gap-1 overflow-x-auto rounded-2xl border border-fuchsia-200 bg-white p-1.5" aria-label="Secciones de publicidad">
                @foreach([['resumen','▦','Resumen'],['anunciantes','🏨','Anunciantes'],['solicitudes','⏳','Solicitudes'],['campanas','📦','Planes y suscripciones'],['directorio','🗺️','Directorio'],['rendimiento','📈','Rendimiento']] as [$key,$icon,$label])
                    <button type="button" data-ad-tab="{{ $key }}" aria-selected="{{ $key==='campanas'?'true':'false' }}" class="inline-flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 text-[9px] font-black text-slate-500 hover:bg-fuchsia-50"><span>{{ $icon }}</span>{{ $label }}</button>
                @endforeach
            </nav>
            <div data-ad-panel="resumen" hidden class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach([['Anunciantes activos',$activeAdvertisersCount,'📣','border-fuchsia-200'],['Campañas activas',$activeCampaignsCount,'🟣','border-violet-200'],['Solicitudes pendientes',$pendingAdLeadsCount,'⏳','border-amber-200'],['Ingreso mensual','S/ '.number_format($monthlyAdvertisingRevenue,2),'💰','border-emerald-200']] as $item)<article class="rounded-xl border bg-white p-4 {{ $item[3] }}"><span>{{ $item[2] }}</span><strong class="mt-2 block text-2xl font-black">{{ $item[1] }}</strong><span class="text-[9px] font-black text-slate-600">{{ $item[0] }}</span></article>@endforeach</div>
                <article class="rounded-2xl bg-gradient-to-br from-fuchsia-950 to-slate-950 p-5 text-white"><p class="text-[9px] font-black uppercase text-fuchsia-300">Unidad de negocio independiente</p><h4 class="mt-1 text-lg font-black">Publicidad para negocios que atienden viajeros</h4><p class="mt-2 text-[10px] text-slate-300">NavegaYA comercializa visibilidad y cada anunciante puede complementar su campaña con una ficha pública.</p><a href="{{ route('advertising.create') }}" target="_blank" class="mt-4 inline-flex rounded-xl bg-fuchsia-400 px-4 py-2.5 text-[10px] font-black text-fuchsia-950">Ver página para anunciarse ↗</a></article>
            </div>
            <div data-ad-panel="anunciantes" hidden class="space-y-4"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach([['🏨','Hoteles y alojamientos'],['🍽️','Restaurantes'],['🌿','Tours y experiencias'],['🧭','Agencias y operadores']] as [$icon,$label])<article class="rounded-2xl border border-fuchsia-200 bg-white p-5"><span class="text-2xl">{{ $icon }}</span><h4 class="mt-2 text-xs font-black">{{ $label }}</h4><p class="mt-1 text-[9px] text-slate-500">Perfil, contacto, plan y vigencia.</p></article>@endforeach</div><a href="{{ route('admin.advertisements.index') }}" class="inline-flex rounded-xl bg-fuchsia-700 px-4 py-2.5 text-[10px] font-black text-white">Administrar {{ $activeAdvertisersCount }} anunciantes →</a></div>
            <div data-ad-panel="solicitudes" hidden class="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center"><span class="text-3xl">⏳</span><strong class="mt-2 block text-3xl font-black">{{ $pendingAdLeadsCount }}</strong><h4 class="text-xs font-black">Solicitudes pendientes de evaluación</h4><p class="mt-2 text-[10px] text-amber-800">Valida negocio, contacto, categoría y propuesta antes de aprobar.</p><a href="{{ route('admin.advertisements.index',['status'=>'lead_pending']) }}" class="mt-4 inline-flex rounded-xl bg-amber-500 px-4 py-2.5 text-[10px] font-black">Revisar solicitudes →</a></div>
            <div data-ad-panel="campanas" x-data="{plan:'all',status:'all',detail:null}" class="space-y-6">
                <section class="grid gap-4 lg:grid-cols-3">
                    @foreach($planMetrics as $plan)
                        <article class="relative flex h-full min-h-[330px] flex-col overflow-hidden rounded-2xl border p-6 shadow-sm {{ match($plan['code']) {'professional'=>'border-amber-300 bg-amber-50','enterprise'=>'border-blue-300 bg-blue-50',default=>'border-slate-300 bg-white'} }}">
                            @if($plan['code']==='professional')<span class="absolute right-4 top-4 rounded-full bg-amber-400 px-2 py-1 text-[8px] font-black text-amber-950">RECOMENDADO</span>@elseif($plan['code']==='enterprise')<span class="absolute right-4 top-4 rounded-full bg-blue-900 px-2 py-1 text-[8px] font-black text-white">PREMIUM</span>@endif
                            <span class="text-2xl">{{ $plan['code']==='basic'?'🏢':($plan['code']==='professional'?'⭐':'⭐⭐') }}</span><h4 class="mt-3 text-lg font-black uppercase">{{ $plan['name'] }}</h4><strong class="mt-1 text-2xl font-black">S/ {{ number_format($plan['price'],0) }}<small class="text-xs font-bold text-slate-500">/mes</small></strong>
                            <div class="mt-4 grid grid-cols-2 gap-2"><div class="rounded-xl bg-white/80 p-3"><span class="text-[8px] uppercase text-slate-500">Clientes activos</span><strong class="block text-xl">{{ $plan['clients'] }}</strong></div><div class="rounded-xl bg-white/80 p-3"><span class="text-[8px] uppercase text-slate-500">Crecimiento</span><strong class="block text-xl {{ $plan['growth']>=0?'text-emerald-700':'text-rose-700' }}">{{ $plan['growth']>=0?'+':'' }}{{ $plan['growth'] }}%</strong></div></div>
                            <p class="mt-3 text-[9px] font-bold text-slate-500">Ingresos del mes</p><strong class="text-lg">S/ {{ number_format($plan['revenue'],2) }}</strong><ul class="mt-3 space-y-1 text-[9px] text-slate-600">@foreach(data_get($plan,'benefits.features',[]) as $feature)<li>✓ {{ $feature }}</li>@endforeach</ul>
                            <button type="button" @click="detail=JSON.parse($el.dataset.plan)" data-plan='@json($plan)' class="mt-auto rounded-xl {{ $plan['code']==='professional'?'bg-amber-500 text-amber-950':'bg-slate-900 text-white' }} px-4 py-2.5 text-[10px] font-black">Ver detalles</button>
                        </article>
                    @endforeach
                </section>
                <section class="grid gap-4 lg:grid-cols-2"><article class="rounded-2xl border bg-white p-5"><h4 class="text-sm font-black">Ingresos mensuales por plan</h4><p class="text-[9px] text-slate-400">Ingreso recurrente actualmente contratado</p><div class="h-64"><canvas id="subscriptionRevenueChart"></canvas></div></article><article class="rounded-2xl border bg-white p-5"><h4 class="text-sm font-black">Distribución de clientes</h4><p class="text-[9px] text-slate-400">Participación por plan</p><div class="h-64"><canvas id="subscriptionClientsChart"></canvas></div></article></section>
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach([['Renovaciones próximas',$expiringSubscriptionsCount,'Próximos 7 días',$expiringSubscriptionsCount>3?'rose':($expiringSubscriptionsCount?'amber':'emerald')],['Churn de Básico',$basicChurn.'%',$basicRisk.' clientes en riesgo',$basicChurn>40?'rose':($basicChurn>=30?'amber':'emerald')],['Enterprise',$enterpriseClients.' clientes','Concentración comercial',$enterpriseClients<3?'rose':($enterpriseClients<=5?'amber':'emerald')],['Crecimiento Profesional',($professionalGrowth>=0?'+':'').$professionalGrowth.'%','Tendencia mensual',$professionalGrowth>20?'emerald':($professionalGrowth>=10?'amber':'rose')]] as [$title,$value,$copy,$color])<article class="rounded-xl border p-4 {{ match($color) {'rose'=>'border-rose-200 bg-rose-50','amber'=>'border-amber-200 bg-amber-50',default=>'border-emerald-200 bg-emerald-50'} }}"><span class="text-[9px] font-black uppercase {{ match($color) {'rose'=>'text-rose-700','amber'=>'text-amber-700',default=>'text-emerald-700'} }}">{{ $title }}</span><strong class="mt-1 block text-2xl">{{ $value }}</strong><p class="text-[9px] text-slate-500">{{ $copy }}</p></article>@endforeach</section>
                <section class="overflow-hidden rounded-2xl border bg-white"><div class="flex flex-col gap-3 border-b bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="text-sm font-black">Suscripciones activas</h4><p class="text-[9px] text-slate-400">Ordenadas por próxima renovación</p><a href="{{ route('admin.advertising-subscriptions.index') }}" class="mt-1 inline-flex text-[9px] font-black text-fuchsia-700">Administrar suscripciones →</a></div><div class="flex gap-2"><select x-model="plan" class="rounded-lg border-slate-200 text-[10px]"><option value="all">Todos los planes</option><option value="basic">Básico</option><option value="professional">Profesional</option><option value="enterprise">Enterprise</option></select><select x-model="status" class="rounded-lg border-slate-200 text-[10px]"><option value="all">Todos los estados</option><option value="active">Activas</option><option value="overdue">Por vencer</option></select></div></div><div class="overflow-x-auto"><table class="w-full text-left text-[9px]"><thead class="bg-slate-50 uppercase text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3">Plan</th><th class="p-3">Anuncios</th><th class="p-3">Impresiones</th><th class="p-3">Inicio</th><th class="p-3">Renovación</th><th class="p-3">Estado</th></tr></thead><tbody class="divide-y">@forelse($subscriptionRows as $row)<tr x-show="(plan==='all'||plan==='{{ $row['plan_code'] }}')&&(status==='all'||status==='{{ $row['status'] }}')"><td class="p-3 font-black">{{ $row['company'] }}</td><td class="p-3">{{ $row['plan'] }}</td><td class="p-3">{{ $row['ads'] }}</td><td class="p-3">{{ number_format($row['impressions']) }} / {{ number_format($row['target']) }}</td><td class="p-3">{{ $row['starts_on']?->format('d/m/Y') }}</td><td class="p-3">{{ $row['ends_on']?->format('d/m/Y') ?? 'Sin fecha' }}</td><td class="p-3"><span class="rounded-full px-2 py-1 font-bold {{ $row['status']==='active'?'bg-emerald-50 text-emerald-700':'bg-amber-50 text-amber-700' }}">{{ $row['status']==='active'?'Activo':'Por vencer' }}</span></td></tr>@empty<tr><td colspan="7" class="p-10 text-center text-slate-400">Aún no existen suscripciones. Los contratos aparecerán aquí al asignar un plan a una empresa.</td></tr>@endforelse</tbody></table></div></section>
                <div x-show="detail" x-cloak class="fixed inset-0 z-[2000] grid place-items-center bg-slate-950/60 p-4" @keydown.escape.window="detail=null"><div @click.outside="detail=null" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"><div class="flex justify-between"><div><p class="text-[9px] font-black uppercase text-fuchsia-700">Detalle del plan</p><h3 class="text-xl font-black" x-text="detail?.name"></h3></div><button @click="detail=null" class="text-xl">×</button></div><p class="mt-3 text-2xl font-black">S/ <span x-text="detail?.price"></span><small class="text-xs">/mes</small></p><ul class="mt-4 space-y-2 text-xs" x-show="detail?.benefits?.features"><template x-for="feature in detail?.benefits?.features || []"><li>✓ <span x-text="feature"></span></li></template></ul><button @click="detail=null" class="mt-6 w-full rounded-xl bg-slate-900 py-3 text-xs font-black text-white">Cerrar</button></div></div>
            </div>
            <div data-ad-panel="directorio" hidden><article class="rounded-2xl border border-fuchsia-200 bg-white p-5"><div class="flex justify-between"><div><p class="text-[9px] font-black uppercase text-fuchsia-700">Directorio turístico público</p><h4 class="text-lg font-black">{{ $publishedTourismCount }} fichas publicadas</h4></div><span class="text-[9px] font-black text-amber-700">{{ $featuredTourismCount }} destacadas</span></div><div class="mt-4 grid grid-cols-3 gap-3">@foreach([['🏨','Hospedajes',$tourismDirectoryCounts['lodging'],'lodging'],['🍽️','Restaurantes',$tourismDirectoryCounts['gastronomy'],'gastronomy'],['🌿','Tours',$tourismDirectoryCounts['attraction'],'attraction']] as [$icon,$label,$count,$type])<a href="{{ route('admin.destination-listings.index',$type) }}" class="rounded-xl bg-slate-50 p-4 text-center hover:bg-fuchsia-50"><span class="text-xl">{{ $icon }}</span><strong class="block text-2xl font-black">{{ $count }}</strong><span class="text-[9px]">{{ $label }}</span></a>@endforeach</div></article></div>
            <div data-ad-panel="rendimiento" hidden><article class="rounded-2xl bg-slate-950 p-5 text-white"><p class="text-[9px] font-black uppercase text-fuchsia-300">Rendimiento publicitario</p><div class="mt-4 grid gap-3 sm:grid-cols-3"><div class="rounded-xl bg-white/10 p-4"><span class="text-[9px]">Impresiones</span><strong class="block text-3xl">{{ number_format($advertisingViews) }}</strong></div><div class="rounded-xl bg-white/10 p-4"><span class="text-[9px]">Clics</span><strong class="block text-3xl">{{ number_format($advertisingClicks) }}</strong></div><div class="rounded-xl bg-fuchsia-500/20 p-4"><span class="text-[9px]">CTR</span><strong class="block text-3xl">{{ number_format($advertisingCtr,1) }}%</strong></div></div>@if($advertisingViews===0)<p class="mt-4 rounded-xl bg-white/5 p-4 text-[10px] text-slate-300">Las métricas aparecerán cuando las campañas reciban impresiones y clics.</p>@endif</article></div>
        </div>
        @endunless

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
    fluvialCapacity: [{{ $fluvialTicketsSold }}, {{ max(0, $fluvialCapacity - $fluvialTicketsSold) }}],
    airCapacity: [{{ $airTicketsSold }}, {{ max(0, $airCapacity - $airTicketsSold) }}],
    paymentMethods: [{{ $paymentMethodStats['yape_percent'] }}, {{ $paymentMethodStats['card_percent'] }}, {{ $paymentMethodStats['cash_percent'] }}],
    advertisingPlans: @json($advertisingPlans->pluck('count')->values()),
    advertisingPipeline: @json($advertisingPipeline), subscriptionRevenue: @json($subscriptionRevenueSeries), subscriptionClients: @json($subscriptionClientSeries)
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
    if(tab==='fluvial') {
        window.makeChart('fluvialSalesChart',{type:'line',data:{labels:d.chartLabels,datasets:[{data:d.fluvialSeries,borderColor:'#059669',backgroundColor:'rgba(5,150,105,.12)',fill:true,tension:.35}]},options:{...baseOptions,scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});
        window.makeChart('fluvialCapacityChart',{type:'doughnut',data:{labels:['Vendidos','Disponibles'],datasets:[{data:d.fluvialCapacity.some(Number)?d.fluvialCapacity:[1],backgroundColor:d.fluvialCapacity.some(Number)?['#059669','#d1fae5']:['#cbd5e1'],borderWidth:0}]},options:{...baseOptions,cutout:'62%'}});
        window.initModeMap('fluvialRoutesMap','Fluvial');
    }
    if(tab==='aereo') {
        window.makeChart('airSalesChart',{type:'line',data:{labels:d.chartLabels,datasets:[{data:d.airSeries,borderColor:'#0284c7',backgroundColor:'rgba(2,132,199,.12)',fill:true,tension:.35}]},options:{...baseOptions,scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});
        window.makeChart('airCapacityChart',{type:'doughnut',data:{labels:['Vendidos','Disponibles'],datasets:[{data:d.airCapacity.some(Number)?d.airCapacity:[1],backgroundColor:d.airCapacity.some(Number)?['#0284c7','#e0f2fe']:['#cbd5e1'],borderWidth:0}]},options:{...baseOptions,cutout:'62%'}});
        window.initModeMap('airRoutesMap','Aéreo');
    }
    if(tab==='finanzas') {
        window.makeChart('financeTrendChart',{type:'line',data:{labels:d.chartLabels,datasets:[{label:'Fluvial',data:d.fluvialSeries,borderColor:'#059669',tension:.35},{label:'Aéreo',data:d.airSeries,borderColor:'#0284c7',tension:.35},{label:'Carga',data:d.cargoSeries,borderColor:'#d97706',tension:.35}]},options:{...baseOptions,plugins:{legend:{display:true,labels:{boxWidth:8,font:{size:9}}}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});
        window.makeChart('paymentMethodsChart',{type:'doughnut',data:{labels:['Yape / Plin','Tarjetas','Otros'],datasets:[{data:d.paymentMethods.some(Number)?d.paymentMethods:[1],backgroundColor:d.paymentMethods.some(Number)?['#7c3aed','#0284c7','#f59e0b']:['#cbd5e1'],borderWidth:0}]},options:{...baseOptions,cutout:'65%',plugins:{legend:{display:true,position:'bottom',labels:{boxWidth:8,font:{size:9}}}}}});
    }
    if(tab==='empresas') { window.makeChart('affiliationsChart',{type:'bar',data:{labels:d.affiliationLabels,datasets:[{data:d.affiliationSeries,backgroundColor:'#10b981',borderRadius:5}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}}); window.makeChart('fleetChart',{type:'doughnut',data:{labels:['Embarcaciones','Aeronaves'],datasets:[{data:[{{ $registeredVesselsCount }},{{ $registeredAircraftCount }}],backgroundColor:['#059669','#0891b2'],borderWidth:0}]},options:{...baseOptions,cutout:'65%',plugins:{legend:{display:true,position:'bottom',labels:{boxWidth:8,font:{size:9}}}}}}); }
    if(tab==='carga') window.makeChart('cargoChart',{type:'bar',data:{labels:['Envíos','Remitentes','Paquetes'],datasets:[{data:d.cargo,backgroundColor:['#0f766e','#0891b2','#d97706'],borderRadius:8}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}});
    if(tab==='publicidad') {
        window.makeChart('subscriptionRevenueChart',{type:'bar',data:{labels:['Esencial','Destacado','Cobertura total'],datasets:[{data:d.subscriptionRevenue,backgroundColor:['#38bdf8','#8b5cf6','#f59e0b'],borderRadius:8}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}});
        window.makeChart('subscriptionClientsChart',{type:'doughnut',data:{labels:['Básico','Profesional','Enterprise'],datasets:[{data:d.subscriptionClients.some(Number)?d.advertisingPipeline:[1],backgroundColor:d.advertisingPipeline.some(Number)?['#10b981','#f59e0b','#8b5cf6','#94a3b8']:['#cbd5e1'],borderWidth:0}]},options:{...baseOptions,cutout:'65%',plugins:{legend:{display:true,position:'bottom',labels:{boxWidth:8,font:{size:9}}}}}});
    }
};
window.initModeMap = function(elementId, modality) {
    if(typeof L==='undefined') return; const el=document.getElementById(elementId); if(!el) return;
    window.nyModeMaps = window.nyModeMaps || {};
    if(window.nyModeMaps[elementId]) {
        if(window.nyModeMaps[elementId].getContainer() === el) { setTimeout(()=>window.nyModeMaps[elementId].invalidateSize(),50); return; }
        window.nyModeMaps[elementId].remove(); delete window.nyModeMaps[elementId];
    }
    const map=L.map(el,{zoomControl:true}).setView([-4.45,-74.3],6); window.nyModeMaps[elementId]=map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'© OpenStreetMap'}).addTo(map);
    const routes=window.dashboardData.routes.filter(route=>route.type===modality); const bounds=[];
    const loretoNodes=[['Iquitos',-3.7437,-73.2516],['Nauta',-4.5051,-73.5757],['Yurimaguas',-5.8966,-76.1043],['Requena',-5.0638,-73.8528],['Contamana',-7.3509,-75.0090],['Caballococha',-3.9058,-70.5168],['San Lorenzo',-4.8294,-76.5558]];
    loretoNodes.forEach(([name,lat,lng])=>L.circleMarker([lat,lng],{radius:5,color:modality==='Aéreo'?'#0369a1':'#047857',fillColor:'#fff',fillOpacity:1,weight:2}).addTo(map).bindTooltip(name,{direction:'top'}));
    routes.forEach(route=>{if(!route.origin_coords||!route.destination_coords)return; const color=modality==='Aéreo'?'#0284c7':'#059669'; const line=L.polyline([route.origin_coords,route.destination_coords],{color,weight:Math.min(9,3+Number(route.tickets||0)/10),dashArray:modality==='Aéreo'?'8 7':null,opacity:.9}).addTo(map); line.bindPopup('<strong>'+(modality==='Aéreo'?'✈️':'🚤')+' '+route.origin+' → '+route.destination+'</strong><br>'+route.operator+'<br><b>'+route.tickets+'</b> pasajes · S/ '+Number(route.sales).toFixed(2)); bounds.push(route.origin_coords,route.destination_coords);});
    if(bounds.length) map.fitBounds(bounds,{padding:[25,25],maxZoom:8}); else {
        map.setView([-4.65,-73.75],6);
        const notice=L.control({position:'bottomleft'}); notice.onAdd=()=>{const div=L.DomUtil.create('div','rounded-lg bg-white/95 p-2 text-[9px] font-bold text-slate-600 shadow-lg');div.innerHTML='Puntos de referencia de Loreto<br><span style="color:#64748b;font-weight:500">Las líneas aparecerán al registrar coordenadas en las rutas.</span>';return div;};notice.addTo(map);
    }
    setTimeout(()=>map.invalidateSize(),100);
};
window.initExecutiveDashboard = function() {
    const root=document.getElementById('executiveDashboard'); if(!root) return;
    const businessButtons=[...root.querySelectorAll('[data-business-tab]')];
    const buttons=[...root.querySelectorAll('[data-dashboard-tab]')];
    const panels=[...root.querySelectorAll('[data-dashboard-panel]')];
    const subnav=root.querySelector('#transportSubnav');
    const title=root.querySelector('[data-analysis-title]');
    const copy=root.querySelector('[data-analysis-copy]');
    if(root.dataset.serverArea==='publicidad') {
        const adButtons=[...root.querySelectorAll('[data-ad-tab]')];
        const adPanels=[...root.querySelectorAll('[data-ad-panel]')];
        const activateAd=name=>{
            if(!adButtons.some(button=>button.dataset.adTab===name)) name='resumen';
            adPanels.forEach(panel=>{const show=panel.dataset.adPanel===name;panel.hidden=!show;panel.style.display=show?'':'none';});
            adButtons.forEach(button=>{const selected=button.dataset.adTab===name;button.setAttribute('aria-selected',selected?'true':'false');button.classList.toggle('bg-fuchsia-700',selected);button.classList.toggle('text-white',selected);button.classList.toggle('shadow-sm',selected);button.classList.toggle('text-slate-500',!selected);});
            if(name==='campanas') requestAnimationFrame(()=>setTimeout(()=>window.initDashboardTab('publicidad'),40));
        };
        adButtons.forEach(button=>button.addEventListener('click',()=>activateAd(button.dataset.adTab)));
        activateAd('campanas');
        return;
    }
    if(!buttons.length || !panels.length) return;
    const valid=buttons.map(button=>button.dataset.dashboardTab);
    const activePalette=['bg-white','bg-emerald-500','bg-sky-400','bg-amber-400','bg-violet-400','bg-orange-400','text-slate-950','text-emerald-950','text-sky-950','text-amber-950','text-violet-950','text-orange-950','shadow-md'];
    const businessPalette=['border-emerald-600','bg-emerald-950','border-fuchsia-500','bg-fuchsia-950','text-white','text-slate-700','shadow-lg'];

    const activateTransportTab=(name,updateUrl=true)=>{
        if(!valid.includes(name)) name='fluvial';
        panels.forEach(panel=>{const show=panel.dataset.dashboardArea==='transporte' && panel.dataset.dashboardPanel===name;panel.hidden=!show;panel.style.display=show?'':'none';});
        buttons.forEach(button=>{
            const selected=button.dataset.dashboardTab===name;
            button.setAttribute('aria-selected',selected?'true':'false');
            button.classList.remove(...activePalette,'text-slate-300');
            if(selected) button.classList.add(...button.dataset.activeClass.split(' '),'shadow-md');
            else button.classList.add('text-slate-300');
        });
        if(updateUrl){const url=new URL(location.href);url.searchParams.set('area','transporte');url.searchParams.set('tab',name);history.replaceState(history.state,'',url);}
        requestAnimationFrame(()=>setTimeout(()=>window.initDashboardTab(name),40));
    };

    const activateBusiness=(area,updateUrl=true)=>{
        const advertising=area==='publicidad';
        businessButtons.forEach(button=>{
            const selected=button.dataset.businessTab===area;
            button.setAttribute('aria-selected',selected?'true':'false');
            button.classList.remove(...businessPalette);
            if(selected) button.classList.add(...button.dataset.businessActive.split(' '));
            else button.classList.add('text-slate-700');
        });
        subnav.hidden=advertising; subnav.style.display=advertising?'none':'';
        title.textContent=advertising?'Inteligencia publicitaria':'Control de transporte';
        copy.textContent=advertising?'Revisa contratos, campañas, planes e impacto sin mezclarlo con la venta de pasajes.':'Selecciona una perspectiva operativa para revisar sus indicadores.';
        if(advertising){
            panels.forEach(panel=>{const show=panel.dataset.dashboardArea==='publicidad';panel.hidden=!show;panel.style.display=show?'':'none';});
            if(updateUrl){const url=new URL(location.href);url.searchParams.set('area','publicidad');url.searchParams.delete('tab');history.replaceState(history.state,'',url);}
            requestAnimationFrame(()=>setTimeout(()=>window.initDashboardTab('publicidad'),40));
        } else activateTransportTab(new URL(location.href).searchParams.get('tab') || 'fluvial',updateUrl);
    };

    const adButtons=[...root.querySelectorAll('[data-ad-tab]')];
    const adPanels=[...root.querySelectorAll('[data-ad-panel]')];
    const activateAdTab=name=>{
        if(!adButtons.some(button=>button.dataset.adTab===name)) name='resumen';
        adPanels.forEach(panel=>{const show=panel.dataset.adPanel===name;panel.hidden=!show;panel.style.display=show?'':'none';});
        adButtons.forEach(button=>{const selected=button.dataset.adTab===name;button.setAttribute('aria-selected',selected?'true':'false');button.classList.toggle('bg-fuchsia-700',selected);button.classList.toggle('text-white',selected);button.classList.toggle('shadow-sm',selected);button.classList.toggle('text-slate-500',!selected);});
        if(name==='campanas') requestAnimationFrame(()=>setTimeout(()=>window.initDashboardTab('publicidad'),40));
    };
    adButtons.forEach(button=>{if(button.dataset.adBound)return;button.dataset.adBound='true';button.addEventListener('click',()=>activateAdTab(button.dataset.adTab));});
    activateAdTab('resumen');

    businessButtons.forEach(button=>{if(button.dataset.bound)return;button.dataset.bound='true';button.addEventListener('click',()=>activateBusiness(button.dataset.businessTab));});
    buttons.forEach((button,index)=>{
        if(button.dataset.dashboardBound==='true') return;
        button.dataset.dashboardBound='true';
        button.addEventListener('click',()=>activateTransportTab(button.dataset.dashboardTab));
        button.addEventListener('keydown',event=>{
            if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault(); let next=index;
            if(event.key==='ArrowRight') next=(index+1)%buttons.length;
            if(event.key==='ArrowLeft') next=(index-1+buttons.length)%buttons.length;
            if(event.key==='Home') next=0; if(event.key==='End') next=buttons.length-1;
            buttons[next].focus(); activateTransportTab(buttons[next].dataset.dashboardTab);
        });
    });
    activateBusiness(root.dataset.serverArea==='publicidad'?'publicidad':'transporte',false);
};
document.addEventListener('DOMContentLoaded',window.initExecutiveDashboard);
document.addEventListener('turbo:load',window.initExecutiveDashboard);
document.addEventListener('turbo:before-cache',()=>{
    Object.values(window.nyCharts||{}).forEach(chart=>chart?.destroy());
    window.nyCharts={};
    Object.values(window.nyModeMaps||{}).forEach(map=>map?.remove());
    window.nyModeMaps={};
});
</script>
@endsection

