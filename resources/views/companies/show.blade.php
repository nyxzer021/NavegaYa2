<x-public-layout current="companies" :title="($organization->commercial_name ?: $organization->legal_name).' · Horarios y pasajes oficiales'">
<style>
    .operator-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}
    .operator-layout{display:grid;grid-template-columns:minmax(0,1fr);align-items:start;gap:2rem}
    .operator-primary{min-width:0}
    .operator-sidebar{min-width:0}
    .operator-tab{display:inline-flex;align-items:center}
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
    $profilePublished = ($organization->profile_content_status ?? 'published') === 'published';
    $gallery = collect();
    if ($profilePublished && $organization->cover_image_path) $gallery->push(['src' => $organization->cover_image_path, 'alt' => 'Portada de '.$name]);
    if ($profilePublished && $organization->logo_path) $gallery->push(['src' => $organization->logo_path, 'alt' => 'Logo de '.$name]);
    foreach ($fleet as $fleetItem) {
        if ($fleetItem['unit']->cover_image_path) $gallery->push(['src' => $fleetItem['unit']->cover_image_path, 'alt' => $fleetItem['unit']->name]);
    }
    $gallery = $gallery->unique('src')->values();
    $dock = $ports->first();
    $supportNumber = preg_replace('/\D/', '', $organization->whatsapp ?: $organization->phone ?: '');
@endphp

