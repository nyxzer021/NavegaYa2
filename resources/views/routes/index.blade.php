<x-public-layout current="routes" title="Rutas y horarios · NavegaYA">
@php
    $searched = request()->hasAny(['origin','destination','date']);
@endphp
<div class="min-h-screen bg-slate-50">
<div class="mx-auto max-w-7xl px-4 pb-4 sm:px-6 lg:px-8">
<x-amazon-hero-bg theme="routes" class="flex w-full items-center justify-center shadow-lg" overlay="from-slate-950/78 via-[#062c21]/58 to-[#062c21]/25">
<div class="mx-auto max-w-4xl px-4 pb-14 pt-4 text-center sm:pb-16">
<div class="mb-3 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/20 px-3.5 py-1 text-[11px] font-bold text-emerald-200 backdrop-blur-sm"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span><span>Rutas Fluviales y Aéreas de Loreto</span></div>
<h1 class="text-3xl font-black tracking-tight text-white drop-shadow-md sm:text-4xl">Rutas Fluviales y Vuelos en Loreto</h1>
<p class="mx-auto mt-2 max-w-2xl text-xs font-medium text-emerald-100/90 sm:text-sm">Itinerarios de zarpe y conexiones bimodales entre Iquitos, Yurimaguas, Nauta y la frontera amazónica.</p>
</div>
</x-amazon-hero-bg><div class="relative z-20 mx-auto mt-4 max-w-5xl px-2 sm:mt-5 sm:px-4"><div class="rounded-3xl border border-slate-100/80 bg-white p-4 shadow-xl shadow-slate-900/10 sm:p-6">
<div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3"><nav class="inline-flex rounded-xl bg-slate-100 p-1 text-xs"><a href="{{route('routes.index',['transport'=>'river'])}}" class="flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 font-semibold text-emerald-900 shadow-sm transition">🚤 Fluvial</a><a href="{{route('routes.index',['transport'=>'air'])}}" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium text-slate-500 transition hover:text-slate-800">✈️ Aéreo</a></nav><nav class="hidden flex-wrap items-center gap-1.5 text-xs text-slate-500 sm:flex"><span class="mr-1 text-[11px] font-semibold text-slate-400">Rutas frecuentes:</span>@foreach($groups->keys()->take(4) as $name)<a href="{{route('routes.index',['origin'=>str($name)->before(' → '),'destination'=>str($name)->after(' → ')])}}" class="whitespace-nowrap rounded-lg border border-slate-200/70 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-800">{{$name}}</a>@endforeach</nav></div>
<form action="{{route('routes.index')}}" method="GET" class="grid grid-cols-1 items-center gap-1.5 rounded-2xl border border-slate-200/80 bg-slate-50 p-1.5 sm:grid-cols-2 lg:grid-cols-12"><input type="hidden" name="transport" value="river">
@php($cityOptions=collect($cities)->mapWithKeys(fn($city)=>[$city=>$city])->all())
<x-public-select name="origin" label="Origen" icon="📍" placeholder="Selecciona ciudad" :options="$cityOptions" :selected="request('origin')" class="lg:col-span-4" />
<x-public-select name="destination" label="Destino" icon="🎯" placeholder="Selecciona ciudad" :options="$cityOptions" :selected="request('destination')" class="lg:col-span-4" />
<label class="flex h-[58px] flex-col justify-center rounded-lg border border-slate-200 bg-white px-3 shadow-sm transition hover:border-emerald-300 focus-within:border-emerald-600 focus-within:ring-4 focus-within:ring-emerald-600/10 lg:col-span-2"><span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Fecha de salida</span><input type="date" name="date" value="{{request('date')}}" class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm font-bold text-slate-800 focus:ring-0"></label><div class="lg:col-span-2"><button type="submit" class="flex h-[58px] w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 text-sm font-black text-slate-950 shadow-sm transition hover:-translate-y-0.5 hover:bg-amber-400 hover:shadow-md active:translate-y-0"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 10.5 18a7.5 7.5 0 0 0 6.15-3.35z"/></svg>Buscar rutas</button></div></form>
</div></div></div>
<main class="mx-auto max-w-6xl px-4 pb-16 pt-4"><x-b2b-ad-banner placement="routes_sidebar" class="mb-5" /><div class="mb-5 flex items-end justify-between"><div><span class="text-[10px] font-bold uppercase tracking-widest text-emerald-700">Transporte fluvial</span><h2 class="text-xl font-bold text-slate-900">Salidas disponibles</h2></div><a href="{{route('routes.index')}}" class="text-xs font-semibold text-emerald-700">Limpiar filtros</a></div>
@forelse($dateGroups as $date=>$items)<div class="mb-3 mt-7 flex items-center gap-3"><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase text-emerald-800">{{\Carbon\Carbon::parse($date)->isToday()?'Hoy':(\Carbon\Carbon::parse($date)->isTomorrow()?'Mañana':\Carbon\Carbon::parse($date)->translatedFormat('D d M'))}}</span><span class="text-xs capitalize text-slate-500">{{\Carbon\Carbon::parse($date)->translatedFormat('l d \d\e F')}}</span></div>
@foreach($items as $departure)
<x-departure-card :departure="$departure" />
@endforeach
@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center"><h3 class="text-sm font-bold text-slate-800">No encontramos salidas</h3><p class="mt-1 text-xs text-slate-500">Prueba con otra fecha, origen o destino.</p></div>@endforelse
</main></div>
</x-public-layout>












