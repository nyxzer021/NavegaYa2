@props(['ad'])
@if($ad)
<div id="promotional-popup" class="fixed inset-0 z-[60] hidden items-end justify-center bg-slate-950/65 p-0 backdrop-blur-[2px] sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="promotional-popup-title" data-ad-id="{{$ad->id}}" data-impression-url="{{route('discover.ad.impression',$ad)}}">
    <article class="relative grid max-h-[88vh] w-full max-w-[760px] overflow-y-auto rounded-t-[28px] bg-white shadow-2xl sm:max-h-[min(620px,88vh)] sm:grid-cols-[1.08fr_.92fr] sm:overflow-hidden sm:rounded-[28px]">
        <button type="button" data-promo-close class="absolute right-3 top-3 z-20 grid h-11 w-11 place-items-center rounded-full border border-white/50 bg-slate-950/65 text-2xl font-light text-white shadow-lg backdrop-blur transition hover:bg-slate-950 focus:outline-none focus:ring-4 focus:ring-amber-300" aria-label="Cerrar promoción">×</button>
        <div class="relative min-h-[210px] bg-emerald-950 sm:min-h-[430px]">
            @if($ad->creative_url)<img src="{{$ad->creative_url}}" alt="Promoción de {{$ad->business_name}}" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">@endif
            <div class="absolute inset-0 bg-gradient-to-t from-emerald-950/85 via-transparent to-slate-950/20 sm:bg-gradient-to-r sm:from-transparent sm:to-slate-950/10"></div>
            <span class="absolute bottom-4 left-4 rounded-full border border-white/30 bg-slate-950/55 px-3 py-1 text-[9px] font-black uppercase tracking-[.16em] text-white backdrop-blur">Patrocinado</span>
        </div>
        <div class="flex flex-col justify-center p-6 sm:p-8"><p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">{{$ad->city?->name ?? $ad->city_destination ?? 'Loreto'}} · {{$ad->category ?: 'Experiencia local'}}</p><h2 id="promotional-popup-title" class="mt-2 text-2xl font-black leading-tight text-slate-950 sm:text-3xl">{{$ad->title ?: $ad->business_name}}</h2>@if($ad->title && $ad->title!==$ad->business_name)<p class="mt-1 text-xs font-bold text-slate-400">{{$ad->business_name}}</p>@endif<p class="mt-4 line-clamp-4 text-sm leading-6 text-slate-600">{{$ad->description ?: 'Descubre esta propuesta seleccionada para completar tu viaje por Loreto.'}}</p><a href="{{route('discover.ad',$ad)}}" target="_blank" rel="sponsored noopener" data-promo-visit class="mt-6 flex min-h-12 w-full items-center justify-center rounded-xl bg-amber-400 px-5 py-3 text-sm font-black text-emerald-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-300 focus:outline-none focus:ring-4 focus:ring-amber-200">Conocer promoción →</a><button type="button" data-promo-close class="mt-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800">Ahora no</button></div>
    </article>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const popup=document.getElementById('promotional-popup'); if(!popup)return;
 const storageKey='navegaya:promotion:closed',day=86400000;
 const getClosed=()=>{try{return Number(localStorage.getItem(storageKey)||0)}catch{return 0}},remember=()=>{try{localStorage.setItem(storageKey,String(Date.now()+day))}catch{}};
 if(getClosed()>Date.now())return;
 let shown=false,timer;
 const anotherModalOpen=()=>document.querySelector('dialog[open],#consulta-pasaje[aria-hidden="false"],.ny-modal-open');
 const show=()=>{if(shown||document.hidden||anotherModalOpen())return;shown=true;popup.classList.remove('hidden');popup.classList.add('flex');popup.setAttribute('aria-hidden','false');document.body.classList.add('overflow-hidden');document.getElementById('advertise-floating-cta')?.style.setProperty('display','none');popup.querySelector('[data-promo-close]')?.focus();fetch(popup.dataset.impressionUrl,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'','Accept':'application/json'}}).catch(()=>{});window.removeEventListener('scroll',onScroll)};
 const close=()=>{remember();popup.classList.add('hidden');popup.classList.remove('flex');popup.setAttribute('aria-hidden','true');document.body.classList.remove('overflow-hidden');document.getElementById('advertise-floating-cta')?.style.removeProperty('display');clearTimeout(timer)};
 const onScroll=()=>{const total=document.documentElement.scrollHeight-innerHeight;if(total>0&&scrollY/total>=.35)show()};
 popup.querySelector('[data-promo-visit]')?.addEventListener('click',remember);popup.querySelectorAll('[data-promo-close]').forEach(button=>button.addEventListener('click',close));popup.addEventListener('click',event=>{if(event.target===popup)close()});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&popup.getAttribute('aria-hidden')==='false')close()});window.addEventListener('scroll',onScroll,{passive:true});timer=setTimeout(show,10000);
});
</script>
@endif
