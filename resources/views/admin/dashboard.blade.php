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
<div id="executiveDashboard" class="w-full space-y-4 text-slate-800">
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
            <button type="button" data-business-tab="transporte" data-business-active="border-emerald-600 bg-emerald-950 text-white shadow-lg" aria-selected="true" class="flex min-h-16 items-center gap-3 rounded-2xl border border-transparent px-4 text-left transition hover:border-emerald-300 hover:bg-white"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 text-xl">🚤</span><span><strong class="block text-sm font-black">Transporte de pasajeros</strong><small class="block text-[9px] font-semibold opacity-70">Fluvial, aéreo, empresas, carga y finanzas</small></span><span class="ml-auto">→</span></button>
            <button type="button" data-business-tab="publicidad" data-business-active="border-fuchsia-500 bg-fuchsia-950 text-white shadow-lg" aria-selected="false" class="flex min-h-16 items-center gap-3 rounded-2xl border border-transparent px-4 text-left text-slate-700 transition hover:border-fuchsia-300 hover:bg-white"><span class="grid h-10 w-10 place-items-center rounded-xl bg-fuchsia-100 text-xl">📣</span><span><strong class="block text-sm font-black">Publicidad y anunciantes</strong><small class="block text-[9px] font-semibold opacity-70">Contratos, campañas, planes e impacto</small></span><span class="ml-auto">→</span></button>
        </div>
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div><h3 data-analysis-title class="text-sm font-black text-slate-950">Control de transporte</h3><p data-analysis-copy class="text-[10px] text-slate-400">Selecciona una perspectiva operativa para revisar sus indicadores.</p></div>
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
        </div>

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

        <div data-dashboard-panel="publicidad" data-dashboard-area="publicidad" role="tabpanel" hidden class="space-y-4 bg-fuchsia-50/40 p-4">
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
            <div class="grid gap-4 lg:grid-cols-[1.15fr_1fr]">
                <article class="rounded-2xl bg-gradient-to-br from-fuchsia-950 via-violet-950 to-slate-950 p-5 text-white shadow-lg">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="text-[9px] font-black uppercase tracking-[.18em] text-fuchsia-300">Modelo comercial independiente</p><h4 class="mt-1 text-lg font-black">Publicidad para negocios que atienden viajeros</h4><p class="mt-2 max-w-2xl text-[10px] leading-relaxed text-slate-300">Hoteles, lodges, alojamientos, restaurantes y operadores turísticos contratan visibilidad. NavegaYA gestiona la pauta y cada negocio puede tener una ficha informativa pública.</p></div>
                        <a href="{{ route('advertising.create') }}" target="_blank" class="shrink-0 rounded-xl bg-fuchsia-400 px-4 py-3 text-[10px] font-black text-fuchsia-950 shadow-lg transition hover:bg-fuchsia-300">Ver página para anunciarse ↗</a>
                    </div>
                </article>
                <article class="rounded-2xl border border-fuchsia-200 bg-white p-4">
                    <div class="flex items-center justify-between"><div><p class="text-[9px] font-black uppercase text-fuchsia-700">Directorio turístico público</p><h4 class="text-sm font-black text-slate-950">{{ $publishedTourismCount }} fichas publicadas</h4></div><span class="rounded-full bg-amber-50 px-2.5 py-1 text-[9px] font-black text-amber-700">{{ $featuredTourismCount }} destacadas</span></div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        @foreach([['🏨','Hospedajes',$tourismDirectoryCounts['lodging'],'lodging'],['🍽️','Restaurantes',$tourismDirectoryCounts['gastronomy'],'gastronomy'],['🌿','Tours',$tourismDirectoryCounts['attraction'],'attraction']] as [$icon,$label,$count,$type])
                            <a href="{{ route('admin.destination-listings.index', $type) }}" class="rounded-xl bg-slate-50 p-3 text-center transition hover:bg-fuchsia-50"><span class="text-lg">{{ $icon }}</span><strong class="mt-1 block text-lg font-black text-slate-950">{{ $count }}</strong><span class="text-[8px] font-bold text-slate-500">{{ $label }}</span></a>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.tourism-partners.index') }}" class="mt-3 inline-flex text-[9px] font-black text-fuchsia-700">Administrar directorio completo →</a>
                </article>
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
    fluvialCapacity: [{{ $fluvialTicketsSold }}, {{ max(0, $fluvialCapacity - $fluvialTicketsSold) }}],
    airCapacity: [{{ $airTicketsSold }}, {{ max(0, $airCapacity - $airTicketsSold) }}],
    paymentMethods: [{{ $paymentMethodStats['yape_percent'] }}, {{ $paymentMethodStats['card_percent'] }}, {{ $paymentMethodStats['cash_percent'] }}],
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
        window.makeChart('advertisingPlansChart',{type:'bar',data:{labels:['Esencial','Destacado','Cobertura total'],datasets:[{data:d.advertisingPlans,backgroundColor:['#38bdf8','#8b5cf6','#f59e0b'],borderRadius:8}]},options:{...baseOptions,scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}});
        window.makeChart('advertisingPipelineChart',{type:'doughnut',data:{labels:['Activas','Pendientes','Pausadas','Vencidas'],datasets:[{data:d.advertisingPipeline.some(Number)?d.advertisingPipeline:[1],backgroundColor:d.advertisingPipeline.some(Number)?['#10b981','#f59e0b','#8b5cf6','#94a3b8']:['#cbd5e1'],borderWidth:0}]},options:{...baseOptions,cutout:'65%',plugins:{legend:{display:true,position:'bottom',labels:{boxWidth:8,font:{size:9}}}}}});
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
    if(!businessButtons.length || !buttons.length || !panels.length) return;
    const valid=buttons.map(button=>button.dataset.dashboardTab);
    const activePalette=['bg-white','bg-emerald-500','bg-sky-400','bg-amber-400','bg-violet-400','bg-orange-400','text-slate-950','text-emerald-950','text-sky-950','text-amber-950','text-violet-950','text-orange-950','shadow-md'];
    const businessPalette=['border-emerald-600','bg-emerald-950','border-fuchsia-500','bg-fuchsia-950','text-white','text-slate-700','shadow-lg'];

    const activateTransportTab=(name,updateUrl=true)=>{
        if(!valid.includes(name)) name='fluvial';
        panels.forEach(panel=>panel.hidden=panel.dataset.dashboardArea!=='transporte' || panel.dataset.dashboardPanel!==name);
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
        subnav.hidden=advertising;
        title.textContent=advertising?'Inteligencia publicitaria':'Control de transporte';
        copy.textContent=advertising?'Revisa contratos, campañas, planes e impacto sin mezclarlo con la venta de pasajes.':'Selecciona una perspectiva operativa para revisar sus indicadores.';
        if(advertising){
            panels.forEach(panel=>panel.hidden=panel.dataset.dashboardArea!=='publicidad');
            if(updateUrl){const url=new URL(location.href);url.searchParams.set('area','publicidad');url.searchParams.delete('tab');history.replaceState(history.state,'',url);}
            requestAnimationFrame(()=>setTimeout(()=>window.initDashboardTab('publicidad'),40));
        } else activateTransportTab(new URL(location.href).searchParams.get('tab') || 'fluvial',updateUrl);
    };

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
    activateBusiness(new URL(location.href).searchParams.get('area')==='publicidad'?'publicidad':'transporte',false);
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
