<x-public-layout current="companies" :title="($organization->commercial_name ?: $organization->legal_name).' · Horarios y pasajes oficiales'">
<style>
    .company-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
    .company-tabs{display:flex;gap:.4rem;overflow-x:auto;position:sticky;top:4.25rem;z-index:40;scroll-margin-top:4.75rem;margin-top:1rem;padding:.45rem;border-radius:999px;background:#062c21}
    .company-tab{display:inline-flex;min-height:2.6rem;align-items:center;white-space:nowrap;border:0;border-radius:999px;background:transparent;padding:.4rem 1rem;font-size:.95rem;font-weight:800;color:#d1fae5;cursor:pointer}
    .company-tab.is-on{background:#fff;color:#022c22}
    .company-tab[data-open="salidas"].is-on{background:#fbbf24;color:#022c22}
    .company-panel[hidden]{display:none!important}
    @media (min-width:640px){.company-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
</style>
@php
    $name = $organization->commercial_name ?: $organization->legal_name;
    $ports = $organization->transportRoutes->flatMap(fn ($route) => collect([$route->originPort, $route->destinationPort]))->filter()->unique('id')->values();
    $riverDepartures = $organization->transportRoutes->flatMap(fn ($route) => $route->departures->map(fn ($departure) => [
        'mode' => 'river', 'departure' => $departure,
        'origin' => $route->originPort?->city ?? '—',
        'destination' => $route->destinationPort?->city ?? '—',
        'terminal' => $route->originPort?->name ?? 'Muelle',
        'vehicle' => $departure->vessel,
        'duration' => $route->estimated_duration_minutes,
    ]));
    $airDepartures = $organization->airRoutes->flatMap(fn ($route) => $route->departures->map(fn ($departure) => [
        'mode' => 'air', 'departure' => $departure, 'origin' => $route->origin_city,
        'destination' => $route->destination_city, 'terminal' => 'Aeropuerto de '.$route->origin_city,
        'vehicle' => $departure->aircraft, 'duration' => $route->estimated_duration_minutes,
    ]));
    $departures = $riverDepartures->concat($airDepartures)
        ->filter(fn ($item) => $item['vehicle'] && $item['departure']->departure_at?->isFuture() && in_array($item['departure']->status, ['scheduled', 'boarding']))
        ->sortBy(fn ($item) => $item['departure']->departure_at)->values();
    $hasRiver = $organization->transportRoutes->isNotEmpty() || $organization->vessels->isNotEmpty();
    $hasAir = $organization->airRoutes->isNotEmpty() || $organization->aircraft->isNotEmpty();
    $operatorMode = $hasRiver && $hasAir ? 'Operador fluvial y aéreo' : ($hasAir ? 'Operador aéreo regional' : 'Operador fluvial');
    $fleet = $organization->vessels->map(fn ($unit) => ['mode' => 'river', 'unit' => $unit])
        ->concat($organization->aircraft->map(fn ($unit) => ['mode' => 'air', 'unit' => $unit]))->values();
    $fallbackImages = ['images/navegaya-hero-rio.png', 'images/navegaya-hero-amazonas.png', 'images/navegaya-hero-atardecer.png'];
    $profilePublished = ($organization->profile_content_status ?? 'published') === 'published';
    $gallery = collect();
    if ($profilePublished && $organization->cover_image_path) {
        $gallery->push(['src' => $organization->cover_image_path, 'alt' => 'Portada de '.$name]);
    }
    if ($profilePublished) {
        foreach ($organization->photos as $photo) {
            $gallery->push(['src' => $photo->path, 'alt' => $photo->caption ?: 'Foto de '.$name]);
        }
    }
    if ($profilePublished && $organization->logo_path) {
        $gallery->push(['src' => $organization->logo_path, 'alt' => 'Logo de '.$name]);
    }
    foreach ($fleet as $fleetItem) {
        if ($fleetItem['unit']->cover_image_path) {
            $gallery->push(['src' => $fleetItem['unit']->cover_image_path, 'alt' => $fleetItem['unit']->name]);
        }
    }
    $gallery = $gallery->unique('src')->values();
    $dock = $ports->first();
    $supportNumber = $organization->show_public_whatsapp ? \App\Support\PhoneNumber::peru($organization->whatsapp ?: $organization->phone) : null;
    $whatsappText = $organization->whatsapp_message ?: 'Hola, deseo información de '.$name.'.';
    $publishedRouteCount = $organization->transportRoutes->count() + $organization->airRoutes->count();
    $place = $dock?->name ?: ($organization->address ?: 'Loreto, Perú');
    $ownComment = auth()->check()
        ? $reviews->first(fn ($review) => (int) $review->user_id === (int) auth()->id() && $review->reservation_id === null)
        : null;
@endphp

<div class="bg-slate-50 pb-16" id="company-ficha">
<main class="mx-auto max-w-6xl px-4 pt-4 sm:px-6">
    <a href="{{ route('companies.index') }}" class="mb-4 inline-flex text-sm font-bold text-emerald-800">← Empresas</a>
    @if(session('success'))<div class="mb-4 rounded-2xl bg-emerald-700 px-5 py-4 text-sm font-semibold text-white">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800"><strong>No pudimos enviar la opinión:</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="overflow-hidden rounded-3xl bg-white shadow-sm">
        <div class="relative h-48 bg-emerald-900 sm:h-64">
            <img src="{{ $profilePublished && $organization->cover_image_path ? $organization->cover_image_path : asset('images/navegaya-hero-rio.png') }}" alt="Operaciones de {{ $name }}" class="h-full w-full object-cover">
        </div>
        <div class="px-5 pb-5 sm:px-7">
            <div class="-mt-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="grid h-20 w-20 place-items-center overflow-hidden rounded-2xl border-4 border-white bg-amber-400 text-2xl font-black text-emerald-950 shadow-md">
                    @if($profilePublished && $organization->logo_path)
                        <img src="{{ $organization->logo_path }}" alt="Logo de {{ $name }}" class="h-full w-full bg-white object-contain">
                    @else
                        {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($supportNumber)
                        <a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode($whatsappText) }}" class="inline-flex h-11 items-center rounded-full bg-emerald-700 px-4 text-sm font-bold text-white">WhatsApp</a>
                    @endif
                    <button type="button" data-open="salidas" class="inline-flex h-11 items-center rounded-full bg-amber-400 px-4 text-sm font-black text-emerald-950">Ver salidas</button>
                    @if($organization->phone)
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $organization->phone) }}" class="inline-flex h-11 items-center rounded-full bg-emerald-900 px-4 text-sm font-bold text-white">Llamar</a>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <h1 class="text-3xl font-black tracking-tight text-emerald-950">{{ $name }}</h1>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Empresa verificada</span>
                @if($reviews->count())
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">★ {{ number_format($average, 1) }}</span>
                @endif
            </div>
            <p class="mt-2 text-sm font-semibold text-emerald-800">{{ $place }} · {{ $operatorMode }}@if($organization->service_hours) · {{ $organization->service_hours }}@endif</p>
        </div>
    </section>

    <div class="company-stats my-4">
        <article class="rounded-2xl bg-emerald-700 p-4 text-white"><span class="text-sm font-semibold text-emerald-100">Flota</span><strong class="mt-1 block text-2xl font-black">{{ $fleet->count() }}</strong></article>
        <article class="rounded-2xl bg-emerald-900 p-4 text-white"><span class="text-sm font-semibold text-emerald-100">Rutas</span><strong class="mt-1 block text-2xl font-black">{{ $publishedRouteCount }}</strong></article>
        <article class="rounded-2xl bg-amber-400 p-4 text-emerald-950"><span class="text-sm font-semibold">Fotos</span><strong class="mt-1 block text-2xl font-black">{{ $gallery->count() }}</strong></article>
        <article class="rounded-2xl bg-white p-4 text-emerald-950 shadow-sm"><span class="text-sm font-semibold text-emerald-700">Calificación</span><strong class="mt-1 block text-2xl font-black">{{ $reviews->count() ? number_format($average, 1) : '—' }}</strong></article>
    </div>

    <nav id="empresa-menu" class="company-tabs" aria-label="Secciones de la empresa">
        <button type="button" data-open="informacion" class="company-tab is-on">Información</button>
        <button type="button" data-open="galeria" class="company-tab">Galería</button>
        <button type="button" data-open="flota" class="company-tab">Flota</button>
        <button type="button" data-open="salidas" class="company-tab">Salidas</button>
        <button type="button" data-open="opiniones" class="company-tab">Testimonios{{ $reviews->count() ? ' ('.$reviews->count().')' : '' }}</button>
    </nav>

    <section data-panel="informacion" class="company-panel mt-5 space-y-4">
        <article class="rounded-3xl border-l-8 border-emerald-600 bg-white p-6 shadow-sm sm:p-8">
            <h2 id="company-information-title" class="text-xl font-black text-emerald-950">Información de la empresa</h2>
            <p class="mt-3 whitespace-pre-line text-base leading-7 text-slate-700">{{ $organization->public_description ?: 'Empresa de transporte autorizada en NavegaYA. Consulta aquí sus salidas, unidades registradas y canales oficiales de atención.' }}</p>
        </article>
        <div class="grid gap-3 sm:grid-cols-2">
            <article class="rounded-2xl bg-white p-5 shadow-sm"><span class="text-xs font-black uppercase tracking-wide text-emerald-700">Dirección</span><strong class="mt-2 block text-lg text-emerald-950">{{ $organization->address ?: $place }}</strong></article>
            <article class="rounded-2xl bg-white p-5 shadow-sm"><span class="text-xs font-black uppercase tracking-wide text-amber-700">Atención</span><strong class="mt-2 block text-lg text-emerald-950">{{ $organization->phone ?: $organization->whatsapp ?: 'Consultar por canal oficial' }}</strong>@if($organization->service_hours)<span class="mt-1 block text-sm text-slate-600">{{ $organization->service_hours }}</span>@endif</article>
            <article class="rounded-2xl bg-emerald-800 p-5 text-white"><span class="text-xs font-black uppercase tracking-wide text-emerald-200">Razón social</span><strong class="mt-2 block text-lg">{{ $organization->legal_name }}</strong><span class="mt-1 block text-sm text-emerald-100">RUC {{ $organization->ruc ?: 'validado' }}</span></article>
            <article class="rounded-2xl bg-amber-400 p-5 text-emerald-950"><span class="text-xs font-black uppercase tracking-wide">{{ $hasAir && ! $hasRiver ? 'Terminal' : 'Muelle' }}</span><strong class="mt-2 block text-lg">{{ $place }}</strong><span class="mt-1 block text-sm">{{ $hasAir && ! $hasRiver ? 'MTC / DGAC' : 'MTC / Capitanía de Puerto' }}</span></article>
        </div>
        @if($organization->website)
            <a href="{{ $organization->website }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center rounded-full bg-white px-4 text-sm font-bold text-emerald-800 shadow-sm">Visitar sitio oficial</a>
        @endif
        <div class="grid gap-3 sm:grid-cols-2">
            <article class="rounded-2xl bg-white p-5 text-sm leading-6 text-slate-700 shadow-sm"><strong class="mb-2 block text-emerald-950">Para viajar</strong>Llega con anticipación, lleva el documento físico y aborda con el boleto digital. El equipaje de mano y una maleta de hasta 15 kg van incluidos; confirma cargas especiales con la empresa.</article>
            <article class="rounded-2xl bg-emerald-900 p-5 text-sm leading-6 text-emerald-50 shadow-sm"><strong class="mb-2 block text-amber-300">Cómo llegar</strong>Pide que te dejen en {{ $place }}@if($dock?->city), {{ $dock->city }}@endif. Un mototaxi desde el centro suele costar entre S/ 7 y S/ 12.<a target="_blank" rel="noopener" href="https://maps.google.com/?q={{ urlencode($place.', '.($dock?->city ?: 'Loreto, Perú')) }}" class="mt-3 inline-flex rounded-full bg-amber-400 px-3 py-1.5 text-xs font-black text-emerald-950">Abrir mapa</a></article>
        </div>
        <section class="rounded-3xl bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-lg font-black text-emerald-950">Encomiendas</h3>
            <p class="mt-1 text-sm text-slate-600">Una referencia para cotizar. El operador confirma el precio final.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <button type="button" data-cargo="sobre" class="rounded-2xl bg-emerald-50 p-4 text-left font-bold text-emerald-900">Sobre</button>
                <button type="button" data-cargo="caja" class="is-on rounded-2xl bg-emerald-700 p-4 text-left font-bold text-white">Caja</button>
                <button type="button" data-cargo="saco" class="rounded-2xl bg-emerald-50 p-4 text-left font-bold text-emerald-900">Bulto</button>
            </div>
            <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-amber-50 p-4"><span class="text-sm font-bold text-emerald-950">Peso <span id="cargo-weight">5 kg</span></span><strong class="text-2xl font-black text-emerald-800">S/ <span id="cargo-price">20.00</span></strong></div>
            <input id="cargo-range" type="range" min="1" max="50" value="5" class="mt-3 w-full accent-emerald-700">
            @if($supportNumber)
                <a id="cargo-whatsapp" target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode('Hola, deseo confirmar una cotización de caja de aproximadamente 5 kg con '.$name.'.') }}" class="mt-3 inline-flex h-11 items-center rounded-full bg-amber-400 px-5 text-sm font-black text-emerald-950">Confirmar por WhatsApp</a>
            @endif
        </section>
        @auth
            <details class="rounded-2xl bg-white p-4 text-sm text-slate-500 shadow-sm">
                <summary class="cursor-pointer font-bold text-slate-600">Reportar esta ficha</summary>
                <form method="POST" action="{{ route('companies.profile.report', $organization) }}" class="mt-3 space-y-2">
                    @csrf
                    <select name="reason" required class="w-full rounded-xl border-slate-200 text-sm"><option value="inappropriate">Imagen inapropiada</option><option value="false_information">Información falsa</option><option value="copyright">Derechos de autor</option><option value="other">Otro motivo</option></select>
                    <textarea name="details" rows="2" placeholder="Describe el problema" class="w-full rounded-xl border-slate-200 text-sm"></textarea>
                    <button class="rounded-full bg-rose-600 px-4 py-2 text-xs font-black text-white">Enviar reporte</button>
                </form>
            </details>
        @endauth
    </section>

    <section data-panel="galeria" class="company-panel mt-5" hidden>
        @if($gallery->isNotEmpty())
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach($gallery as $image)
                    <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" loading="lazy" class="h-44 w-full rounded-3xl object-cover shadow-sm sm:h-56 {{ $loop->first ? 'sm:col-span-2 sm:h-72' : '' }}">
                @endforeach
            </div>
        @else
            <div class="rounded-3xl bg-amber-400 p-8 text-emerald-950"><h2 class="text-xl font-black">Galería</h2><p class="mt-2 text-sm font-semibold">La empresa todavía no publicó fotografías propias.</p></div>
        @endif
    </section>

    <section data-panel="flota" class="company-panel mt-5 grid gap-4 sm:grid-cols-2" hidden>
        @forelse($fleet as $index => $fleetItem)
            @php
                $unit = $fleetItem['unit'];
                $unitIsAir = $fleetItem['mode'] === 'air';
            @endphp
            <article class="overflow-hidden rounded-3xl bg-white shadow-sm">
                <img src="{{ $unit->cover_image_path ?: asset($fallbackImages[$index % count($fallbackImages)]) }}" alt="{{ $unit->name }}" class="h-44 w-full object-cover">
                <div class="p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-black text-emerald-950">{{ $unit->name }}</h3>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $unitIsAir ? 'bg-sky-100 text-sky-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $unitIsAir ? 'Avioneta' : 'Embarcación' }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $unit->description ?: 'Unidad registrada para transporte de pasajeros.' }}</p>
                    <div class="mt-4 flex justify-between rounded-2xl bg-emerald-50 px-3 py-2 text-sm text-emerald-900"><span>{{ $unit->seat_capacity }} pasajeros</span><span>{{ $unit->registration_number ?: 'Matrícula validada' }}</span></div>
                </div>
            </article>
        @empty
            <div class="rounded-3xl bg-emerald-800 p-8 text-white sm:col-span-2"><h2 class="text-xl font-black">Flota</h2><p class="mt-2 text-sm text-emerald-100">No hay unidades publicadas en esta ficha.</p></div>
        @endforelse
    </section>

    <section data-panel="salidas" class="company-panel mt-5 space-y-3" hidden>
        <div class="rounded-3xl bg-amber-400 px-5 py-4 text-emerald-950"><h2 class="text-xl font-black">Salidas</h2><p class="mt-1 text-sm font-semibold">Elige una salida para ver los asientos y reservar.</p></div>
        @forelse($departures as $item)
            <x-departure-card :departure="$item['departure']" />
        @empty
            <div class="rounded-3xl bg-white p-7 shadow-sm"><h3 class="text-lg font-black text-emerald-950">Consulta la próxima salida con la boletería oficial</h3><p class="mt-2 text-sm text-slate-600">El operador actualiza sus zarpes según la capitanía y el río.</p>@if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}" class="mt-4 inline-flex h-11 items-center rounded-full bg-emerald-700 px-4 text-sm font-bold text-white">Consultar próxima salida</a>@endif</div>
        @endforelse
    </section>

    <section data-panel="opiniones" class="company-panel mt-5 space-y-4" hidden>
        @if($reviews->count())
            <div class="flex items-center gap-4 rounded-3xl bg-amber-400 p-6 text-emerald-950">
                <strong class="text-5xl font-black">{{ number_format($average, 1) }}</strong>
                <div><div class="text-lg">★★★★★</div><span class="text-sm font-bold">{{ $reviews->count() }} testimonio(s)</span></div>
            </div>
        @else
            <div class="rounded-3xl bg-emerald-800 p-7 text-white"><h2 class="text-xl font-black">Todavía no hay comentarios</h2><p class="mt-2 text-sm text-emerald-100">Cuando un viajero registrado comente, su opinión aparecerá aquí.</p></div>
        @endif
        @guest
            <div class="flex flex-col justify-between gap-3 rounded-3xl bg-white p-5 shadow-sm sm:flex-row sm:items-center"><p class="text-sm font-semibold text-emerald-950">Inicia sesión para comentar esta empresa.</p><a href="{{ route('login') }}" class="inline-flex h-11 items-center rounded-full bg-emerald-900 px-4 text-sm font-bold text-white">Iniciar sesión</a></div>
        @endguest
        @auth
            @if(! $ownComment)
                <form method="POST" action="{{ route('companies.comment', $organization) }}" class="space-y-3 rounded-3xl bg-white p-5 shadow-sm">
                    @csrf
                    <h3 class="text-lg font-black text-emerald-950">Comenta esta empresa</h3>
                    <p class="text-sm text-slate-600">Tu nombre se publica junto al comentario. No incluyas teléfonos ni documentos.</p>
                    <div class="flex flex-wrap gap-2">@for($rating = 1; $rating <= 5; $rating++)<label class="cursor-pointer"><input required type="radio" name="rating" value="{{ $rating }}" class="peer sr-only"><span class="inline-flex h-10 items-center rounded-full bg-amber-100 px-3 text-sm font-bold text-amber-900 peer-checked:bg-amber-400 peer-checked:text-emerald-950">{{ $rating }} ★</span></label>@endfor</div>
                    <textarea required minlength="10" maxlength="1000" name="comment" rows="3" placeholder="Cuenta cómo fue la atención, la embarcación o el viaje." class="w-full rounded-2xl border-emerald-100 text-sm">{{ old('comment') }}</textarea>
                    <button class="inline-flex h-11 items-center rounded-full bg-amber-400 px-5 text-sm font-black text-emerald-950">Publicar comentario</button>
                </form>
            @endif
            @foreach($completedReservations as $reservation)
                @php
                    $trip = $reservation->isAir() ? $reservation->airDeparture : $reservation->departure;
                    $route = $reservation->isAir() ? $trip?->airRoute : $trip?->transportRoute;
                    $origin = $reservation->isAir() ? $route?->origin_city : $route?->originPort?->city;
                    $destination = $reservation->isAir() ? $route?->destination_city : $route?->destinationPort?->city;
                    $travelerReview = $reservation->companyReview;
                    $reviewLabels = ['pending' => 'Pendiente de revisión', 'published' => 'Publicada', 'rejected' => 'No publicada'];
                @endphp
                <article class="rounded-3xl bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><span class="text-xs font-bold text-emerald-700">Reserva {{ $reservation->code }}</span><h3 class="mt-1 text-lg font-black text-emerald-950">{{ $origin }} → {{ $destination }}</h3><p class="mt-1 text-sm text-slate-500">Viaje completado {{ $trip?->departure_at?->translatedFormat('d M Y · H:i') }}</p></div>
                        @if($travelerReview)
                            <span class="rounded-full px-3 py-1 text-xs font-black {{ $travelerReview->status === 'published' ? 'bg-emerald-100 text-emerald-800' : ($travelerReview->status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">{{ $reviewLabels[$travelerReview->status] ?? $travelerReview->status }}</span>
                        @endif
                    </div>
                    @if($travelerReview)
                        <div class="mt-4"><div class="text-amber-500">{{ str_repeat('★', $travelerReview->rating) }}{{ str_repeat('☆', 5 - $travelerReview->rating) }}</div><p class="mt-2 text-sm leading-6 text-slate-700">{{ $travelerReview->comment }}</p>@if($travelerReview->status === 'pending')<p class="mt-3 text-sm font-semibold text-amber-800">NavegaYA está revisando tu opinión. Se mostrará públicamente después de aprobarla.</p>@endif</div>
                    @else
                        <form method="POST" action="{{ route('companies.review', $organization) }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                            <fieldset><legend class="text-sm font-bold text-emerald-950">Califica este viaje</legend><div class="mt-2 flex flex-wrap gap-2">@for($rating = 1; $rating <= 5; $rating++)<label class="cursor-pointer"><input required type="radio" name="rating" value="{{ $rating }}" class="peer sr-only"><span class="inline-flex h-10 items-center rounded-full bg-amber-100 px-3 text-sm font-bold text-amber-900 peer-checked:bg-amber-400">{{ $rating }} ★</span></label>@endfor</div></fieldset>
                            <textarea required minlength="10" maxlength="1000" name="comment" rows="4" placeholder="Describe la atención, puntualidad, comodidad y embarque." class="w-full rounded-2xl border-emerald-100 text-sm">{{ old('comment') }}</textarea>
                            <button class="rounded-full bg-amber-400 px-5 py-3 text-sm font-black text-emerald-950">Enviar opinión</button>
                        </form>
                    @endif
                </article>
            @endforeach
        @endauth
        <div class="grid gap-3">
            @foreach($reviews as $review)
                <article class="rounded-3xl bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-700 text-sm font-black text-white">{{ mb_strtoupper(mb_substr($review->user->name, 0, 1)) }}</span><strong class="text-emerald-950">{{ $review->user->name }}</strong></div>
                        <span class="font-bold text-amber-500">{{ str_repeat('★', $review->rating) }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ $review->comment }}</p>
                    @auth
                        <details class="mt-3"><summary class="cursor-pointer text-xs font-bold text-slate-400">Reportar opinión</summary><form method="POST" action="{{ route('companies.reviews.report', [$organization, $review]) }}" class="mt-2 flex flex-wrap gap-2">@csrf<select name="reason" required class="h-9 flex-1 rounded-xl border-slate-200 text-xs"><option value="harassment">Insultos o acoso</option><option value="false_information">Información falsa</option><option value="spam">Spam</option><option value="personal_data">Datos personales</option><option value="other">Otro motivo</option></select><button class="rounded-full bg-rose-600 px-3 text-xs font-black text-white">Reportar</button></form></details>
                    @endauth
                </article>
            @endforeach
        </div>
    </section>

    <x-b2b-ad-banner placement="company_footer" :city="$organization->base_city" class="mt-8" />
