<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">Monitor regional de operaciones</h2></x-slot>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" x-data="{ openCompany: {{ $selectedOrganizationId ? (int)$selectedOrganizationId : 'null' }}, period: 'all', search: '' }">
 @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">✓ {{ session('success') }}</div>@endif

 <section class="flex flex-col justify-between gap-5 rounded-3xl border border-emerald-900/60 bg-[#062c21] p-6 text-white shadow-xl sm:p-8 md:flex-row md:items-center">
  <div class="max-w-3xl"><span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/15 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Auditoría regional · Solo lectura</span><h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Empresas, rutas y salidas</h1><p class="mt-1 text-xs text-emerald-100/75 sm:text-sm">Supervisa operadores, ocupación, permisos y manifiestos sin intervenir en el despacho diario.</p></div>
  <div class="flex shrink-0 flex-wrap gap-2.5"><a href="{{ route('admin.master-routes.index') }}" class="inline-flex h-10 items-center rounded-xl bg-amber-500 px-5 text-xs font-black text-slate-950 shadow-md hover:bg-amber-600">🗺️ Tramos maestros</a></div>
 </section>

 @if($isSuperAdmin)
 <section class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:grid-cols-3">
  <div class="border-b border-slate-100 p-5 sm:border-b-0 sm:border-r"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Boletos emitidos hoy</span><div class="mt-1 flex items-end gap-2"><strong class="text-2xl font-black text-slate-900">{{ number_format($todayTickets) }}</strong><span class="pb-1 text-[10px] font-semibold text-emerald-700">regional</span></div></div>
  <div class="border-b border-slate-100 p-5 sm:border-b-0 sm:border-r"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">GMV confirmado hoy</span><strong class="mt-1 block text-2xl font-black text-slate-900">S/ {{ number_format($globalRevenue,2) }}</strong></div>
  <div class="p-5"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Comisión neta NavegaYA</span><strong class="mt-1 block text-2xl font-black text-emerald-800">S/ {{ number_format($netCommission,2) }}</strong></div>
 </section>
 @endif

 <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
  <form method="GET" action="{{ route('admin.transport-routes.index') }}" class="grid gap-3 md:grid-cols-[minmax(260px,420px)_1fr] md:items-end">
   <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Filtrar directorio</span><select name="organization_id" onchange="this.form.submit()" class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700 focus:border-emerald-700 focus:ring-emerald-700"><option value="">Todas las empresas transportistas</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((int)$selectedOrganizationId===$company->id)>{{ $company->commercial_name ?: $company->legal_name }}</option>@endforeach</select></label>
   <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end"><div class="flex flex-wrap gap-1.5">@foreach(['all'=>'Todas','today'=>'Hoy','tomorrow'=>'Mañana','week'=>'Esta semana'] as $key=>$label)<button type="button" @click="period='{{ $key }}'" :class="period==='{{ $key }}'?'bg-[#062c21] text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="rounded-lg px-3 py-2 text-[11px] font-bold">{{ $label }}</button>@endforeach</div><input x-model.debounce.200ms="search" type="search" placeholder="Buscar ruta, puerto o nave…" class="h-10 min-w-64 rounded-xl border-slate-200 bg-slate-50 px-3 text-xs"></div>
  </form>
 </section>

 <section>
  <div class="mb-3 flex items-end justify-between"><div><span class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Directorio operativo</span><h2 class="text-xl font-black text-slate-900">Empresas transportistas</h2></div><span class="text-xs font-semibold text-slate-400">{{ $companies->count() }} operador(es)</span></div>
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
   @forelse($companies as $company)
    @php
     $companyDepartures=$groupedDepartures->get($company->id,collect());
     $todayCount=$companyDepartures->filter(fn($departure)=>$departure->departure_at->isToday())->count();
     $hasRiver=(int)($company->active_vessels_count??0)>0;
     $hasAir=(int)($company->active_aircraft_count??0)>0;
     $activeFleet=(int)($company->active_vessels_count??0)+(int)($company->active_aircraft_count??0);
     $inspectionValid=$company->verified_at && (!$company->vessels_min_inspection_expires_at || \Illuminate\Support\Carbon::parse($company->vessels_min_inspection_expires_at)->isToday() || \Illuminate\Support\Carbon::parse($company->vessels_min_inspection_expires_at)->isFuture());
     $initials=collect(preg_split('/\s+/',trim($company->commercial_name?:$company->legal_name)))->filter()->take(2)->map(fn($word)=>mb_strtoupper(mb_substr($word,0,1)))->implode('');
    @endphp
    <button type="button" @click="openCompany = openCompany === {{ $company->id }} ? null : {{ $company->id }}" :aria-expanded="openCompany === {{ $company->id }}" class="group relative overflow-hidden rounded-3xl border bg-white p-5 text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-500/50 hover:shadow-xl" :class="openCompany === {{ $company->id }} ? 'border-emerald-600 ring-2 ring-emerald-600/10' : 'border-slate-200'">
     <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-800 via-emerald-500 to-amber-400"></div>
     <div class="flex items-start justify-between gap-3"><div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-emerald-100 bg-emerald-50 text-base font-black text-emerald-900">@if($company->logo_path)<img src="{{ Storage::url($company->logo_path) }}" alt="" class="h-full w-full object-cover">@else{{ $initials ?: 'NY' }}@endif</div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500" x-text="openCompany === {{ $company->id }} ? 'Cerrar ↑' : 'Ver salidas ↓'"></span></div>
     <h3 class="mt-4 text-base font-black text-slate-900 transition group-hover:text-emerald-800">{{ $company->commercial_name ?: $company->legal_name }}</h3><p class="mt-0.5 text-[11px] text-slate-400">{{ $company->legal_name }} · RUC {{ $company->ruc ?: 'por verificar' }}</p>
     <div class="mt-3 flex flex-wrap gap-1.5">@if($hasRiver)<span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-800">🚤 Fluvial</span>@endif @if($hasAir)<span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-sky-800">✈️ Aéreo</span>@endif @if(!$hasRiver&&!$hasAir)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Flota pendiente</span>@endif <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $company->verified_at?'border-emerald-200 bg-emerald-50 text-emerald-800':'border-amber-200 bg-amber-50 text-amber-800' }}">{{ $company->verified_at?'✓ Operador verificado':'⚠ Revisión pendiente' }}</span><span class="rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $inspectionValid?'border-emerald-200 bg-emerald-50 text-emerald-800':'border-amber-200 bg-amber-50 text-amber-800' }}">DICAPI / inspección: {{ $inspectionValid?'vigente':'por validar' }}</span></div>
     <div class="mt-5 grid grid-cols-2 divide-x divide-slate-100 rounded-2xl bg-slate-50 p-3"><div class="pr-3"><strong class="block text-xl font-black text-slate-900">{{ $todayCount }}</strong><span class="text-[10px] font-semibold leading-tight text-slate-500">salidas programadas hoy</span></div><div class="pl-3"><strong class="block text-xl font-black text-slate-900">{{ $activeFleet }}</strong><span class="text-[10px] font-semibold leading-tight text-slate-500">naves activas</span></div></div>
    </button>
   @empty<div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-400">No hay empresas transportistas activas.</div>@endforelse
  </div>
 </section>

 @foreach($companies as $company)
  @php
   $companyDepartures = $groupedDepartures->get($company->id, collect());
  @endphp
  <section x-show="openCompany === {{ $company->id }}" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak class="overflow-hidden rounded-3xl border border-emerald-900/20 bg-white shadow-xl">
   <header class="flex flex-col gap-3 bg-[#062c21] px-5 py-5 text-white sm:flex-row sm:items-center sm:justify-between"><div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Detalle cronológico</span><h2 class="mt-0.5 text-lg font-black">{{ $company->commercial_name ?: $company->legal_name }}</h2><p class="text-[11px] text-emerald-100/65">RUC {{ $company->ruc ?: 'por verificar' }} · {{ $companyDepartures->count() }} salida(s) futura(s)</p></div><button type="button" @click="openCompany=null" class="w-fit rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-[11px] font-bold hover:bg-white/20">Cerrar detalle ×</button></header>
   <div class="overflow-x-auto"><table class="w-full min-w-[1080px] text-left text-xs text-slate-600"><thead class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-400"><tr><th class="py-3 pl-5">Hora</th><th class="px-4 py-3">Ruta / puertos</th><th class="px-4 py-3">Nave / matrícula</th><th class="px-4 py-3">Ocupación</th><th class="px-4 py-3">Tarifa</th><th class="px-4 py-3">Estado</th><th class="py-3 pr-5 text-right">Acciones</th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse($companyDepartures as $departure)
     @php
      $booked=$departure->reservations->whereIn('status',['pending_payment','confirmed'])->sum(fn($reservation)=>$reservation->seats->count());$capacity=max(1,(int)($departure->vessel->seat_capacity??0));$occupancy=min(100,(int)round(($booked/$capacity)*100));
      $day=$departure->departure_at->isToday()?'today':($departure->departure_at->isTomorrow()?'tomorrow':($departure->departure_at->lte(now()->addWeek()->endOfDay())?'week':'later'));
      $searchText=mb_strtolower(implode(' ',[$departure->transportRoute->originPort->city??'',$departure->transportRoute->destinationPort->city??'',$departure->transportRoute->originPort->name??'',$departure->transportRoute->destinationPort->name??'',$departure->vessel->name??'']));
      $status=match($departure->status){'boarding'=>['Abordando','bg-amber-50 text-amber-800 border-amber-200'],'departed'=>['Zarpó','bg-sky-50 text-sky-800 border-sky-200'],'cancelled'=>['Cancelado','bg-red-50 text-red-700 border-red-200'],default=>['Programado','bg-emerald-50 text-emerald-800 border-emerald-200']};
     @endphp
     <tr data-day="{{ $day }}" data-search="{{ $searchText }}" x-show="(period==='all'||period===$el.dataset.day||(period==='week'&&['today','tomorrow','week'].includes($el.dataset.day)))&&$el.dataset.search.includes(search.toLowerCase())" class="hover:bg-slate-50/80">
      <td class="py-4 pl-5"><strong class="block text-sm text-slate-900">{{ $departure->departure_at?->format('H:i') ?? '--:--' }}</strong><span class="text-[10px] text-slate-400">{{ $departure->departure_at?->translatedFormat('d M Y') ?? 'Sin fecha' }}</span></td>
      <td class="px-4 py-4"><strong class="block text-slate-900">{{ $departure->transportRoute->originPort->city ?? 'Origen pendiente' }} ➔ {{ $departure->transportRoute->destinationPort->city ?? 'Destino pendiente' }}</strong><span class="block max-w-[250px] truncate text-[10px] text-slate-400">{{ $departure->transportRoute->originPort->name ?? 'Puerto pendiente' }} ➔ {{ $departure->transportRoute->destinationPort->name ?? 'Puerto pendiente' }}</span><span class="mt-1 block font-mono text-[9px] text-slate-400">{{ $departure->transportRoute->code ?? 'Sin código' }}</span></td>
      <td class="px-4 py-4"><strong class="block text-slate-800">{{ $departure->vessel->name ?? 'Nave sin asignar' }}</strong><span class="text-[10px] text-slate-400">{{ $departure->vessel->registration_number ?? 'Sin matrícula' }}</span></td>
      <td class="px-4 py-4"><div class="w-32"><div class="mb-1 flex justify-between text-[10px] font-bold"><span>{{ $booked }}/{{ $capacity }}</span><span>{{ $occupancy }}%</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $occupancy>=90?'bg-amber-500':'bg-emerald-600' }}" style="width:{{ $occupancy }}%"></div></div></div></td>
      <td class="px-4 py-4 font-black text-slate-900">S/ {{ number_format((float)($departure->fare??0),2) }}</td><td class="px-4 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $status[1] }}">{{ $status[0] }}</span></td>
      <td class="py-4 pr-5"><div class="flex justify-end"><a href="{{ route('admin.manifests.show',$departure) }}" class="inline-flex h-8 items-center rounded-lg bg-[#062c21] px-3 text-[10px] font-bold text-white hover:bg-emerald-900">📄 Manifiesto DICAPI</a></div></td>
     </tr>
    @empty<tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">Este operador no tiene salidas futuras programadas.</td></tr>@endforelse
   </tbody></table></div>
  </section>
 @endforeach
</div>
</x-app-layout>
