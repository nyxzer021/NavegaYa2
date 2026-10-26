@extends('layouts.admin')
@section('content')
<div class="space-y-4">
<section class="flex flex-col gap-3 rounded-2xl bg-gradient-to-r from-emerald-950 to-slate-900 p-5 text-white md:flex-row md:items-center md:justify-between">
    <div><p class="text-[9px] font-black uppercase tracking-[.18em] text-amber-300">Supervisión publicitaria</p><h1 class="mt-1 text-xl font-black">Carrusel y campañas</h1><p class="mt-1 text-[11px] text-emerald-100/75">Las empresas crean sus portadas; NavegaYA aprueba, programa y supervisa su exposición.</p></div>
    <div class="flex gap-2"><a href="{{ route('admin.advertising-subscriptions.index') }}" class="rounded-lg border border-white/20 px-4 py-2 text-[10px] font-black text-white">Planes</a><a href="{{ route('home') }}#publicidad" target="_blank" class="rounded-lg bg-amber-400 px-4 py-2 text-[10px] font-black text-slate-950">Ver portada pública ↗</a></div>
</section>
@if(session('success'))<div class="rounded-xl bg-emerald-50 p-3 text-xs font-bold text-emerald-800">{{ session('success') }}</div>@endif

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3"><div><h2 class="text-sm font-black text-slate-950">Vista previa del carrusel principal</h2><p class="text-[10px] text-slate-500">En cada visita se muestran hasta 6 campañas con menor exposición para repartir visibilidad entre todas las empresas.</p></div><span class="rounded-full bg-fuchsia-50 px-3 py-1 text-[9px] font-black text-fuchsia-700">{{ $carouselAds->count() }} portadas activas</span></header>
    <div class="flex snap-x gap-3 overflow-x-auto p-4">
        @forelse($carouselAds as $ad)
        <article class="relative h-44 min-w-[280px] snap-start overflow-hidden rounded-xl bg-emerald-950 sm:min-w-[360px]">
            @if($ad->creative_url)<img src="{{ $ad->creative_url }}" class="absolute inset-0 h-full w-full object-cover" alt="Portada de {{ $ad->business_name }}">@endif
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/45 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-4 text-white"><span class="rounded-full bg-amber-400 px-2 py-1 text-[8px] font-black text-slate-950">PATROCINADO</span><h3 class="mt-2 text-sm font-black">{{ $ad->title ?: $ad->business_name }}</h3><p class="mt-1 text-[9px] text-white/70">{{ $ad->business_name }} · {{ number_format($ad->views) }} vistas</p></div>
        </article>
        @empty
        <div class="w-full rounded-xl border border-dashed border-slate-300 p-8 text-center text-xs text-slate-500">Aún no hay campañas activas asignadas al carrusel principal.</div>
        @endforelse
    </div>
</section>

<section class="grid grid-cols-2 gap-2 lg:grid-cols-4">@foreach(['lead_pending'=>'Por revisar','active'=>'Publicadas','paused'=>'Pausadas','expired'=>'Vencidas'] as $key=>$label)<a href="{{ route('admin.advertisements.index',['status'=>$key]) }}" class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><span class="text-[9px] font-black uppercase text-slate-400">{{ $label }}</span><strong class="mt-1 block text-xl text-slate-950">{{ $pipeline[$key] ?? 0 }}</strong></a>@endforeach</section>

