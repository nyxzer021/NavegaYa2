<x-public-layout current="companies" :title="($organization->commercial_name ?: $organization->legal_name).' · Horarios y pasajes oficiales'">
<style>
    .operator-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}
    .operator-layout{display:grid;grid-template-columns:minmax(0,1fr);align-items:start;gap:2rem}
    .operator-primary{min-width:0}
    .operator-sidebar{min-width:0}
    @media (min-width:640px){.operator-metrics{grid-template-columns:repeat(4,minmax(0,1fr))}}
    @media (min-width:1024px){
        .operator-layout{grid-template-columns:minmax(0,2fr) minmax(300px,1fr)}
        .operator-sidebar{position:sticky;top:7rem}
    }
</style>
@php
    $name = $organization->commercial_name ?: $organization->legal_name;
    $ports = $organization->transportRoutes->flatMap(fn ($route) => collect([$route->originPort, $route->destinationPort]))->filter()->unique('id')->values();
    $riverDepartures = $organization->transportRoutes->flatMap(fn ($route) => $route->departures->map(fn ($departure) => [
        'mode' => 'river', 'departure' => $departure, 'origin' => $route->originPort->city,
        'destination' => $route->destinationPort->city, 'terminal' => $route->originPort->name,
        'vehicle' => $departure->vessel, 'duration' => $route->estimated_duration_minutes,
    ]));
    $airDepartures = $organization->airRoutes->flatMap(fn ($route) => $route->departures->map(fn ($departure) => [
        'mode' => 'air', 'departure' => $departure, 'origin' => $route->origin_city,
        'destination' => $route->destination_city, 'terminal' => 'Aeropuerto de '.$route->origin_city,
        'vehicle' => $departure->aircraft, 'duration' => $route->estimated_duration_minutes,
    ]));
    $departures = $riverDepartures->concat($airDepartures)
        ->filter(fn ($item) => $item['departure']->departure_at?->isFuture() && in_array($item['departure']->status, ['scheduled', 'boarding']))
        ->sortBy(fn ($item) => $item['departure']->departure_at)->values();
    $hasRiver = $organization->transportRoutes->isNotEmpty() || $organization->vessels->isNotEmpty();
    $hasAir = $organization->airRoutes->isNotEmpty() || $organization->aircraft->isNotEmpty();
    $operatorMode = $hasRiver && $hasAir ? 'Operador fluvial y aéreo' : ($hasAir ? 'Operador aéreo regional' : 'Operador fluvial');
    $fleet = $organization->vessels->map(fn ($unit) => ['mode' => 'river', 'unit' => $unit])
        ->concat($organization->aircraft->map(fn ($unit) => ['mode' => 'air', 'unit' => $unit]))->values();
    $fallbackImages = ['images/navegaya-hero-rio.png', 'images/navegaya-hero-amazonas.png', 'images/navegaya-hero-atardecer.png'];
    $dock = $ports->first();
    $supportNumber = preg_replace('/\D/', '', $organization->whatsapp ?: $organization->phone ?: '');
@endphp

