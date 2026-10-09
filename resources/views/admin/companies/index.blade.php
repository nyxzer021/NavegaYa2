@extends('layouts.admin')

@section('title', 'Empresas y Afiliaciones · NavegaYA')
@section('header')
<div>
    <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">Red comercial</p>
    <h1 class="ny-page-title">Empresas y Afiliaciones</h1>
</div>
@endsection

@section('content')
<div x-data="{ tab: @js(request('tab', 'solicitudes')) }" class="w-full min-w-0 space-y-5">
    <section class="relative overflow-hidden rounded-3xl bg-[#062c21] px-6 py-7 text-white shadow-xl sm:px-8">
        <div class="absolute inset-y-0 right-0 w-1/2 bg-gradient-to-l from-emerald-500/10 to-transparent"></div>
        <div class="relative flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
            <div>
                <span class="inline-flex rounded-full border border-emerald-400/25 bg-emerald-400/10 px-3 py-1 text-[10px] font-black uppercase tracking-[.16em] text-emerald-200">Marketplace de operadores</span>
                <h2 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Red de empresas transportistas</h2>
                <p class="mt-1 max-w-2xl text-sm text-emerald-100/70">Valida nuevas afiliaciones, compara el rendimiento comercial y administra las condiciones de cada operador.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.master-routes.index') }}" class="rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-xs font-bold text-white hover:bg-white/15">🧭 Catálogo de rutas</a>
                <a href="{{ route('admin.companies.create') }}" class="rounded-xl bg-amber-500 px-5 py-2.5 text-xs font-black text-slate-950 shadow-lg hover:bg-amber-400">＋ Registrar empresa</a>
            </div>
        </div>
    </section>

    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">✓ {{ session('success') }}</div>@endif
    @if(session('provisioned_credentials'))
        @php
            $credentials = session('provisioned_credentials');
        @endphp
        <section class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-5">
            <span class="text-[10px] font-black uppercase text-amber-800">Credenciales iniciales · copiar ahora</span><h2 class="mt-1 font-black">{{ $credentials['company'] }}</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach(['email' => 'Usuario', 'password' => 'Contraseña temporal'] as $field => $label)
                    <button type="button" onclick="navigator.clipboard.writeText(@js($credentials[$field]))" class="rounded-xl border border-amber-200 bg-white p-3 text-left text-xs"><span class="block text-[9px] uppercase text-slate-400">{{ $label }}</span><strong>{{ $credentials[$field] }}</strong><span class="float-right text-emerald-700">Copiar</span></button>
                @endforeach
            </div>
        </section>
    @endif

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @php
            $kpis = [
                ['GMV marketplace hoy', 'S/ '.number_format((float) $gmvToday, 2), 'Ventas confirmadas de operadores', '💳', 'text-slate-950'],
                ['Comisión generada', 'S/ '.number_format((float) $commissionToday, 2), 'Según acuerdos comerciales', '📈', 'text-amber-600'],
                ['Boletos emitidos', number_format($ticketsToday), "🚤 {$fluvialTicketsToday} fluviales · ✈️ {$airTicketsToday} aéreos", '🎟️', 'text-slate-950'],
                ['Operadores con salidas', $activeOperatorsToday, 'Actividad registrada hoy', '🏢', 'text-slate-950'],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-start justify-between"><span class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $kpi[0] }}</span><span class="rounded-lg bg-slate-50 p-2">{{ $kpi[3] }}</span></div><p class="mt-2 text-2xl font-black {{ $kpi[4] }}">{{ $kpi[1] }}</p><span class="text-[10px] font-semibold text-slate-400">{{ $kpi[2] }}</span></article>
        @endforeach
    </section>

    <nav class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
        <button type="button" @click="tab='solicitudes'" :class="tab==='solicitudes' ? 'bg-[#062c21] text-white shadow' : 'text-slate-500 hover:bg-slate-50'" class="whitespace-nowrap rounded-xl px-4 py-2.5 text-xs font-black transition">Solicitudes <span class="ml-1 rounded-full bg-amber-400 px-2 py-0.5 text-[9px] text-slate-950">{{ $pendingAffiliations->count() }}</span></button>
        <button type="button" @click="tab='rendimiento'" :class="tab==='rendimiento' ? 'bg-[#062c21] text-white shadow' : 'text-slate-500 hover:bg-slate-50'" class="whitespace-nowrap rounded-xl px-4 py-2.5 text-xs font-black transition">Empresas afiliadas</button>
        <button type="button" @click="tab='comisiones'" :class="tab==='comisiones' ? 'bg-[#062c21] text-white shadow' : 'text-slate-500 hover:bg-slate-50'" class="whitespace-nowrap rounded-xl px-4 py-2.5 text-xs font-black transition">Esquema de comisiones</button>
    </nav>

    <section x-show="tab==='solicitudes'" x-cloak class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-col justify-between gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center"><div><p class="text-[10px] font-black uppercase tracking-wider text-amber-600">Bandeja comercial</p><h3 class="text-lg font-black text-slate-950">Solicitudes pendientes de validación</h3><p class="text-xs text-slate-500">Revisa identidad, RUC y responsable antes de habilitar ventas.</p></div><span class="w-fit rounded-full bg-amber-50 px-3 py-1 text-[10px] font-black text-amber-700">{{ $pendingAffiliations->count() }} por revisar</span></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-xs"><thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400"><tr><th class="p-4">Empresa solicitante</th><th class="p-4">Representante y acceso</th><th class="p-4">Modalidad</th><th class="p-4">Verificación</th><th class="p-4">Fecha</th><th class="p-4 text-right">Acciones</th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($pendingAffiliations as $company)
            @php($admin = $company->users->first(fn ($user) => $user->roles->contains('code', 'company_admin')))
            <tr class="hover:bg-amber-50/30">
                <td class="p-4"><strong class="block text-sm text-slate-900">{{ $company->commercial_name ?: $company->legal_name }}</strong><span class="text-[10px] text-slate-400">{{ $company->legal_name }} · RUC {{ $company->ruc }}</span></td>
                <td class="p-4"><strong class="block text-slate-700">{{ $company->contact_name ?: ($admin->name ?? 'Sin representante') }}</strong><span class="text-[10px] text-slate-400">{{ $admin->email ?? $company->email ?? 'Correo pendiente' }} · {{ $company->phone ?? 'Sin teléfono' }}</span></td>
                <td class="p-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold">{{ match($company->modality){'aereo'=>'✈️ Aéreo','mixto'=>'🚤✈️ Mixto',default=>'🚤 Fluvial'} }}</span></td>
                <td class="p-4"><span class="inline-flex rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">● RUC por validar</span></td>
                <td class="p-4 text-slate-500">{{ $company->created_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="p-4"><div class="flex justify-end gap-2"><a href="{{ route('admin.companies.edit', $company) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-[10px] font-bold">Evaluar</a><form method="POST" action="{{ route('admin.companies.toggle-status', $company) }}">@csrf @method('PATCH')<button class="rounded-lg bg-emerald-600 px-3 py-2 text-[10px] font-black text-white">Aprobar</button></form></div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-12 text-center"><span class="text-2xl">✓</span><strong class="mt-2 block text-slate-700">Bandeja al día</strong><span class="text-xs text-slate-400">No hay solicitudes pendientes de validación.</span></td></tr>
        @endforelse
        </tbody></table></div>
    </section>

    <section x-show="tab==='rendimiento'" x-cloak class="space-y-4">
        <form method="GET" action="{{ route('admin.companies.index') }}" class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <input type="hidden" name="tab" value="rendimiento"><input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar empresa o RUC" class="h-10 min-w-64 flex-1 rounded-xl border-slate-200 bg-slate-50 text-xs">
            <select name="status" class="h-10 rounded-xl border-slate-200 bg-slate-50 text-xs"><option value="">Todos los estados</option><option value="active" @selected(request('status')==='active')>Activos</option><option value="pending_verification" @selected(request('status')==='pending_verification')>Pendientes</option><option value="suspended" @selected(request('status')==='suspended')>Suspendidos</option></select><button class="rounded-xl bg-[#062c21] px-5 text-xs font-bold text-white">Filtrar</button><a href="{{ route('admin.companies.index', ['tab' => 'rendimiento']) }}" class="px-3 py-3 text-xs font-bold text-emerald-700">Limpiar</a>
        </form>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-col justify-between gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center"><div><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Rendimiento comercial de hoy</p><h3 class="text-lg font-black text-slate-950">Empresas afiliadas</h3></div><a href="{{ route('admin.itineraries.index') }}" class="text-xs font-black text-emerald-700">Ver inventario de salidas →</a></header>
            <div class="overflow-x-auto"><table class="w-full min-w-[1250px] text-left text-xs"><thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400"><tr><th class="p-4">Empresa</th><th class="p-4">Administrador</th><th class="p-4 text-center">Salidas hoy</th><th class="p-4 text-center">Boletos hoy</th><th class="p-4">Ocupación</th><th class="p-4 text-right">GMV hoy</th><th class="p-4 text-right">Comisión</th><th class="p-4">Operación</th><th class="p-4 text-right">Gestión</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($companies as $company)
                @php($admin = $company->users->first(fn ($user) => $user->roles->contains('code', 'company_admin')))
                @php($fleet = (int) $company->active_vessels_count + (int) $company->active_aircraft_count)
                @php($departures = (int) $company->route_departures_count + (int) $company->air_departures_count)
                <tr class="hover:bg-slate-50/70">
                    <td class="p-4"><a href="{{ route('admin.companies.show', $company) }}" class="block font-black text-slate-900 hover:text-emerald-700 hover:underline">{{ $company->commercial_name ?: $company->legal_name }}</a><span class="text-[10px] text-slate-400">RUC {{ $company->ruc }} · {{ match($company->modality){'aereo'=>'✈ Aéreo','mixto'=>'🚤 + ✈ Mixto',default=>'🚤 Fluvial'} }}</span></td>
                    <td class="p-4"><strong class="block text-slate-700">{{ $admin->name ?? 'Sin administrador' }}</strong><span class="text-[10px] text-slate-400">{{ $admin->email ?? 'Credencial pendiente' }}</span></td><td class="p-4 text-center text-base font-black">{{ $company->today_departures_count }}</td><td class="p-4 text-center text-base font-black">{{ $company->today_tickets_count }}</td>
                    <td class="p-4"><div class="flex items-center gap-2"><div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $company->today_occupancy }}%"></div></div><strong>{{ $company->today_occupancy }}%</strong></div></td>
                    <td class="p-4 text-right font-black">S/ {{ number_format((float) $company->today_gmv, 2) }}</td><td class="p-4 text-right"><strong class="text-emerald-700">S/ {{ number_format((float) $company->today_commission, 2) }}</strong><span class="block text-[9px] text-slate-400">{{ number_format((float) $company->commission_rate, 2) }}%</span></td>
                    <td class="p-4"><a href="{{ route('admin.companies.show', $company) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold hover:border-emerald-300 hover:bg-emerald-50"><span>{{ match($company->modality){'aereo'=>'✈️','mixto'=>'🚤✈️',default=>'🚤'} }}</span><span>{{ $fleet }} unidad(es)</span><span class="text-emerald-600">→</span></a><span class="mt-1 block text-[10px] text-slate-400">{{ $departures }} salidas registradas</span></td>
                    <td class="p-4"><div class="flex justify-end gap-2"><a href="{{ route('admin.companies.edit', $company) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-[10px] font-bold">Permisos / Editar</a><form method="POST" action="{{ route('admin.companies.toggle-status', $company) }}">@csrf @method('PATCH')<button class="rounded-lg px-3 py-2 text-[10px] font-bold {{ $company->status==='active'?'bg-rose-50 text-rose-700':'bg-emerald-50 text-emerald-700' }}">{{ $company->status==='active'?'Suspender':'Activar' }}</button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="9" class="p-12 text-center text-slate-400">No se encontraron empresas con estos filtros.</td></tr>
            @endforelse
            </tbody></table></div><div class="border-t border-slate-100 p-4">{{ $companies->links() }}</div>
        </div>
    </section>

    <section x-show="tab==='comisiones'" x-cloak class="grid gap-4 md:grid-cols-2">
        @foreach(['fluvial' => ['🚤', 'Pasajes fluviales'], 'aereo' => ['✈️', 'Pasajes aéreos']] as $channel => $meta)
            @php($summary = $commissionSummary->get($channel))
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><span class="text-2xl">{{ $meta[0] }}</span><span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black text-emerald-700">{{ $summary?->operators_count ?? 0 }} operadores activos</span></div><h3 class="mt-4 text-lg font-black text-slate-950">{{ $meta[1] }}</h3><p class="mt-1 text-xs text-slate-500">Promedio contractual vigente sobre cada boleto procesado en la plataforma.</p><p class="mt-5 text-3xl font-black text-[#062c21]">{{ number_format((float) ($summary?->average_rate ?? 0), 2) }}%</p><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Comisión promedio configurada</span></article>
        @endforeach
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 md:col-span-2"><strong>Modelo comercial:</strong> la comisión se calcula sobre pagos confirmados. Cada operador puede tener una tasa propia administrada desde “Permisos / Editar”.</div>
    </section>
</div>
@endsection