<div class="min-h-screen bg-slate-50 pb-20">
<main class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8" x-data="{
    tab:['salidas','carga','flota','opiniones','politicas'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'salidas',
    cargoType:'caja',
    cargoWeight:5,
    cargoPrice(){if(this.cargoType==='sobre')return 15;if(this.cargoType==='caja')return 20+Math.max(0,this.cargoWeight-5)*2;return 35+Math.max(0,this.cargoWeight-15)*1.5},
    cargoMessage(){return encodeURIComponent('Hola, deseo confirmar una cotización de '+this.cargoType+' de aproximadamente '+this.cargoWeight+' kg con este operador.')}
}">
    <div class="mb-4 flex items-center justify-between text-xs"><a href="{{route('companies.index')}}" class="font-bold text-emerald-800 transition hover:text-emerald-950">← Volver a empresas autorizadas</a><span class="hidden font-medium text-slate-400 sm:block">Registro oficial NavegaYA · Loreto</span></div>
    @if(session('success'))<div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900">✓ {{session('success')}}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800"><strong>No pudimos enviar la opinión:</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif

    <section class="mb-8 overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-xl">
        <div class="relative flex min-h-[240px] items-center bg-[#062c21] px-6 py-10 text-white sm:px-10">
            <img src="{{($organization->profile_content_status ?? 'published') === 'published' && $organization->cover_image_path ? $organization->cover_image_path : asset('images/navegaya-hero-rio.png')}}" alt="Operaciones de {{$name}}" class="absolute inset-0 h-full w-full object-cover opacity-25">
            <div class="absolute inset-0 bg-gradient-to-r from-[#062c21] via-emerald-950/95 to-slate-900/80"></div>
            <div class="relative z-10 flex w-full flex-col justify-between gap-6 md:flex-row md:items-center">
                <div class="flex items-center gap-4">
                    <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl border-2 border-emerald-400/40 bg-white p-2 text-2xl font-black text-emerald-950 shadow-lg sm:h-20 sm:w-20">@if(($organization->profile_content_status ?? 'published') === 'published' && $organization->logo_path)<img src="{{$organization->logo_path}}" alt="Logo de {{$name}}" class="h-full w-full object-contain">@else{{$hasAir&&!$hasRiver?'✈️':'🚤'}}@endif</div>
                    <div><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl md:text-4xl">{{$name}}</h1><span class="rounded-full border border-emerald-400/30 bg-emerald-500/20 px-2.5 py-1 text-[10px] font-bold text-emerald-300">✓ {{$hasAir&&!$hasRiver?'Operador aéreo verificado':'Operador verificado DICAPI'}}</span>@if($reviews->count())<span class="rounded-full border border-amber-300/30 bg-amber-400/15 px-2.5 py-1 text-[10px] font-bold text-amber-300">★ {{number_format($average,1)}} / 5 · {{$reviews->count()}} opinión(es)</span>@endif</div><p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-emerald-100/80"><span>📍 Base: <strong>{{$dock?->name ?: ($organization->address ?: 'Loreto, Perú')}}</strong></span><span>RUC: <strong>{{$organization->ruc ?: 'Registro validado'}}</strong></span><span>{{$operatorMode}}</span></p></div>
                </div>
                <div class="flex flex-wrap gap-2.5">@auth<details class="relative"><summary class="flex h-11 cursor-pointer list-none items-center rounded-xl border border-white/20 bg-white/10 px-3 text-[10px] font-bold text-white">⚑ Reportar perfil</summary><form method="POST" action="{{route('companies.profile.report',$organization)}}" class="absolute right-0 z-30 mt-2 w-64 space-y-2 rounded-2xl bg-white p-4 text-slate-700 shadow-2xl">@csrf<select name="reason" required class="w-full rounded-lg border-slate-200 text-xs"><option value="inappropriate">Imagen inapropiada</option><option value="false_information">Información falsa</option><option value="copyright">Derechos de autor</option><option value="other">Otro motivo</option></select><textarea name="details" rows="2" placeholder="Describe el problema" class="w-full rounded-lg border-slate-200 text-xs"></textarea><button class="w-full rounded-lg bg-rose-600 py-2 text-xs font-black text-white">Enviar reporte</button></form></details>@endauth @if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{$supportNumber}}?text={{urlencode('Hola, solicito información sobre las salidas de '.$name)}}" class="flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700">💬 WhatsApp boletería</a>@endif @if($organization->phone)<a href="tel:{{preg_replace('/[^\d+]/','',$organization->phone)}}" class="flex h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-xs font-bold text-white transition hover:bg-white/20">📞 Central</a>@endif</div>
            </div>
        </div>
        <div class="operator-metrics divide-slate-100 bg-slate-50 text-center text-xs sm:divide-x"><div class="p-4"><span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700"><i class="h-2 w-2 rounded-full bg-emerald-500"></i>Modalidad habilitada</span><strong class="mt-1 block text-sm text-slate-900">{{$operatorMode}}</strong></div><div class="p-4"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Flota publicada</span><strong class="mt-1 block text-sm text-slate-900">{{$fleet->count()}} unidad(es)</strong></div><div class="p-4"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Rutas registradas</span><strong class="mt-1 block text-sm text-emerald-700">{{$organization->transportRoutes->count() + $organization->airRoutes->count()}} ruta(s)</strong></div><div class="p-4"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Abordaje</span><strong class="mt-1 block text-sm text-slate-900">DNI físico o código QR</strong></div></div>
    </section>

    <div class="operator-layout">
        <div class="operator-primary space-y-6">
            <section class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="company-information-title">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Información de la empresa</span>
                <h2 id="company-information-title" class="mt-1 text-xl font-extrabold text-slate-900">Conoce a {{$name}}</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-600">{{$organization->public_description ?: 'Empresa de transporte autorizada en NavegaYA. Consulta aquí sus salidas, unidades registradas y canales oficiales de atención.'}}</p>
                <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 text-xs sm:grid-cols-2">
                    <div><dt class="font-bold uppercase tracking-wider text-slate-400">Dirección</dt><dd class="mt-1 font-semibold text-slate-800">{{$organization->address ?: ($dock?->name ?: 'Loreto, Perú')}}</dd></div>
                    <div><dt class="font-bold uppercase tracking-wider text-slate-400">Atención</dt><dd class="mt-1 font-semibold text-slate-800">{{$organization->phone ?: $organization->whatsapp ?: 'Consultar por canal oficial'}}</dd></div>
                    @if($organization->website)<div class="sm:col-span-2"><dt class="font-bold uppercase tracking-wider text-slate-400">Sitio web</dt><dd class="mt-1"><a href="{{$organization->website}}" target="_blank" rel="noopener" class="font-bold text-emerald-700 hover:underline">Visitar sitio oficial →</a></dd></div>@endif
                </dl>
            </section>
            <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 pb-2 text-xs">
                <a href="#salidas" @click="tab='salidas'" :class="tab==='salidas'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="operator-tab whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">🚤 Salidas y boletos</a>
                <a href="#carga" @click="tab='carga'" :class="tab==='carga'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="operator-tab whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">📦 Encomiendas y carga</a>
                <a href="#flota" @click="tab='flota'" :class="tab==='flota'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="operator-tab whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">📸 Galería y flota</a>
                <a href="#opiniones" @click="tab='opiniones'" :class="tab==='opiniones'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="operator-tab whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">⭐ Opiniones{{$reviews->count() ? ' ('.$reviews->count().')' : ''}}</a>
                <a href="#politicas" @click="tab='politicas'" :class="tab==='politicas'?'bg-[#062c21] text-white':'bg-white text-slate-600 hover:bg-slate-100'" class="operator-tab whitespace-nowrap rounded-xl px-4 py-2 font-bold shadow-sm transition">📋 Políticas de viaje</a>
            </nav>

            <section id="salidas" x-show="tab==='salidas'" class="space-y-4">
                <div><h2 class="text-lg font-extrabold text-slate-900">Salidas programadas</h2><p class="mt-1 text-xs text-slate-500">Selecciona una salida para abrir el plano de asientos y reservar.</p></div>
                @forelse($departures as $item)
                    <x-departure-card :departure="$item['departure']" />
                @empty
                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-7"><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Programación coordinada</span><h3 class="mt-1 text-base font-extrabold text-emerald-950">Consulta la próxima salida con la boletería oficial</h3><p class="mt-2 text-xs leading-relaxed text-emerald-900/75">El operador actualiza sus zarpes según autorización portuaria y condiciones del río. Usa el contacto oficial para recibir el horario vigente.</p>@if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{$supportNumber}}" class="mt-4 inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white">Consultar próxima salida</a>@endif</div>
                @endforelse
            </section>

            <section id="carga" x-show="tab==='carga'" class="space-y-6 rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8">
                <div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Carga y encomiendas</span><h2 class="mt-1 text-xl font-extrabold text-slate-900">Cotiza una carga referencial</h2><p class="mt-1 text-xs leading-relaxed text-slate-500">Calcula una referencia y confirma precio, destino, recepción y restricciones directamente con el operador.</p></div>
                <div class="grid gap-3 sm:grid-cols-3"><button type="button" @click="cargoType='sobre';cargoWeight=1" :class="cargoType==='sobre'?'border-emerald-600 bg-emerald-50':'border-slate-200 hover:bg-slate-50'" class="rounded-2xl border p-4 text-left transition"><span class="block text-2xl">✉️</span><strong class="mt-2 block text-xs text-slate-900">Sobre documentario</strong><span class="text-[11px] text-slate-500">Documentos y trámites</span></button><button type="button" @click="cargoType='caja';cargoWeight=5" :class="cargoType==='caja'?'border-emerald-600 bg-emerald-50':'border-slate-200 hover:bg-slate-50'" class="rounded-2xl border p-4 text-left transition"><span class="block text-2xl">📦</span><strong class="mt-2 block text-xs text-slate-900">Caja o paquete</strong><span class="text-[11px] text-slate-500">Víveres, repuestos o ropa</span></button><button type="button" @click="cargoType='saco';cargoWeight=20" :class="cargoType==='saco'?'border-emerald-600 bg-emerald-50':'border-slate-200 hover:bg-slate-50'" class="rounded-2xl border p-4 text-left transition"><span class="block text-2xl">🌾</span><strong class="mt-2 block text-xs text-slate-900">Bulto o saco</strong><span class="text-[11px] text-slate-500">Productos y mercadería</span></button></div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4"><div class="flex justify-between text-xs"><strong class="text-slate-700">Peso aproximado</strong><strong class="text-sm text-emerald-800" x-text="cargoWeight+' kg'"></strong></div><input type="range" min="1" max="50" x-model.number="cargoWeight" class="mt-3 w-full cursor-pointer accent-emerald-700"></div>
                <div class="flex flex-col items-center justify-between gap-4 rounded-2xl p-5 text-white sm:flex-row" style="background:#062c21;color:#fff"><div><span class="text-[10px] font-bold uppercase" style="color:#6ee7b7">Estimación inicial, sujeta a confirmación</span><div class="text-2xl font-black text-amber-400">S/ <span x-text="cargoPrice().toFixed(2)"></span></div><span class="text-[10px]" style="color:#d1fae5">El operador confirma tarifa final, ruta y recepción.</span></div>@if($supportNumber)<a :href="'https://wa.me/{{$supportNumber}}?text='+cargoMessage()" target="_blank" class="rounded-xl bg-amber-500 px-5 py-3 text-xs font-black text-slate-950 transition hover:bg-amber-600">💬 Confirmar cotización</a>@endif</div>
            </section>

            <section id="flota" x-show="tab==='flota'" class="space-y-5">
                <div><h2 class="text-lg font-extrabold text-slate-900">Galería y flota autorizada</h2><p class="mt-1 text-xs text-slate-500">Fotografías publicadas por la empresa y unidades registradas en la plataforma.</p></div>
                @if($gallery->isNotEmpty())
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach($gallery as $image)<img src="{{$image['src']}}" alt="{{$image['alt']}}" loading="lazy" class="h-36 w-full rounded-2xl border border-slate-200 bg-white object-cover shadow-sm sm:h-44">@endforeach</div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-xs text-slate-500">La empresa todavía no publicó fotografías propias.</div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                @forelse($fleet as $index => $fleetItem)
                    @php $unit=$fleetItem['unit']; $unitIsAir=$fleetItem['mode']==='air'; @endphp
                    <article class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm"><img src="{{$unit->cover_image_path ?: asset($fallbackImages[$index % count($fallbackImages)])}}" alt="{{$unit->name}}" class="mb-4 h-40 w-full rounded-2xl object-cover"><div class="flex items-center justify-between gap-3"><h3 class="text-sm font-extrabold text-slate-900">{{$unit->name}}</h3><span class="rounded-full {{$unitIsAir?'bg-sky-100 text-sky-800':'bg-emerald-100 text-emerald-800'}} px-2 py-0.5 text-[10px] font-bold">{{$unitIsAir?'✈ Aeronave habilitada':'🚤 Embarcación habilitada'}}</span></div><p class="mt-2 text-xs leading-relaxed text-slate-500">{{$unit->description ?: 'Unidad registrada con equipamiento reglamentario para transporte de pasajeros.'}}</p><div class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-600"><span>Capacidad: <strong>{{$unit->seat_capacity}} pasajeros</strong></span><span>Matrícula: <strong>{{$unit->registration_number ?: 'Validada'}}</strong></span></div></article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-xs text-slate-500 sm:col-span-2">No hay unidades publicadas en esta ficha.</div>
                @endforelse
                </div>
            </section>

            <section id="opiniones" x-show="tab==='opiniones'" class="space-y-4">
                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 bg-gradient-to-r from-amber-50 to-white p-6">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Experiencias verificadas</span>
                        <h2 class="mt-1 text-lg font-extrabold text-slate-900">Comparte tu experiencia de viaje</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Cada opinión queda vinculada a una reserva completada y pasa por moderación antes de publicarse.</p>
                    </div>
                    <div class="p-6">
                        @guest
                            <div class="flex flex-col justify-between gap-4 rounded-2xl bg-slate-50 p-5 sm:flex-row sm:items-center"><div><strong class="text-sm text-slate-900">Inicia sesión para verificar tu viaje</strong><p class="mt-1 text-xs text-slate-500">Usa la cuenta asociada al correo de tu reserva.</p></div><a href="{{route('login')}}" class="shrink-0 rounded-xl bg-[#062c21] px-5 py-3 text-center text-xs font-black text-white">Iniciar sesión</a></div>
                        @else
                            @forelse($completedReservations as $reservation)
                                @php
                                    $trip = $reservation->isAir() ? $reservation->airDeparture : $reservation->departure;
                                    $route = $reservation->isAir() ? $trip?->airRoute : $trip?->transportRoute;
                                    $origin = $reservation->isAir() ? $route?->origin_city : $route?->originPort?->city;
                                    $destination = $reservation->isAir() ? $route?->destination_city : $route?->destinationPort?->city;
                                    $travelerReview = $reservation->companyReview;
                                    $reviewLabels = ['pending' => 'Pendiente de revisión', 'published' => 'Publicada', 'rejected' => 'No publicada'];
                                @endphp
                                <article class="mb-4 rounded-2xl border {{$travelerReview ? 'border-slate-200 bg-slate-50' : 'border-emerald-200 bg-emerald-50/40'}} p-5 last:mb-0">
                                    <div class="flex flex-wrap items-start justify-between gap-3"><div><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Reserva {{$reservation->code}}</span><h3 class="mt-1 text-sm font-extrabold text-slate-900">{{$origin}} → {{$destination}}</h3><p class="mt-1 text-xs text-slate-500">Viaje completado {{$trip?->departure_at?->translatedFormat('d M Y · H:i')}}</p></div>@if($travelerReview)<span class="rounded-full px-3 py-1 text-[10px] font-black {{$travelerReview->status === 'published' ? 'bg-emerald-100 text-emerald-800' : ($travelerReview->status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800')}}">{{$reviewLabels[$travelerReview->status] ?? strtoupper($travelerReview->status)}}</span>@endif</div>
                                    @if($travelerReview)
                                        <div class="mt-4 border-t border-slate-200 pt-4"><div class="text-sm text-amber-500">{{str_repeat('★',$travelerReview->rating)}}{{str_repeat('☆',5-$travelerReview->rating)}}</div><p class="mt-2 text-xs leading-relaxed text-slate-600">{{$travelerReview->comment}}</p>@if($travelerReview->status === 'pending')<p class="mt-3 text-[11px] font-semibold text-amber-800">NavegaYA está revisando tu opinión. Se mostrará públicamente después de aprobarla.</p>@endif</div>
                                    @else
                                        <form method="POST" action="{{route('companies.review',$organization)}}" class="mt-5 space-y-4 border-t border-emerald-100 pt-4">@csrf<input type="hidden" name="reservation_id" value="{{$reservation->id}}"><fieldset><legend class="text-xs font-bold text-slate-700">Califica este viaje</legend><div class="mt-2 flex flex-wrap gap-2">@for($rating=1;$rating<=5;$rating++)<label class="cursor-pointer"><input required type="radio" name="rating" value="{{$rating}}" class="peer sr-only"><span class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition peer-checked:border-amber-400 peer-checked:bg-amber-50 peer-checked:text-amber-800">{{$rating}} ★</span></label>@endfor</div></fieldset><label class="block text-xs font-bold text-slate-700">Cuéntanos cómo fue el servicio<textarea required minlength="10" maxlength="1000" name="comment" rows="4" placeholder="Describe la atención, puntualidad, comodidad y embarque." class="mt-2 w-full rounded-xl border-slate-200 text-sm focus:border-emerald-600 focus:ring-emerald-600">{{old('comment')}}</textarea></label><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><p class="text-[10px] leading-relaxed text-slate-400">No publiques teléfonos, documentos ni otros datos personales.</p><button class="rounded-xl bg-amber-400 px-5 py-3 text-xs font-black text-slate-950 transition hover:bg-amber-300">Enviar opinión</button></div></form>
                                    @endif
                                </article>
                            @empty
                                @if($hasUpcomingTrip)<div class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><strong class="text-sm text-amber-950">Podrás opinar después de completar el viaje</strong><p class="mt-1 text-xs text-amber-800">Tu reserva está registrada, pero la salida todavía no ha sido marcada como completada.</p></div>@else<div class="rounded-2xl bg-slate-50 p-5"><strong class="text-sm text-slate-900">Solo pueden opinar pasajeros verificados</strong><p class="mt-1 text-xs text-slate-500">No encontramos un viaje completado con esta empresa asociado a tu cuenta o correo.</p></div>@endif
                            @endforelse
                        @endguest
                    </div>
                </div>
                @if($reviews->count())<div class="flex items-center gap-5 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm"><div class="border-r border-slate-100 pr-6 text-center"><strong class="text-4xl text-slate-900">{{number_format($average,1)}}</strong><div class="text-sm text-amber-400">★★★★★</div><span class="text-[10px] text-slate-400">{{$reviews->count()}} viajero(s) verificado(s)</span></div><p class="text-xs leading-relaxed text-slate-500">Opiniones publicadas por viajeros con una reserva confirmada y un viaje completado con este operador.</p></div><div class="grid gap-3">@foreach($reviews as $review)<article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2.5"><span class="grid h-8 w-8 place-items-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{mb_strtoupper(mb_substr($review->user->name,0,1))}}</span><strong class="text-xs text-slate-900">{{$review->user->name}}</strong></div><span class="text-xs text-amber-400">{{str_repeat('★',$review->rating)}}</span></div><p class="mt-3 text-xs leading-relaxed text-slate-600">{{$review->comment}}</p>@auth<details class="mt-3"><summary class="cursor-pointer list-none text-[10px] font-bold text-slate-400 hover:text-rose-600">⚑ Reportar opinión</summary><form method="POST" action="{{route('companies.reviews.report',[$organization,$review])}}" class="mt-2 flex flex-wrap gap-2 rounded-xl bg-slate-50 p-3">@csrf<select name="reason" required class="h-9 flex-1 rounded-lg border-slate-200 text-xs"><option value="harassment">Insultos o acoso</option><option value="false_information">Información falsa</option><option value="spam">Spam</option><option value="personal_data">Datos personales</option><option value="other">Otro motivo</option></select><button class="rounded-lg bg-rose-600 px-3 text-xs font-black text-white">Reportar</button></form></details>@endauth</article>@endforeach</div>@else<div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-7"><h2 class="text-base font-extrabold text-emerald-950">Opiniones vinculadas a viajes reales</h2><p class="mt-2 text-xs text-emerald-900/75">Esta sección publica únicamente experiencias de pasajeros que completaron una reserva confirmada.</p></div>@endif
            </section>

            <section id="politicas" x-show="tab==='politicas'" class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm"><h2 class="text-sm font-extrabold text-slate-900">Condiciones de transporte</h2><ul class="mt-4 space-y-3 text-xs leading-relaxed text-slate-600"><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Presentación:</strong> llega con anticipación al muelle y lleva el documento físico del pasajero.</span></li><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Equipaje:</strong> una maleta de hasta 15 kg y un bolso de mano; confirma cargas especiales con la empresa.</span></li><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Condiciones fluviales:</strong> la autorización de zarpe depende de la Capitanía y de las condiciones del río.</span></li><li class="flex gap-2.5"><span class="font-bold text-emerald-700">✓</span><span><strong>Abordaje:</strong> el boleto digital y el DNI físico permiten validar al pasajero sin imprimir.</span></li></ul></section>
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
