@props(['placement' => 'home_hero', 'city' => null])

@php
    $query = \App\Models\Advertisement::query()->active()->forPlacement($placement);
    if ($city) {
        $query->where('city_destination', $city);
    }
    $ad = $query->inRandomOrder()->first();
    if ($ad) {
        $ad->increment('views');
    }
    $phone = $ad ? preg_replace('/[^0-9]/', '', (string) $ad->phone_whatsapp) : '';
    $href = $ad?->target_url ? route('discover.ad', $ad) : 'https://wa.me/'.$phone;
@endphp

@if($ad)
    <aside {{ $attributes->merge(['class' => 'my-4 overflow-hidden rounded-xl border border-amber-200/80 bg-amber-50/40 p-3 shadow-sm']) }} aria-label="Recomendación patrocinada">
        <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <div class="flex items-center gap-3">
                @if($ad->creative_url)
                    <img src="{{ $ad->creative_url }}" alt="{{ $ad->business_name }}" class="h-14 w-20 rounded-lg object-cover">
                @else
                    <div class="flex h-14 w-14 items-center justify-center rounded-lg bg-emerald-800 text-xl font-black text-white">
                        {{ mb_strtoupper(mb_substr($ad->business_name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <span class="rounded bg-amber-200/70 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-800">Recomendado NavegaYA</span>
                    <h4 class="m-0 text-sm font-extrabold text-slate-900">{{ $ad->business_name }}</h4>
                    <p class="m-0 text-xs text-slate-500">Destino: {{ $ad->city_destination }}</p>
                </div>
            </div>
            @if($ad->target_url || $phone)
                <a href="{{ $href }}" target="_blank" rel="sponsored noopener" class="whitespace-nowrap rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700">
                    Ver detalles / reservar →
                </a>
            @endif
        </div>
    </aside>
@endif