<form method="GET" class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3"><input name="search" value="{{ request('search') }}" placeholder="Empresa, contacto o WhatsApp" class="min-w-60 flex-1"><select name="status"><option value="">Todos los estados</option>@foreach(['lead_pending'=>'Por revisar','active'=>'Publicada','paused'=>'Pausada','expired'=>'Vencida'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select><button class="rounded-lg bg-[#062c21] px-4 text-xs font-black text-white">Filtrar</button></form>

<section class="grid gap-3 xl:grid-cols-2">
@forelse($ads as $ad)
<article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex gap-3 p-4">
        <div class="h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-slate-100">@if($ad->creative_url)<img src="{{ $ad->creative_url }}" class="h-full w-full object-cover" alt="">@else<div class="grid h-full place-items-center text-2xl">📣</div>@endif</div>
        <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="truncate text-sm font-black text-slate-950">{{ $ad->business_name }}</h2><span class="rounded-full px-2 py-1 text-[8px] font-black {{ $ad->status==='active'?'bg-emerald-50 text-emerald-700':($ad->status==='lead_pending'?'bg-amber-50 text-amber-700':'bg-slate-100 text-slate-600') }}">{{ ['lead_pending'=>'POR REVISAR','active'=>'PUBLICADA','paused'=>'PAUSADA','expired'=>'VENCIDA'][$ad->status] ?? strtoupper($ad->status) }}</span></div><p class="mt-1 line-clamp-1 text-[10px] text-slate-500">{{ $ad->title ?: 'Sin título comercial' }}</p><p class="mt-2 text-[9px] text-slate-400">{{ collect($ad->placements)->map(fn($p)=>$placements[$p]??$p)->join(' · ') ?: 'Sin ubicación' }} · {{ number_format($ad->views) }} vistas · {{ number_format($ad->clicks) }} clics</p></div>
    </div>
    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-2.5"><span class="text-[9px] font-semibold text-slate-400">{{ $ad->starts_on?->format('d/m/Y') ?: 'Sin inicio' }} — {{ $ad->ends_on?->format('d/m/Y') ?: 'Sin fin' }}</span><div class="flex gap-2">@if($ad->status==='lead_pending')<form method="POST" action="{{ route('admin.advertisements.approve',$ad) }}">@csrf @method('PATCH')<button class="rounded-lg bg-emerald-700 px-3 py-2 text-[9px] font-black text-white">Aprobar</button></form>@elseif($ad->status==='active')<form method="POST" action="{{ route('admin.advertisements.pause',$ad) }}">@csrf @method('PATCH')<button class="rounded-lg bg-slate-100 px-3 py-2 text-[9px] font-black">Pausar</button></form>@else<form method="POST" action="{{ route('admin.advertisements.resume',$ad) }}">@csrf @method('PATCH')<button class="rounded-lg bg-emerald-700 px-3 py-2 text-[9px] font-black text-white">Reactivar</button></form>@endif</div></div>
    <details class="border-t border-slate-100"><summary class="cursor-pointer list-none px-4 py-3 text-[10px] font-black text-emerald-800">Editar campaña y ubicación ▾</summary>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.advertisements.update',$ad) }}" class="grid max-h-[430px] gap-3 overflow-y-auto bg-slate-50 p-4 md:grid-cols-2">@csrf @method('PATCH')
            <input type="hidden" name="business_name" value="{{ $ad->business_name }}"><input type="hidden" name="business_type" value="{{ $ad->business_type ?: 'lodge_hotel' }}"><input type="hidden" name="contact_name" value="{{ $ad->contact_name ?: $ad->business_name }}"><input type="hidden" name="phone_whatsapp" value="{{ $ad->phone_whatsapp ?: 'Sin registrar' }}"><input type="hidden" name="city_destination" value="{{ $ad->city_destination ?: 'Iquitos' }}">
            <label class="text-[9px] font-black uppercase text-slate-500">Título<input required name="title" value="{{ $ad->title }}" class="mt-1 w-full"></label><label class="text-[9px] font-black uppercase text-slate-500">Enlace<input type="url" name="target_url" value="{{ $ad->target_url }}" class="mt-1 w-full"></label>
            <label class="text-[9px] font-black uppercase text-slate-500 md:col-span-2">Descripción<textarea name="description" rows="2" class="mt-1 w-full">{{ $ad->description }}</textarea></label>
            <label class="text-[9px] font-black uppercase text-slate-500">Reemplazar portada<input type="file" name="banner_image" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-[9px]"><small class="mt-1 block normal-case font-normal">1200 × 675 recomendado · mínimo 800 × 450 · máximo 1.5 MB</small></label><label class="text-[9px] font-black uppercase text-slate-500">Estado<select name="status" class="mt-1 w-full">@foreach(['lead_pending'=>'Por revisar','active'=>'Publicada','paused'=>'Pausada','expired'=>'Vencida'] as $state=>$label)<option value="{{ $state }}" @selected($ad->status===$state)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-[9px] font-black uppercase text-slate-500">Inicio<input name="starts_on" value="{{ $ad->starts_on?->format('Y-m-d') }}" type="date" class="mt-1 w-full"></label><label class="text-[9px] font-black uppercase text-slate-500">Fin<input name="ends_on" value="{{ $ad->ends_on?->format('Y-m-d') }}" type="date" class="mt-1 w-full"></label>
            <fieldset class="md:col-span-2"><legend class="text-[9px] font-black uppercase text-slate-500">Dónde aparecerá</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($placements as $value=>$label)<label class="flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-[10px]"><input type="checkbox" name="placements[]" value="{{ $value }}" @checked(in_array($value,$ad->placements??[]))><span>{{ $label }}</span></label>@endforeach</div></fieldset>
            <label class="text-[9px] font-black uppercase text-slate-500 md:col-span-2">Notas internas<textarea name="admin_notes" rows="2" class="mt-1 w-full">{{ $ad->admin_notes }}</textarea></label><input type="hidden" name="monthly_fee" value="{{ $ad->monthly_fee }}"><button class="rounded-lg bg-amber-400 py-2.5 text-xs font-black text-slate-950 md:col-span-2">Guardar cambios</button>
        </form>
    </details>
</article>
@empty<div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-xs text-slate-500 xl:col-span-2">No hay campañas con estos filtros.</div>@endforelse
</section>
{{ $ads->links() }}
</div>
@endsection
