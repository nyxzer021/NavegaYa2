@extends('layouts.company')
@section('title', 'Publicidad y campañas')
@section('page-title', 'Publicidad y campañas')
@section('content')
@php($activeCount = $campaigns->whereIn('status', ['lead_pending', 'active', 'paused'])->count())
<div class="mx-auto max-w-6xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-2xl font-black text-slate-950">Promociona tus rutas y servicios</h2>
        <p class="mt-1 max-w-2xl text-sm text-slate-500">Carga una imagen, elige dónde aparecerá y revisa su rendimiento. NavegaYA reparte la exposición entre empresas sin repetir al mismo anunciante en una página.</p>
    </section>

    <section class="grid gap-4 sm:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-sm font-semibold text-slate-500">En uso</span>
            <strong class="mt-2 block text-2xl font-black text-slate-950">{{ $activeCount }}</strong>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-sm font-semibold text-slate-500">Cupo del plan</span>
            <strong class="mt-2 block text-2xl font-black text-slate-950">{{ $limit ?: '—' }}</strong>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-sm font-semibold text-slate-500">Vistas</span>
            <strong class="mt-2 block text-2xl font-black text-slate-950">{{ number_format($campaigns->sum('views')) }}</strong>
        </article>
    </section>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid items-start gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-lg font-black text-slate-950">Nuevo anuncio</h3>
            <p class="mt-1 text-sm text-slate-500">La publicidad usa el cupo del plan. No genera un cobro aparte.</p>
            @if(! $subscription)
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-relaxed text-amber-950">
                    <strong class="block">Plan publicitario pendiente</strong>
                    <span class="mt-1 block">Pide la activación y NavegaYA asignará el cupo de tu empresa.</span>
                    <a href="{{ route('messages.index', ['asunto' => 'Solicitud de activación del plan publicitario', 'mensaje' => 'Solicito la activación del plan publicitario de mi empresa.']) }}" class="mt-3 inline-flex min-h-10 items-center rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white">Solicitar activación</a>
                </div>
            @endif
            <form method="POST" action="{{ route('company.advertising.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <label class="block text-sm font-semibold text-slate-700">Título del anuncio<input name="title" value="{{ old('title') }}" required maxlength="160" placeholder="Ej. Viaja de Iquitos a Nauta" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50 px-3 text-sm font-semibold"></label>
                <label class="block text-sm font-semibold text-slate-700">Ubicación<select name="placement" required class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50 px-3 text-sm font-semibold">@foreach($placements as $key => $label)<option value="{{ $key }}" @disabled(! in_array($key, $allowedPlacements, true))>{{ $label }}{{ ! in_array($key, $allowedPlacements, true) ? ' · no incluido' : '' }}</option>@endforeach</select></label>
                <label class="block text-sm font-semibold text-slate-700">Descripción breve<textarea name="description" rows="3" maxlength="500" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 p-3 text-sm font-semibold">{{ old('description') }}</textarea></label>
                <label class="block rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                    <strong class="block text-sm text-slate-800">Imagen horizontal</strong>
                    <span class="mt-1 block text-sm text-slate-500">Mínimo 800 × 450 px. Se optimiza al guardar.</span>
                    <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required class="mt-3 block w-full text-sm">
                </label>
                <button @disabled(! $subscription || ($limit > 0 && $activeCount >= $limit)) class="h-11 w-full rounded-xl bg-amber-400 text-sm font-black text-emerald-950 disabled:cursor-not-allowed disabled:opacity-50">Enviar a revisión</button>
            </form>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-5">
                <h3 class="text-lg font-black text-slate-950">Mis campañas</h3>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ \App\Support\SpanishCount::phrase($campaigns->count(), 'campaña', 'campañas') }}</span>
            </header>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                @forelse($campaigns as $campaign)
                    <article class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="aspect-[16/9] bg-slate-100">
                            @if($campaign->creative_url)
                                <img src="{{ $campaign->creative_url }}" class="h-full w-full object-cover" alt="{{ $campaign->title }}">
                            @endif
                        </div>
                        <div class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="font-black text-slate-950">{{ $campaign->title }}</h4>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $campaign->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($campaign->status === 'lead_pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ $campaign->status === 'active' ? 'Publicada' : \App\Support\PanelLabel::status($campaign->status) }}</span>
                            </div>
                            <p class="text-sm text-slate-500">{{ collect($campaign->placements)->map(fn ($placement) => $placements[$placement] ?? $placement)->join(', ') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-slate-50 p-3"><span class="text-sm text-slate-500">Vistas</span><strong class="block text-lg text-slate-950">{{ number_format($campaign->views) }}</strong></div>
                                <div class="rounded-xl bg-slate-50 p-3"><span class="text-sm text-slate-500">Clics</span><strong class="block text-lg text-slate-950">{{ number_format($campaign->clicks) }}</strong></div>
                            </div>
                            @if($campaign->status === 'active')
                                <form method="POST" action="{{ route('company.advertising.pause', $campaign) }}">
                                    @csrf @method('PATCH')
                                    <button class="min-h-10 rounded-xl border border-rose-200 px-4 text-sm font-bold text-rose-700">Pausar campaña</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-14 text-center md:col-span-2">
                        <strong class="block text-slate-800">Aún no hay campañas</strong>
                        <p class="mt-1 text-sm text-slate-500">La primera aparecerá aquí después de enviarla.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