<div class="min-h-screen bg-slate-50 pb-20">
<main class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8" x-data="{
    tab:'salidas',
    cargoType:'caja',
    cargoWeight:5,
    cargoPrice(){if(this.cargoType==='sobre')return 15;if(this.cargoType==='caja')return 20+Math.max(0,this.cargoWeight-5)*2;return 35+Math.max(0,this.cargoWeight-15)*1.5},
    cargoMessage(){return encodeURIComponent('Hola, deseo confirmar una cotización de '+this.cargoType+' de aproximadamente '+this.cargoWeight+' kg con este operador.')}
}">
    <div class="mb-4 flex items-center justify-between text-xs"><a href="{{route('companies.index')}}" class="font-bold text-emerald-800 transition hover:text-emerald-950">← Volver a empresas autorizadas</a><span class="hidden font-medium text-slate-400 sm:block">Registro oficial NavegaYA · Loreto</span></div>

    <section class="mb-8 overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-xl">
        <div class="relative flex min-h-[240px] items-center bg-[#062c21] px-6 py-10 text-white sm:px-10">
            <img src="{{$organization->cover_image_path ?: asset('images/navegaya-hero-rio.png')}}" alt="Operaciones de {{$name}}" class="absolute inset-0 h-full w-full object-cover opacity-25">
            <div class="absolute inset-0 bg-gradient-to-r from-[#062c21] via-emerald-950/95 to-slate-900/80"></div>
            <div class="relative z-10 flex w-full flex-col justify-between gap-6 md:flex-row md:items-center">
                <div class="flex items-center gap-4">
                    <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl border-2 border-emerald-400/40 bg-white p-2 text-2xl font-black text-emerald-950 shadow-lg sm:h-20 sm:w-20">@if($organization->logo_path)<img src="{{$organization->logo_path}}" alt="Logo de {{$name}}" class="h-full w-full object-contain">@else{{$hasAir&&!$hasRiver?'✈️':'🚤'}}@endif</div>
                    <div><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl md:text-4xl">{{$name}}</h1><span class="rounded-full border border-emerald-400/30 bg-emerald-500/20 px-2.5 py-1 text-[10px] font-bold text-emerald-300">✓ {{$hasAir&&!$hasRiver?'Operador aéreo verificado':'Operador verificado DICAPI'}}</span>@if($reviews->count())<span class="rounded-full border border-amber-300/30 bg-amber-400/15 px-2.5 py-1 text-[10px] font-bold text-amber-300">★ {{number_format($average,1)}} / 5 · {{$reviews->count()}} opinión(es)</span>@endif</div><p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-emerald-100/80"><span>📍 Base: <strong>{{$dock?->name ?: ($organization->address ?: 'Loreto, Perú')}}</strong></span><span>RUC: <strong>{{$organization->ruc ?: 'Registro validado'}}</strong></span><span>{{$operatorMode}}</span></p></div>
                </div>
                <div class="flex flex-wrap gap-2.5">@if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{$supportNumber}}?text={{urlencode('Hola, solicito información sobre las salidas de '.$name)}}" class="flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700">💬 WhatsApp boletería</a>@endif @if($organization->phone)<a href="tel:{{preg_replace('/[^\d+]/','',$organization->phone)}}" class="flex h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-xs font-bold text-white transition hover:bg-white/20">📞 Central</a>@endif</div>
            </div>
        </div>
        <div class="operator-metrics divide-slate-100 bg-slate-50 text-center text-xs sm:divide-x"><div class="p-4"><span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700"><i class="h-2 w-2 rounded-full bg-emerald-500"></i>Navegación fluvial</span><strong class="mt-1 block text-sm text-slate-900">Nivel normal · Confirmar zarpe</strong></div><div class="p-4"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Flota autorizada</span><strong class="mt-1 block text-sm text-slate-900">Certificación fluvial</strong></div><div class="p-4"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Seguridad</span><strong class="mt-1 block text-sm text-emerald-700">Manifiesto DICAPI</strong></div><div class="p-4"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Abordaje</span><strong class="mt-1 block text-sm text-slate-900">DNI físico o código</strong></div></div>
    </section>

    <div class="operator-layout">
        <div class="operator-primary space-y-6">
            <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 pb-2 text-xs">
                <button type="button" @click="tab='salidas'" :class="tab==='salidas'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">🚤 Salidas y boletos</button>
                <button type="button" @click="tab='carga'" :class="tab==='carga'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">📦 Encomiendas y carga</button>
                <button type="button" @click="tab='flota'" :class="tab==='flota'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">📸 Galería y flota</button>
                <button type="button" @click="tab='opiniones'" :class="tab==='opiniones'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">⭐ Opiniones{{$reviews->count() ? ' ('.$reviews->count().')' : ''}}</button>
                <button type="button" @click="tab='politicas'" :class="tab==='politicas'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">📋 Políticas de viaje</button>
            </nav>

            <section x-show="tab==='salidas'" x-cloak class="space-y-4">
                <div><h2 class="text-lg font-extrabold text-slate-900">Salidas programadas</h2><p class="mt-1 text-xs text-slate-500">Selecciona una salida para abrir el plano de asientos y reservar.</p></div>
                @forelse($departures as $item)
                    <x-departure-card :departure="$item['departure']" />
                @empty
                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-7"><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Programación coordinada</span><h3 class="mt-1 text-base font-extrabold text-emerald-950">Consulta la próxima salida con la boletería oficial</h3><p class="mt-2 text-xs leading-relaxed text-emerald-900/75">El operador actualiza sus zarpes según autorización portuaria y condiciones del río. Usa el contacto oficial para recibir el horario vigente.</p>@if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{$supportNumber}}" class="mt-4 inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white">Consultar próxima salida</a>@endif</div>
                @endforelse
            </section>

            <section x-show="tab==='carga'" x-cloak class="space-y-6 rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8">
                <div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Carga y encomiendas</span><h2 class="mt-1 text-xl font-extrabold text-slate-900">Cotiza una carga referencial</h2><p class="mt-1 text-xs leading-relaxed text-slate-500">Calcula una referencia y confirma precio, destino, recepción y restricciones directamente con el operador.</p></div>
                <div class="grid gap-3 sm:grid-cols-3"><button type="button" @click="cargoType='sobre';cargoWeight=1" :class="cargoType==='sobre'?'border-emerald-600 bg-emerald-50':'border-slate-200 hover:bg-slate-50'" class="rounded-2xl border p-4 text-left transition"><span class="block text-2xl">✉️</span><strong class="mt-2 block text-xs text-slate-900">Sobre documentario</strong><span class="text-[11px] text-slate-500">Documentos y trámites</span></button><button type="button" @click="cargoType='caja';cargoWeight=5" :class="cargoType==='caja'?'border-emerald-600 bg-emerald-50':'border-slate-200 hover:bg-slate-50'" class="rounded-2xl border p-4 text-left transition"><span class="block text-2xl">📦</span><strong class="mt-2 block text-xs text-slate-900">Caja o paquete</strong><span class="text-[11px] text-slate-500">Víveres, repuestos o ropa</span></button><button type="button" @click="cargoType='saco';cargoWeight=20" :class="cargoType==='saco'?'border-emerald-600 bg-emerald-50':'border-slate-200 hover:bg-slate-50'" class="rounded-2xl border p-4 text-left transition"><span class="block text-2xl">🌾</span><strong class="mt-2 block text-xs text-slate-900">Bulto o saco</strong><span class="text-[11px] text-slate-500">Productos y mercadería</span></button></div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4"><div class="flex justify-between text-xs"><strong class="text-slate-700">Peso aproximado</strong><strong class="text-sm text-emerald-800" x-text="cargoWeight+' kg'"></strong></div><input type="range" min="1" max="50" x-model.number="cargoWeight" class="mt-3 w-full cursor-pointer accent-emerald-700"></div>
                <div class="flex flex-col items-center justify-between gap-4 rounded-2xl p-5 text-white sm:flex-row" style="background:#062c21;color:#fff"><div><span class="text-[10px] font-bold uppercase" style="color:#6ee7b7">Estimación inicial, sujeta a confirmación</span><div class="text-2xl font-black text-amber-400">S/ <span x-text="cargoPrice().toFixed(2)"></span></div><span class="text-[10px]" style="color:#d1fae5">El operador confirma tarifa final, ruta y recepción.</span></div>@if($supportNumber)<a :href="'https://wa.me/{{$supportNumber}}?text='+cargoMessage()" target="_blank" class="rounded-xl bg-amber-500 px-5 py-3 text-xs font-black text-slate-950 transition hover:bg-amber-600">💬 Confirmar cotización</a>@endif</div>
            </section>

            <section x-show="tab==='flota'" x-cloak class="grid gap-4 sm:grid-cols-2">
                @forelse($fleet as $index => $fleetItem)
                    @php $unit=$fleetItem['unit']; $unitIsAir=$fleetItem['mode']==='air'; @endphp
                    <article class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm"><img src="{{$unit->cover_image_path ?: asset($fallbackImages[$index % count($fallbackImages)])}}" alt="{{$unit->name}}" class="mb-4 h-40 w-full rounded-2xl object-cover"><div class="flex items-center justify-between gap-3"><h3 class="text-sm font-extrabold text-slate-900">{{$unit->name}}</h3><span class="rounded-full {{$unitIsAir?'bg-sky-100 text-sky-800':'bg-emerald-100 text-emerald-800'}} px-2 py-0.5 text-[10px] font-bold">{{$unitIsAir?'✈ Aeronave habilitada':'🚤 Embarcación habilitada'}}</span></div><p class="mt-2 text-xs leading-relaxed text-slate-500">{{$unit->description ?: 'Unidad registrada con equipamiento reglamentario para transporte de pasajeros.'}}</p><div class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-600"><span>Capacidad: <strong>{{$unit->seat_capacity}} pasajeros</strong></span><span>Matrícula: <strong>{{$unit->registration_number ?: 'Validada'}}</strong></span></div></article>
                @empty
                    @foreach($fallbackImages as $index => $image)<article class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm"><img src="{{asset($image)}}" alt="Operación fluvial certificada" class="mb-4 h-40 w-full rounded-2xl object-cover"><h3 class="text-sm font-extrabold text-slate-900">{{['Flota con certificación fluvial','Seguridad para pasajeros','Experiencia en ríos amazónicos'][$index]}}</h3><p class="mt-2 text-xs text-slate-500">Operación sujeta a inspección, manifiesto de pasajeros y autorización de zarpe.</p></article>@endforeach
                @endforelse
            </section>

            <section x-show="tab==='opiniones'" x-cloak class="space-y-4">
                @if($reviews->count())<div class="flex items-center gap-5 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm"><div class="border-r border-slate-100 pr-6 text-center"><strong class="text-4xl text-slate-900">{{number_format($average,1)}}</strong><div class="text-sm text-amber-400">★★★★★</div><span class="text-[10px] text-slate-400">{{$reviews->count()}} viajero(s) verificado(s)</span></div><p class="text-xs leading-relaxed text-slate-500">Opiniones publicadas por viajeros con una reserva confirmada y un viaje completado con este operador.</p></div><div class="grid gap-3">@foreach($reviews as $review)<article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2.5"><span class="grid h-8 w-8 place-items-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{mb_strtoupper(mb_substr($review->user->name,0,1))}}</span><strong class="text-xs text-slate-900">{{$review->user->name}}</strong></div><span class="text-xs text-amber-400">{{str_repeat('★',$review->rating)}}</span></div><p class="mt-3 text-xs leading-relaxed text-slate-600">{{$review->comment}}</p></article>@endforeach</div>@else<div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-7"><h2 class="text-base font-extrabold text-emerald-950">Opiniones vinculadas a viajes reales</h2><p class="mt-2 text-xs text-emerald-900/75">Esta sección publica únicamente experiencias de pasajeros que completaron una reserva confirmada.</p></div>@endif
            </section>

            <section x-show="tab==='politicas'" x-cloak class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm"><h2 class="text-sm font-extrabold text-slate-900">Condiciones de transporte</h2><ul class="mt-4 space-y-3 text-xs leading-relaxed text-slate-600"><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Presentación:</strong> llega con anticipación al muelle y lleva el documento físico del pasajero.</span></li><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Equipaje:</strong> una maleta de hasta 15 kg y un bolso de mano; confirma cargas especiales con la empresa.</span></li><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Condiciones fluviales:</strong> la autorización de zarpe depende de la Capitanía y de las condiciones del río.</span></li><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Abordaje:</strong> el boleto digital y el DNI físico permiten validar al pasajero sin imprimir.</span></li></ul></section>
        </div>

        <aside class="operator-sidebar space-y-5">
            <section class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm"><div class="flex items-center gap-2 border-b border-slate-100 pb-3"><span class="text-lg">🛡️</span><div><h2 class="text-xs font-black uppercase tracking-wider text-slate-900">Ficha de operador autorizado</h2><span class="text-[10px] text-slate-400">Datos verificados en NavegaYA</span></div></div><dl class="mt-4 space-y-4 text-xs"><div><dt class="text-[10px] font-bold uppercase text-slate-400">Razón social</dt><dd class="mt-1 font-extrabold text-slate-800">{{$organization->legal_name}}</dd></div><div><dt class="text-[10px] font-bold uppercase text-slate-400">RUC comercial</dt><dd class="mt-1 font-extrabold text-slate-800">{{$organization->ruc ?: 'Registro empresarial validado'}}</dd></div><div><dt class="text-[10px] font-bold uppercase text-slate-400">Licencia operativa</dt><dd class="mt-1 font-extrabold text-emerald-800">{{$hasAir&&!$hasRiver?'MTC / DGAC':'MTC / Capitanía de Puerto'}}</dd></div><div><dt class="text-[10px] font-bold uppercase text-slate-400">{{$hasAir&&!$hasRiver?'Terminal de salida':'Muelle de zarpe'}}</dt><dd class="mt-1 font-extrabold text-slate-800">{{$dock?->name ?: ($organization->address ?: 'Loreto, Perú')}}</dd></div><div><dt class="text-[10px] font-bold uppercase text-slate-400">Equipaje referencial</dt><dd class="mt-1 font-extrabold text-slate-800">Sujeto a la unidad y tarifa seleccionada</dd></div><div><dt class="text-[10px] font-bold uppercase text-slate-400">Control de pasajeros</dt><dd class="mt-1 font-extrabold text-emerald-800">{{$hasAir&&!$hasRiver?'Lista de pasajeros y control aeroportuario':'Manifiesto digital oficial'}}</dd></div></dl></section>

            <section class="space-y-3 rounded-3xl border border-slate-200/90 bg-white p-5 text-xs shadow-sm"><div class="flex items-center gap-2 border-b border-slate-100 pb-3"><span class="text-base">🛺</span><h2 class="font-extrabold text-slate-900">Cómo llegar al muelle</h2></div><p class="text-[11px] leading-relaxed text-slate-600">Solicita al conductor llegar a <strong>{{$dock?->name ?: ($organization->address ?: 'el muelle indicado por la empresa')}}</strong>@if($dock?->city), {{$dock->city}}@endif. Confirma el acceso exacto con boletería antes de salir.</p><div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-3 text-[11px]"><span class="text-slate-500">Mototaxi desde zona céntrica</span><strong class="text-emerald-800">S/ 7–12 referencial</strong></div><a target="_blank" rel="noopener" href="https://maps.google.com/?q={{urlencode(($dock?->name ?: $organization->address ?: 'Puerto de Iquitos').', '.($dock?->city ?: 'Loreto, Perú'))}}" class="block w-full rounded-xl bg-slate-100 py-2.5 text-center text-[11px] font-bold text-slate-800 transition hover:bg-slate-200">📍 Abrir ubicación en Google Maps</a><p class="text-[9px] leading-relaxed text-slate-400">La tarifa depende de distancia, horario y negociación con el conductor.</p></section>

            <section class="rounded-3xl border border-emerald-900 p-6 text-white shadow-md" style="background:linear-gradient(135deg,#062c21 0%,#064e3b 100%);color:#fff"><span class="rounded-full border border-emerald-500/30 bg-emerald-500/20 px-2.5 py-1 text-[10px] font-bold" style="color:#a7f3d0">⚓ Asistencia en muelle</span><h2 class="mt-4 text-base font-extrabold leading-tight" style="color:#fff">¿Necesitas ayuda con tu salida o equipaje?</h2><p class="mt-2 text-xs leading-relaxed" style="color:#d1fae5">Comunícate con la mesa oficial del operador para confirmar embarque, carga y ubicación del muelle.</p><div class="mt-4 flex items-center justify-between gap-3 border-t border-emerald-800/80 pt-4"><div><span class="block text-[9px] font-bold uppercase tracking-wider" style="color:#6ee7b7">Central de atención</span><strong class="text-sm" style="color:#fff">{{$organization->phone ?: $organization->whatsapp ?: 'Atención por canal oficial'}}</strong></div>@if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{$supportNumber}}" class="rounded-xl bg-amber-500 px-3.5 py-2.5 text-xs font-black text-slate-950 transition hover:bg-amber-600">Contactar</a>@endif</div></section>
        </aside>
    </div>
    <x-b2b-ad-banner placement="company_footer" :city="$organization->base_city" class="mt-8" />
</main>
</div>
</x-public-layout>
