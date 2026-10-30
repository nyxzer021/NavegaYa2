@extends('layouts.admin')
@section('title','Bandeja de solicitudes · NavegaYA')
@section('header')<div><p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">Centro de control</p><h1 class="ny-page-title">Bandeja de solicitudes</h1></div>@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <section class="overflow-hidden rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-7 text-slate-950 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-5"><div><span class="text-[10px] font-black uppercase tracking-[.2em] text-emerald-700">Atención administrativa</span><h2 class="mt-2 text-3xl font-black">{{ $summary['total'] }} solicitud(es) por atender</h2><p class="mt-2 max-w-2xl text-sm text-slate-600">Solo aparecen casos que necesitan una decisión humana: afiliaciones, excepciones de pago, flota, moderación de contenido y reportes de usuarios. Los pagos confirmados se activan automáticamente.</p></div><span class="grid h-20 w-20 place-items-center rounded-3xl bg-emerald-100 text-4xl">🔔</span></div>
    </section>

    <nav class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @foreach([
            ['','Todas',$summary['total'],'📥'],['organization','Afiliaciones',$summary['organizations'],'🏢'],['payment','Excepciones de pago',$summary['payments'],'💳'],['fleet','Flota',$summary['fleet'],'🚤'],['content','Contenido y anuncios',$summary['content'],'📣'],['report','Usuarios e incidencias',$summary['reports'],'⚠️']
        ] as [$key,$label,$count,$icon])
        <a href="{{ route('admin.requests.index',$key?['type'=>$key]:[]) }}" class="rounded-2xl border p-4 shadow-sm transition {{ $activeType===$key?'border-emerald-700 bg-emerald-700 text-white':'border-slate-200 bg-white hover:border-emerald-300' }}"><span class="text-xl">{{ $icon }}</span><strong class="mt-2 block text-2xl font-black">{{ $count }}</strong><span class="text-[10px] font-black uppercase tracking-wide {{ $activeType===$key?'text-emerald-100':'text-slate-500' }}">{{ $label }}</span></a>
        @endforeach
    </nav>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h2 class="text-lg font-black text-slate-950">Pendientes ordenados por fecha</h2><p class="text-xs text-slate-500">Usa las tarjetas superiores para filtrar. Luego abre un caso para revisar sus datos y decidir en el módulo correspondiente.</p></div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800">{{ $items->count() }} pendiente(s)</span></header>
        <div class="divide-y divide-slate-100">
            @forelse($items as $item)
            <article class="grid gap-4 px-6 py-5 transition hover:bg-slate-50 lg:grid-cols-[auto_1fr_auto] lg:items-center">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-2xl">{{ $item['icon'] }}</span>
                <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-black text-slate-950">{{ $item['title'] }}</h3><span class="rounded-full bg-amber-50 px-2.5 py-1 text-[9px] font-black uppercase text-amber-800">{{ $item['status'] }}</span></div><p class="mt-1 text-xs text-slate-600">{{ $item['detail'] }}</p><p class="mt-2 text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $item['area'] }} · {{ $item['created_at']?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p></div>
                <a href="{{ $item['url'] }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-900 px-4 py-3 text-xs font-black text-white hover:bg-emerald-800">{{ $item['action'] }} →</a>
            </article>
            @empty
            <div class="px-6 py-16 text-center"><span class="text-4xl">✅</span><h3 class="mt-3 text-lg font-black">No hay solicitudes pendientes</h3><p class="mt-1 text-sm text-slate-500">Todo el trabajo administrativo está al día.</p></div>
            @endforelse
        </div>
    </section>
</div>
@endsection
