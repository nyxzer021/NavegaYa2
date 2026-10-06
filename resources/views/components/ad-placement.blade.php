@props(['ads', 'compact' => false])
@if($ads->isNotEmpty())
<section {{ $attributes->merge(['class' => 'grid gap-3']) }} aria-label="Contenido patrocinado">
    @foreach($ads as $ad)
        @php $href = $ad->target_url ? route('discover.ad', $ad) : route('advertising.create'); @endphp
        <a href="{{ $href }}" @if($ad->target_url) target="_blank" rel="sponsored noopener" @endif class="group overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg {{ $compact ? 'flex min-h-24' : 'block' }}">
            @if($ad->creative_url)<img src="{{ $ad->creative_url }}" alt="Promoción de {{ $ad->business_name }}" class="{{ $compact ? 'hidden w-40 sm:block' : 'h-36 w-full' }} object-cover">@endif
            <div class="flex flex-1 items-center justify-between gap-4 p-4">
                <div><span class="text-[9px] font-black uppercase tracking-[.16em] text-amber-700">Patrocinado · {{ $ad->city_destination ?: ($ad->city?->name ?? 'Loreto') }}</span><h3 class="mt-1 text-sm font-black text-slate-900 group-hover:text-emerald-800">{{ $ad->title ?: $ad->business_name }}</h3><p class="mt-1 line-clamp-2 text-[11px] text-slate-500">{{ $ad->description ?: $ad->category }}</p></div>
                <span class="shrink-0 rounded-xl bg-amber-400 px-3 py-2 text-[10px] font-black text-slate-950">Ver oferta →</span>
            </div>
        </a>
    @endforeach
</section>
@endif