</main>
<script>
    (function () {
        var root = document.getElementById('company-ficha');
        if (!root) return;
        var names = ['informacion', 'galeria', 'flota', 'salidas', 'opiniones'];
        var cargoType = 'caja';
        var cargoWeight = 5;
        function price() {
            if (cargoType === 'sobre') return 15;
            if (cargoType === 'caja') return 20 + Math.max(0, cargoWeight - 5) * 2;
            return 35 + Math.max(0, cargoWeight - 15) * 1.5;
        }
        function paintCargo() {
            var weight = document.getElementById('cargo-weight');
            var amount = document.getElementById('cargo-price');
            var link = document.getElementById('cargo-whatsapp');
            if (weight) weight.textContent = cargoWeight + ' kg';
            if (amount) amount.textContent = price().toFixed(2);
            root.querySelectorAll('[data-cargo]').forEach(function (button) {
                var on = button.getAttribute('data-cargo') === cargoType;
                button.classList.toggle('bg-emerald-700', on);
                button.classList.toggle('text-white', on);
                button.classList.toggle('bg-emerald-50', !on);
                button.classList.toggle('text-emerald-900', !on);
            });
            if (link) {
                link.href = link.href.split('?')[0] + '?text=' + encodeURIComponent('Hola, deseo confirmar una cotización de ' + cargoType + ' de aproximadamente ' + cargoWeight + ' kg.');
            }
        }
        function show(name) {
            if (names.indexOf(name) === -1) name = 'informacion';
            root.querySelectorAll('[data-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-panel') !== name;
            });
            root.querySelectorAll('[data-open]').forEach(function (button) {
                button.classList.toggle('is-on', button.getAttribute('data-open') === name);
            });
            if (location.hash !== '#' + name) history.replaceState(null, '', '#' + name);
        }
        root.addEventListener('click', function (event) {
            var tab = event.target.closest('[data-open]');
            if (tab && root.contains(tab)) {
                show(tab.getAttribute('data-open'));
                document.getElementById('empresa-menu').scrollIntoView({ block: 'nearest' });
                return;
            }
            var cargo = event.target.closest('[data-cargo]');
            if (cargo && root.contains(cargo)) {
                cargoType = cargo.getAttribute('data-cargo');
                cargoWeight = cargoType === 'sobre' ? 1 : (cargoType === 'saco' ? 20 : 5);
                var range = document.getElementById('cargo-range');
                if (range) range.value = String(cargoWeight);
                paintCargo();
            }
        });
        var range = document.getElementById('cargo-range');
        if (range) range.addEventListener('input', function () {
            cargoWeight = Number(range.value);
            paintCargo();
        });
        var start = location.hash.replace('#', '');
        show(names.indexOf(start) === -1 ? 'informacion' : start);
    })();
</script>
</div>
</x-public-layout>
