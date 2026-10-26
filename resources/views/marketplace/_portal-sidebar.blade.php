<aside class="border-r border-white/10 bg-[#052f25] p-5 text-white">
    <div class="rounded-2xl border border-white/10 bg-white/5 p-4"><p class="text-[9px] font-black uppercase tracking-[.2em] text-emerald-300">Empresa conectada</p><h2 class="mt-2 text-lg font-black">{{$organization->commercial_name?:$organization->legal_name}}</h2><p class="mt-1 text-xs text-emerald-100/60">RUC {{$organization->ruc?:'por registrar'}}</p><span class="mt-3 inline-flex rounded-full bg-amber-300 px-3 py-1 text-[9px] font-black uppercase text-emerald-950">{{$organization->status}}</span></div>
    <nav class="mt-6 space-y-1">
        @foreach([['overview','▦','Resumen'],['profile','▣','Página pública'],['gallery','▧','Galería'],['offers','▤','Publicaciones y precios'],['reviews','★','Reseñas'],['campaigns','◉','Publicidad'],['plan','◆','Plan y facturación']] as [$tab,$icon,$label])
            <a href="{{route('marketplace.dashboard',['tab'=>$tab])}}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold text-emerald-50/70 hover:bg-white/10 hover:text-white"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10">{{$icon}}</span>{{$label}}</a>
        @endforeach
        <a href="{{route('messages.index')}}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold {{($activePortalSection??'')==='messages'?'bg-emerald-600 text-white shadow-lg':'text-emerald-50/70 hover:bg-white/10 hover:text-white'}}"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10">✉</span>Mensajes comerciales</a>
        <a href="{{route('support.index')}}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-bold {{($activePortalSection??'')==='support'?'bg-emerald-600 text-white shadow-lg':'text-emerald-50/70 hover:bg-white/10 hover:text-white'}}"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10">🛟</span>Soporte e incidencias</a>
    </nav>
</aside>
