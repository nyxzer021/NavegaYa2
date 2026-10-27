@props(['placement' => 'home_hero', 'city' => null])

@php
    $query = \App\Models\Advertisement::query()->active()->forPlacement($placement);
    if ($city) {
        $query->where('city_destination', $city);
    }
    $ads = $query->inRandomOrder()->limit(5)->get();
    if ($ads->isNotEmpty()) {
        $ads->first()->increment('views');
    }
@endphp

@if($ads->isNotEmpty())
    <aside data-b2b-carousel {{ $attributes->merge(['class' => 'relative my-5 h-[285px] overflow-hidden rounded-2xl bg-[#062c21] shadow-xl sm:h-[330px]']) }} aria-label="Promociones destacadas">
        @foreach($ads as $index => $ad)
            @php
                $phone = preg_replace('/[^0-9]/', '', (string) $ad->phone_whatsapp);
                $href = $ad->target_url ? route('discover.ad', $ad) : 'https://wa.me/'.$phone;
            @endphp
            <article data-b2b-slide class="absolute inset-0 {{ $index ? 'hidden' : '' }}">
                @if($ad->creative_url)<img src="{{ $ad->creative_url }}" alt="Promoción de {{ $ad->business_name }}" class="absolute inset-0 h-full w-full object-cover">@endif
                <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-emerald-950/75 to-slate-900/20"></div>
                <div class="relative flex h-full max-w-2xl flex-col justify-center p-7 text-white sm:p-10">
                    <span class="w-fit rounded-md border border-amber-300/40 bg-amber-300/15 px-3 py-1 text-[9px] font-black uppercase tracking-wider text-amber-300">Patrocinado · {{ $ad->city_destination ?: 'Loreto' }}</span>
                    <h3 class="mt-4 text-2xl font-black leading-tight sm:text-3xl">{{ $ad->title ?: $ad->business_name }}</h3>
                    <p class="mt-3 line-clamp-2 text-xs leading-6 text-emerald-50/85 sm:text-sm">{{ $ad->description ?: 'Descubre esta recomendación para tu viaje por Loreto.' }}</p>
                    @if($ad->target_url || $phone)<a href="{{ $href }}" target="_blank" rel="sponsored noopener" class="mt-5 inline-flex w-fit rounded-lg bg-amber-400 px-5 py-3 text-xs font-black text-emerald-950 transition hover:bg-amber-300">Ver promoción →</a>@endif
                </div>
            </article>
        @endforeach
        @if($ads->count()>1)
            <button type="button" data-b2b-prev class="absolute bottom-5 right-16 z-10 grid h-9 w-9 place-items-center rounded-lg border border-white/20 bg-slate-950/40 text-white backdrop-blur" aria-label="Promoción anterior">‹</button>
            <button type="button" data-b2b-next class="absolute bottom-5 right-5 z-10 grid h-9 w-9 place-items-center rounded-lg border border-white/20 bg-slate-950/40 text-white backdrop-blur" aria-label="Siguiente promoción">›</button>
            <div class="absolute bottom-6 left-7 z-10 flex gap-1.5 sm:left-10">@foreach($ads as $index=>$ad)<button type="button" data-b2b-dot="{{$index}}" class="h-1.5 rounded-full bg-white/50 transition-all {{$index===0?'w-6 bg-white':'w-2'}}" aria-label="Ver promoción {{$index+1}}"></button>@endforeach</div>
        @endif
    </aside>
    <script>document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-b2b-carousel]').forEach(root=>{if(root.dataset.ready)return;root.dataset.ready='1';const slides=[...root.querySelectorAll('[data-b2b-slide]')],dots=[...root.querySelectorAll('[data-b2b-dot]')];if(slides.length<2)return;let active=0,timer;const show=index=>{active=(index+slides.length)%slides.length;slides.forEach((slide,i)=>slide.classList.toggle('hidden',i!==active));dots.forEach((dot,i)=>{dot.classList.toggle('w-6',i===active);dot.classList.toggle('w-2',i!==active);dot.classList.toggle('bg-white',i===active);dot.classList.toggle('bg-white/50',i!==active)})};const start=()=>{clearInterval(timer);timer=setInterval(()=>show(active+1),6000)};root.querySelector('[data-b2b-next]')?.addEventListener('click',()=>{show(active+1);start()});root.querySelector('[data-b2b-prev]')?.addEventListener('click',()=>{show(active-1);start()});dots.forEach(dot=>dot.addEventListener('click',()=>{show(Number(dot.dataset.b2bDot));start()}));root.addEventListener('mouseenter',()=>clearInterval(timer));root.addEventListener('mouseleave',start);start()})});</script>
@endif
