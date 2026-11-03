<x-public-layout current="companies" :title="($organization->commercial_name ?: $organization->legal_name).' · Horarios y pasajes oficiales'">
<style>
    .company-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
    .company-stat{display:flex;align-items:center;gap:.75rem;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:.75rem .9rem;box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .company-stat svg{width:1.35rem;height:1.35rem;flex:none;color:#047857}
    .company-stat strong{display:block;font-size:1.25rem;line-height:1.1;color:#022c22}
    .company-stat strong.is-empty{font-size:.95rem}
    .company-stat em{font-style:normal;font-size:.68rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
    .company-tabs{display:flex;gap:.15rem;max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;position:sticky;top:4.25rem;z-index:40;scroll-margin-top:4.75rem;margin-top:1.25rem;padding:.3rem;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);scrollbar-width:none}
    .company-tabs::-webkit-scrollbar{display:none}
    .company-tab{display:inline-flex;min-height:2.5rem;align-items:center;white-space:nowrap;border:0;border-radius:.75rem;background:transparent;padding:.35rem .9rem;font-size:.9rem;font-weight:700;color:#334155;cursor:pointer}
    .company-tab:hover{background:#f0fdf4;color:#065f46}
    .company-tab:focus-visible{outline:2px solid #059669;outline-offset:2px}
    .company-tab.is-on{background:#ecfdf5;color:#064e3b;box-shadow:inset 0 -2px 0 #059669}
    .company-tab[data-open="salidas"].is-on{background:#fffbeb;color:#78350f;box-shadow:inset 0 -2px 0 #f59e0b}
    .company-panel[hidden]{display:none!important}
    .company-fact{display:flex;flex-direction:column;justify-content:center;min-height:4.25rem;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:.65rem .8rem;box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .company-fact span{font-size:.68rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#047857}
    .company-fact strong{margin-top:.15rem;font-size:.92rem;line-height:1.3;color:#022c22}
    .company-fact .is-quiet{text-transform:none;letter-spacing:0;font-weight:600;color:#64748b}
    .company-viewer{width:min(960px,92vw);border:0;border-radius:1rem;padding:.5rem;background:#fff}
    .company-viewer::backdrop{background:rgba(6,44,33,.72)}
    .company-logo{display:grid;place-items:center;width:6rem;height:6rem;margin-top:-3rem;overflow:hidden;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:.45rem;box-shadow:0 8px 18px rgba(15,23,42,.08)}
    .company-logo img{width:100%;height:100%;object-fit:contain}
    .company-photo{overflow:hidden;border:1px solid #e2e8f0;border-radius:1rem;background:#f8fafc}
    .company-photo img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover}
    .company-viewer img{display:block;max-height:78vh;width:100%;object-fit:contain;border-radius:.75rem;background:#f8fafc}
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
    $whatsappText = $organization->whatsapp_message ?: 'Hola, vi su perfil en NavegaYA y quisiera información sobre sus viajes.';
    $publishedRouteCount = $organization->transportRoutes->count() + $organization->airRoutes->count();
    $description = trim((string) $organization->public_description);
    $brief = $description !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $description), 180) : null;
    $locationLabel = $dock?->name ?: ($organization->address ?: $organization->base_city);
    $metaParts = array_values(array_filter([$locationLabel, $operatorMode, $organization->service_hours]));
    $routeLines = $organization->transportRoutes->map(function ($route) {
        $origin = $route->originPort?->city ?: $route->originPort?->name;
        $destination = $route->destinationPort?->city ?: $route->destinationPort?->name;

        return $origin && $destination && $origin !== $destination ? $origin.' → '.$destination : null;
    })->filter();
    $routeLines = $routeLines->concat($organization->airRoutes->map(function ($route) {
        return $route->origin_city && $route->destination_city ? $route->origin_city.' → '.$route->destination_city : null;
    })->filter())->unique()->values();
    $mapQuery = $dock?->name ?: $organization->address;
    if ($mapQuery && $dock?->city) {
        $mapQuery = trim($mapQuery.', '.$dock->city);
    }
    $ownComment = auth()->check()
        ? $reviews->first(fn ($review) => (int) $review->user_id === (int) auth()->id() && $review->reservation_id === null)
        : null;
@endphp

<div class="bg-slate-50 pb-16" id="company-ficha">
<main class="mx-auto max-w-6xl px-4 pt-4 sm:px-6">
    <a href="{{ route('companies.index') }}" class="mb-4 inline-flex text-sm font-bold text-emerald-800">← Empresas</a>
    @if(session('success'))<div class="mb-4 rounded-2xl bg-emerald-700 px-5 py-4 text-sm font-semibold text-white">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800"><strong>No pudimos enviar la opinión:</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="rounded-3xl bg-white shadow-sm">
        <div class="h-48 overflow-hidden rounded-t-3xl bg-emerald-900 sm:h-72">
            <img src="{{ $profilePublished && $organization->cover_image_path ? $organization->cover_image_path : asset('images/navegaya-hero-rio.png') }}" alt="Operaciones de {{ $name }}" class="h-full w-full object-cover">
        </div>
        <div class="px-5 pb-6 sm:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <div class="company-logo">
                        @if($profilePublished && $organization->logo_path)
                            <img src="{{ $organization->logo_path }}" alt="Logo de {{ $name }}" class="h-full w-full object-contain">
                        @else
                            <span class="text-2xl font-black text-emerald-800">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <h1 class="text-3xl font-black tracking-tight text-emerald-950">{{ $name }}</h1>
                        @if($organization->verified_at)
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800">Empresa verificada</span>
                        @endif
                        @if($reviews->count())
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">★ {{ number_format($average, 1) }}</span>
                        @endif
                    </div>
                    @if($metaParts)
                        <p class="mt-2 text-sm font-semibold text-emerald-800">{{ implode(' · ', $metaParts) }}</p>
                    @endif
                    @if($brief)
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $brief }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 sm:mb-1 sm:shrink-0">
                    @if($supportNumber)
                        <a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode($whatsappText) }}" class="inline-flex h-11 items-center gap-2 rounded-full bg-emerald-700 px-4 text-sm font-bold text-white">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="h-4 w-4" fill="currentColor"><path d="M12.04 2C6.58 2 2.15 6.4 2.15 11.83c0 1.74.46 3.44 1.34 4.94L2 22l5.39-1.41a10 10 0 0 0 4.65 1.18h.01c5.46 0 9.89-4.4 9.89-9.83C21.94 6.4 17.5 2 12.04 2zm5.76 14.17c-.24.68-1.4 1.3-1.94 1.38-.5.08-1.12.11-1.81-.11-.42-.14-.95-.31-1.64-.61-2.88-1.25-4.76-4.15-4.9-4.34-.14-.19-1.16-1.54-1.16-2.94s.73-2.08 1-2.37c.24-.28.64-.41 1.02-.41.12 0 .23 0 .33.01.3.01.45.03.65.5.24.58.82 2 .89 2.15.07.14.12.32.02.51-.09.19-.14.31-.28.48-.14.16-.29.36-.42.48-.14.13-.28.28-.12.54.16.26.72 1.18 1.54 1.91 1.06.95 1.95 1.24 2.23 1.38.28.14.44.12.6-.07.16-.19.7-.81.88-1.09.19-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.46.21.53.32.07.12.07.68-.17 1.36z"/></svg>
                            Contáctanos
                        </a>
                        <button type="button" data-open="salidas" class="inline-flex h-11 items-center rounded-full border border-emerald-800 bg-white px-4 text-sm font-bold text-emerald-900">Ver salidas</button>
                    @else
                        <button type="button" data-open="salidas" class="inline-flex h-11 items-center rounded-full bg-amber-400 px-4 text-sm font-black text-emerald-950">Ver salidas</button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="company-stats my-4">
        <article class="company-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 16.5 12 20l9-3.5M4 13l8 3 8-3M12 4 3 8l9 4 9-4-9-4Z"/></svg><span><strong>{{ $fleet->count() }}</strong><em>Flota</em></span></article>
        <article class="company-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V5m0 14h16M8 19v-4m4 4V9m4 10v-6"/></svg><span><strong>{{ $publishedRouteCount }}</strong><em>Rutas</em></span></article>
        <article class="company-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 15 5-4 4 3 3-2 6 4"/></svg><span><strong>{{ $gallery->count() }}</strong><em>Fotos</em></span></article>
        <article class="company-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 2.6 5.4L20.5 9.3l-4.2 4.1 1 5.9L12 16.8 6.7 19.3l1-5.9L3.5 9.3l5.9-.9L12 3Z"/></svg><span><strong class="{{ $reviews->count() ? '' : 'is-empty' }}">{{ $reviews->count() ? number_format($average, 1) : 'Sin reseñas' }}</strong><em>Calificación</em></span></article>
    </div>

    <nav id="empresa-menu" class="company-tabs" aria-label="Secciones de la empresa">
        <button type="button" data-open="informacion" class="company-tab is-on">Información</button>
        <button type="button" data-open="galeria" class="company-tab">Galería</button>
        <button type="button" data-open="flota" class="company-tab">Flota</button>
        <button type="button" data-open="salidas" class="company-tab">Salidas</button>
        <button type="button" data-open="opiniones" class="company-tab">Testimonios{{ $reviews->count() ? ' ('.$reviews->count().')' : '' }}</button>
    </nav>

    <section data-panel="informacion" class="company-panel mt-4 grid gap-3">
        @if($description !== '')
            <article class="rounded-2xl border border-slate-200 border-l-4 border-l-emerald-600 bg-white px-4 py-3 shadow-sm">
                <h2 id="company-information-title" class="text-sm font-black text-emerald-950">Información de la empresa</h2>
                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $description }}</p>
            </article>
        @endif
        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
            @if($organization->address)
                <article class="company-fact"><span>Dirección</span><strong>{{ $organization->address }}</strong></article>
            @endif
            @if($organization->service_hours)
                <article class="company-fact"><span>Horario</span><strong>{{ $organization->service_hours }}</strong></article>
            @endif
            @if($organization->phone || $supportNumber)
                <article class="company-fact"><span>Contacto</span><strong>{{ $organization->phone ?: $supportNumber }}</strong>@if($supportNumber)<span class="is-quiet">WhatsApp {{ $supportNumber }}</span>@endif</article>
            @endif
            @if($organization->legal_name)
                <article class="company-fact"><span>Razón social</span><strong>{{ $organization->legal_name }}</strong>@if($organization->ruc)<span class="is-quiet">RUC {{ $organization->ruc }}</span>@endif</article>
            @endif
            @if($dock)
                <article class="company-fact"><span>{{ $hasAir && ! $hasRiver ? 'Terminal' : 'Muelle' }}</span><strong>{{ $dock->name }}</strong>@if($dock->city)<span class="is-quiet">{{ $dock->city }}</span>@endif</article>
            @endif
            @if($organization->website)
                <article class="company-fact"><span>Web</span><strong><a href="{{ $organization->website }}" target="_blank" rel="noopener" class="underline">Sitio oficial</a></strong></article>
            @endif
        </div>
        @if($routeLines->isNotEmpty())
            <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <h3 class="text-sm font-black text-emerald-950">Rutas</h3>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach($routeLines as $line)
                        <li class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-900">{{ $line }}</li>
                    @endforeach
                </ul>
            </article>
        @endif
        @if($mapQuery)
            <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm">
                <span><strong class="text-emerald-950">Cómo llegar. </strong>{{ $mapQuery }}</span>
                <a target="_blank" rel="noopener" href="https://maps.google.com/?q={{ urlencode($mapQuery) }}" class="inline-flex h-8 items-center rounded-full bg-amber-400 px-3 text-xs font-black text-emerald-950">Mapa</a>
            </article>
        @endif
        <section class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="mr-1 text-sm font-black text-emerald-950">Encomiendas</h3>
                <button type="button" data-cargo="sobre" class="h-9 rounded-full bg-emerald-50 px-3 text-sm font-bold text-emerald-900">Sobre</button>
                <button type="button" data-cargo="caja" class="is-on h-9 rounded-full bg-emerald-700 px-3 text-sm font-bold text-white">Caja</button>
                <button type="button" data-cargo="saco" class="h-9 rounded-full bg-emerald-50 px-3 text-sm font-bold text-emerald-900">Bulto</button>
                <span class="ml-auto text-sm font-bold text-emerald-950"><span id="cargo-weight">5 kg</span> · S/ <span id="cargo-price">20.00</span></span>
                @if($supportNumber)
                    <a id="cargo-whatsapp" target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode('Hola, deseo confirmar una cotización de caja de aproximadamente 5 kg con '.$name.'.') }}" class="inline-flex h-9 items-center rounded-full border border-amber-300 bg-amber-50 px-3 text-sm font-black text-emerald-950">Cotizar</a>
                @endif
            </div>
            <p class="mt-2 text-xs text-slate-500">Referencia para cotizar. La empresa confirma el precio final.</p>
            <input id="cargo-range" type="range" min="1" max="50" value="5" class="mt-2 w-full accent-emerald-700">
        </section>
        @auth
            <details class="rounded-2xl bg-white px-4 py-3 text-sm text-slate-500 shadow-sm">
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
                    <button type="button" data-photo="{{ $image['src'] }}" data-photo-alt="{{ $image['alt'] }}" class="company-photo text-left shadow-sm {{ $loop->first ? 'sm:col-span-2' : '' }}">
                        <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" loading="lazy">
                    </button>
                @endforeach
            </div>
        @else
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <h2 class="text-base font-black text-emerald-950">Galería</h2>
                <p class="mt-2 text-sm text-slate-600">La empresa todavía no publicó fotografías propias.</p>
            </div>
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
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center sm:col-span-2"><h2 class="text-base font-black text-emerald-950">Flota</h2><p class="mt-2 text-sm text-slate-600">No hay unidades publicadas en esta ficha.</p></div>
        @endforelse
    </section>

    <section data-panel="salidas" class="company-panel mt-5 space-y-3" hidden>
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm"><h2 class="text-base font-black text-emerald-950">Salidas</h2><p class="mt-1 text-sm text-slate-600">Elige una salida para ver los asientos y reservar.</p></div>
        @forelse($departures as $item)
            <x-departure-card :departure="$item['departure']" />
        @empty
            <div class="rounded-3xl bg-white p-7 shadow-sm"><h3 class="text-lg font-black text-emerald-950">Consulta la próxima salida con la boletería oficial</h3><p class="mt-2 text-sm text-slate-600">El operador actualiza sus zarpes según la capitanía y el río.</p>@if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}" class="mt-4 inline-flex h-11 items-center rounded-full bg-emerald-700 px-4 text-sm font-bold text-white">Consultar próxima salida</a>@endif</div>
        @endforelse
    </section>

    <section data-panel="opiniones" class="company-panel mt-5 space-y-4" hidden>
        @if($reviews->count())
            <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-emerald-950 shadow-sm">
                <strong class="text-4xl font-black">{{ number_format($average, 1) }}</strong>
                <div><div class="text-amber-500">★★★★★</div><span class="text-sm font-bold text-slate-600">{{ $reviews->count() }} testimonio(s)</span></div>
            </div>
        @else
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"><h2 class="text-base font-black text-emerald-950">Todavía no hay comentarios</h2><p class="mt-2 text-sm text-slate-600">Cuando un viajero registrado comente, su opinión aparecerá aquí.</p></div>
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
<dialog id="company-photo" class="company-viewer">
    <form method="dialog" class="mb-2 flex justify-end"><button class="rounded-full bg-emerald-900 px-3 py-1.5 text-xs font-bold text-white">Cerrar</button></form>
    <img id="company-photo-img" alt="">
</dialog>
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
                return;
            }
            var photo = event.target.closest('[data-photo]');
            var viewer = document.getElementById('company-photo');
            var viewerImg = document.getElementById('company-photo-img');
            if (photo && viewer && viewerImg && typeof viewer.showModal === 'function') {
                viewerImg.src = photo.getAttribute('data-photo');
                viewerImg.alt = photo.getAttribute('data-photo-alt') || 'Foto de la empresa';
                viewer.showModal();
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
