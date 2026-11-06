<x-public-layout current="companies" :title="($organization->commercial_name ?: $organization->legal_name).' · Horarios y pasajes oficiales'">
<style>
    .company-wrap{width:min(1200px,calc(100% - 2rem));margin-inline:auto}
    .company-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
    .company-stat{display:flex;align-items:center;gap:.75rem;border:1px solid #e4ebe7;border-radius:1rem;background:#fff;padding:.85rem 1rem}
    .company-stat svg{width:1.35rem;height:1.35rem;flex:none;color:#087F5B}
    .company-stat strong{display:block;font-size:1.35rem;line-height:1.1;color:#10231D}
    .company-stat strong.is-empty{font-size:1rem}
    .company-stat em{font-style:normal;font-size:.75rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#607069}
    .company-tabs{display:flex;gap:.35rem;max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;position:sticky;top:4.25rem;z-index:40;scroll-margin-top:4.75rem;margin-top:1.25rem;padding:.4rem;border:1px solid #e4ebe7;border-radius:1rem;background:#fff;scrollbar-width:none}
    .company-tabs::-webkit-scrollbar{display:none}
    .company-tab{display:inline-flex;min-height:44px;align-items:center;white-space:nowrap;border:0;border-radius:.8rem;background:transparent;padding:.4rem 1rem;font-size:1rem;font-weight:700;color:#607069;cursor:pointer}
    .company-tab:hover{background:#F7F9F8;color:#10231D}
    .company-tab:focus-visible{outline:2px solid #087F5B;outline-offset:2px}
    .company-tab.is-on{background:var(--menu-bar,#064E3B);color:#fff}
    .company-layout{display:grid;gap:1rem}
    .company-side{display:grid;gap:.75rem;align-content:start}
    .company-card{border:1px solid #e4ebe7;border-radius:1rem;background:#fff;padding:1rem 1.1rem}
    .company-card h2,.company-card h3{font-size:1.15rem;font-weight:800;color:#10231D}
    .company-card p,.company-side p{font-size:1rem;line-height:1.55;color:#10231D}
    .company-kicker{display:block;font-size:.75rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#607069}
    .company-cargo{border:1px solid #e4ebe7;border-radius:1rem;background:#fff;padding:1rem 1.1rem}
    .company-cargo-note{margin-top:.65rem;font-size:.95rem;font-weight:600;color:#607069}
    .company-cargo-note.is-port{color:#9a3412}
    .company-quote{display:inline-flex;min-height:44px;align-items:center;border:0;border-radius:.8rem;background:#F4B820;padding:0 1rem;font-size:1rem;font-weight:800;color:#10231D}
    .company-map{display:inline-flex;min-height:44px;align-items:center;border:1px solid #087F5B;border-radius:.8rem;background:#fff;padding:0 1rem;font-size:1rem;font-weight:700;color:#064E3B}
    .company-go{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border-radius:.8rem;padding:0 1rem;font-size:1rem;font-weight:800}
    .company-go-wa{background:#087F5B;color:#fff}
    .company-go-seat{background:#F4B820;color:#10231D}
    @media (min-width:1024px){.company-layout{grid-template-columns:minmax(0,1fr) 320px;align-items:start}}
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
    .company-viewer-bar{display:flex;flex-wrap:wrap;gap:.4rem;justify-content:flex-end;margin-bottom:.45rem}
    .company-viewer-bar button{min-height:2.75rem;border:0;border-radius:999px;background:#062c21;padding:0 .9rem;font-size:.85rem;font-weight:800;color:#fff;cursor:pointer}
    .company-viewer-bar button:disabled{opacity:.4;cursor:default}
    .company-viewer-bar button:focus-visible,.company-quiet:focus-visible,.company-filters select:focus-visible,.company-filters input:focus-visible{outline:2px solid #059669;outline-offset:2px}
    .company-viewer-count{margin-top:.35rem;text-align:center;font-size:.8rem;font-weight:700;color:#475569}
    [data-departure][hidden]{display:none!important}
    .company-filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:.6rem}
    .company-filters label{display:flex;flex-direction:column;gap:.25rem;min-width:8.5rem;font-size:.8rem;font-weight:700;color:#607069}
    .company-filters select,.company-filters input{height:44px;border:1px solid #e4ebe7;border-radius:.7rem;background:#fff;padding:0 .7rem;font-size:1rem;font-weight:600;color:#10231D}
    .company-cargo [data-cargo]{min-height:44px}
    .company-quiet{display:inline-flex;min-height:44px;align-items:center;border:1px solid #d5e0db;border-radius:.8rem;background:#fff;padding:0 .9rem;font-size:1rem;font-weight:700;color:#064E3B;cursor:pointer}
    .company-quiet:hover{background:#F7F9F8}
    @media (min-width:640px){.company-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
</style>
@php
    $name = $organization->commercial_name ?: $organization->legal_name;
    $presentation = \App\Support\CompanyPublicPresentation::forOrganization($organization);
    $menuStyle = \App\Support\CompanyPublicPresentation::menuStyle($presentation);
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
    $departuresByVehicle = $departures->groupBy(fn ($item) => $item['mode'].'-'.$item['vehicle']->id);
    $departureOrigins = $departures->pluck('origin')->filter(fn ($city) => $city && $city !== '—')->unique()->sort()->values();
    $departureDestinations = $departures->pluck('destination')->filter(fn ($city) => $city && $city !== '—')->unique()->sort()->values();
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
    $commentPhoto = $organization->photos->first();
    $dock = $ports->first();
    $supportNumber = $organization->publicWhatsapp();
    $cargoNumber = $organization->publicWhatsapp('cargo');
    $supportLabel = \App\Support\PhoneNumber::display($supportNumber);
    $whatsappText = trim(($organization->whatsapp_message ?: 'Hola, vi su perfil en NavegaYA y quisiera información sobre sus viajes.').($organization->whatsappNotice() ? ' '.$organization->whatsappNotice() : ''));
    $publishedRouteCount = $organization->transportRoutes->count() + $organization->airRoutes->count();
    $description = trim((string) $organization->public_description);
    $brief = $description !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $description), 180) : null;
    $baseLabel = \App\Support\CompanyBase::label($organization);
    $fleetPhrase = \App\Support\CompanyBase::fleetPhrase($organization);
    $locationLabel = $baseLabel ?: ($organization->address ?: null);
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
    $copyPhone = $organization->phone ?: $supportNumber;
    $shareUrl = route('companies.show', $organization);
    $ownComment = auth()->check()
        ? $reviews->first(fn ($review) => (int) $review->user_id === (int) auth()->id() && $review->reservation_id === null)
        : null;
@endphp

<div class="pb-24" id="company-ficha" style="background:#F7F9F8;color:#10231D;{{ $menuStyle }}@if($organization->public_primary_color)--company-primary:{{ $organization->public_primary_color }};@endif @if($organization->public_accent_color)--company-accent:{{ $organization->public_accent_color }};@endif">
<main class="company-wrap pt-4">
    <a href="{{ route('companies.index') }}" class="mb-4 inline-flex text-sm font-bold text-emerald-800">← Empresas</a>
    @if(session('success'))<div class="mb-4 rounded-2xl bg-emerald-700 px-5 py-4 text-sm font-semibold text-white">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800"><strong>No pudimos enviar la opinión:</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="rounded-3xl bg-white shadow-sm">
        <div class="h-56 overflow-hidden rounded-t-3xl bg-[#064E3B] sm:h-72 lg:h-80">
            <img src="{{ $profilePublished && $organization->cover_image_path ? $organization->cover_image_path : asset('images/navegaya-hero-rio.png') }}" alt="Operaciones de {{ $name }}" class="h-full w-full object-cover object-center">
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
                        <h1 class="text-3xl font-black tracking-tight text-[#10231D] sm:text-4xl">{{ $name }}</h1>
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
                        <p class="mt-2 max-w-3xl text-base leading-7 text-[#607069]">{{ $brief }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if($copyPhone)
                            <button type="button" class="company-quiet" data-copy="{{ $copyPhone }}">Copiar teléfono</button>
                        @endif
                        <button type="button" class="company-quiet" data-share="{{ $shareUrl }}" data-share-title="{{ $name }}">Compartir perfil</button>
                        <span id="company-toast" role="status" class="text-sm font-semibold text-emerald-800"></span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:mb-1 sm:shrink-0">
                    @if($supportNumber)
                        <a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode($whatsappText) }}" class="company-go company-go-wa gap-2">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="h-5 w-5" fill="currentColor"><path d="M12.04 2C6.58 2 2.15 6.4 2.15 11.83c0 1.74.46 3.44 1.34 4.94L2 22l5.39-1.41a10 10 0 0 0 4.65 1.18h.01c5.46 0 9.89-4.4 9.89-9.83C21.94 6.4 17.5 2 12.04 2zm5.76 14.17c-.24.68-1.4 1.3-1.94 1.38-.5.08-1.12.11-1.81-.11-.42-.14-.95-.31-1.64-.61-2.88-1.25-4.76-4.15-4.9-4.34-.14-.19-1.16-1.54-1.16-2.94s.73-2.08 1-2.37c.24-.28.64-.41 1.02-.41.12 0 .23 0 .33.01.3.01.45.03.65.5.24.58.82 2 .89 2.15.07.14.12.32.02.51-.09.19-.14.31-.28.48-.14.16-.29.36-.42.48-.14.13-.28.28-.12.54.16.26.72 1.18 1.54 1.91 1.06.95 1.95 1.24 2.23 1.38.28.14.44.12.6-.07.16-.19.7-.81.88-1.09.19-.28.37-.23.62-.14.26.09 1.63.77 1.91.91.28.14.46.21.53.32.07.12.07.68-.17 1.36z"/></svg>
                            Contactar por WhatsApp
                        </a>
                    @endif
                    <button type="button" data-open="salidas" class="company-go company-go-seat">Ver salidas</button>
                </div>
            </div>
        </div>
    </section>

    <div class="company-stats my-4">
        <article class="company-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 16.5 12 20l9-3.5M4 13l8 3 8-3M12 4 3 8l9 4 9-4-9-4Z"/></svg><span><strong class="is-empty">{{ $organization->vessels->count() }} {{ $organization->vessels->count() === 1 ? 'embarcación' : 'embarcaciones' }} · {{ $organization->vessels->where('status', 'ready')->count() }} {{ $organization->vessels->where('status', 'ready')->count() === 1 ? 'habilitada' : 'habilitadas' }}</strong><em>Flota</em></span></article>
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
        <button type="button" data-open="politicas" class="company-tab">Políticas de viaje</button>
        <button type="button" data-open="donde-comprar" class="company-tab">Dónde comprar</button>
    </nav>

    <section data-panel="informacion" class="company-panel company-layout mt-5">
        <div class="grid gap-4">
            <article class="company-card">
                <h2>Equipaje</h2>
                <p class="mt-2">Equipaje incluido: mano {{ $baggage['hand'] }} kg, bodega {{ $baggage['hold'] }} kg, aéreo {{ $baggage['air'] }} kg. Desde {{ $baggage['cargo'] }} kg se registra como carga.</p>
            </article>
            @if($description !== '')
                <article class="company-card">
                    <h2 id="company-information-title">Información de la empresa</h2>
                    <p class="mt-2 whitespace-pre-line">{{ $description }}</p>
                </article>
            @endif
            @if($routeLines->isNotEmpty())
                <article class="company-card">
                    <h3>Rutas disponibles</h3>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach($routeLines as $line)
                            <li class="rounded-lg bg-[#F7F9F8] px-3 py-2 text-base font-semibold text-[#10231D]">{{ $line }}</li>
                        @endforeach
                    </ul>
                </article>
            @endif
            @if($mapQuery)
                <article class="company-card flex flex-wrap items-center justify-between gap-3">
                    <p><strong>Cómo llegar. </strong>{{ $mapQuery }}</p>
                    <a target="_blank" rel="noopener" href="https://maps.google.com/?q={{ urlencode($mapQuery) }}" class="company-map">Ver mapa</a>
                </article>
            @endif
            <section class="company-cargo" data-port="{{ $dock?->name ?: 'el puerto de embarque' }}" data-price-pequeno="{{ number_format($presentation['cargo']['pequeno'], 2, '.', '') }}" data-price-mediano="{{ number_format($presentation['cargo']['mediano'], 2, '.', '') }}" data-price-grande="{{ number_format($presentation['cargo']['grande'], 2, '.', '') }}" data-port-note="{{ $presentation['cargo']['port_note'] }}">
                <h3>Cotiza tu encomienda</h3>
                <p class="mt-1 text-base text-[#607069]">El importe es referencial. La empresa confirma el precio final.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" data-cargo="pequeno" class="h-11 rounded-lg bg-emerald-50 px-3 text-base font-bold text-emerald-900">Pequeño</button>
                    <button type="button" data-cargo="mediano" class="is-on h-11 rounded-lg bg-emerald-700 px-3 text-base font-bold text-white">Mediano</button>
                    <button type="button" data-cargo="grande" class="h-11 rounded-lg bg-emerald-50 px-3 text-base font-bold text-emerald-900">Grande</button>
                </div>
                <p id="cargo-quote" class="mt-4 text-2xl font-black text-[#10231D]"><span id="cargo-weight">5 kg</span><span id="cargo-amount"> · S/ <span id="cargo-price">{{ number_format($presentation['cargo']['mediano'], 2, '.', '') }}</span></span></p>
                <p id="cargo-note" class="company-cargo-note">Referencia para cotizar. La empresa confirma el precio final.</p>
                <input id="cargo-range" type="range" min="1" max="50" value="5" class="mt-3 w-full accent-[#087F5B]" aria-label="Peso aproximado">
                @if($cargoNumber)
                    <a id="cargo-whatsapp" target="_blank" rel="noopener" href="https://wa.me/{{ $cargoNumber }}?text={{ urlencode('Hola, deseo cotizar una carga mediana de aproximadamente 5 kg.') }}" class="company-quote mt-3">Consultar por WhatsApp</a>
                @endif
            </section>
        </div>
        <aside class="company-side">
            @if($organization->phone || $supportNumber || $organization->service_hours || $organization->address || $baseLabel || $organization->website)
                <article class="company-card">
                    <h3>Contacto</h3>
                    @if($organization->phone || $supportNumber)
                        <p class="mt-2"><span class="company-kicker">Teléfono</span>{{ $organization->phone ?: $supportNumber }}</p>
                    @endif
                    @if($supportLabel)
                        <p class="mt-2"><span class="company-kicker">WhatsApp</span>{{ $supportLabel }}</p>
                        <a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode($whatsappText) }}" class="company-go company-go-wa mt-3">Contactar por WhatsApp</a>
                    @endif
                    @if($organization->service_hours)
                        <p class="mt-3"><span class="company-kicker">Horario de atención</span>{{ $organization->service_hours }}</p>
                    @endif
                    @if($organization->address)
                        <p class="mt-3"><span class="company-kicker">Dirección</span>{{ $organization->address }}</p>
                    @endif
                    @if($baseLabel)
                        <p class="mt-3"><span class="company-kicker">Puerto base</span>{{ $baseLabel }}</p>
                    @endif
                    @if($organization->website)
                        <p class="mt-3"><span class="company-kicker">Sitio web</span><a href="{{ $organization->website }}" target="_blank" rel="noopener" class="underline">Sitio oficial</a></p>
                    @endif
                </article>
            @endif
            @if($organization->legal_name || $organization->ruc)
                <article class="company-card">
                    @if($organization->legal_name)
                        <p><span class="company-kicker">Razón social</span>{{ $organization->legal_name }}</p>
                    @endif
                    @if($organization->ruc)
                        <p class="mt-2 text-sm text-[#607069]"><span class="company-kicker">RUC</span>{{ $organization->ruc }}</p>
                    @endif
                </article>
            @endif
            @auth
                <details class="company-card text-sm text-[#607069]">
                    <summary class="cursor-pointer font-bold text-[#10231D]">Reportar esta ficha</summary>
                    <form method="POST" action="{{ route('companies.profile.report', $organization) }}" class="mt-3 space-y-2">
                        @csrf
                        <select name="reason" required class="w-full rounded-xl border-slate-200 text-sm"><option value="inappropriate">Imagen inapropiada</option><option value="false_information">Información falsa</option><option value="copyright">Derechos de autor</option><option value="other">Otro motivo</option></select>
                        <textarea name="details" rows="2" placeholder="Describe el problema" class="w-full rounded-xl border-slate-200 text-sm"></textarea>
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white">Enviar reporte</button>
                    </form>
                </details>
            @endauth
        </aside>
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
        @if($commentPhoto)
            <div class="mt-4 rounded-3xl bg-white p-4" data-photo-comments="{{ route('companies.photos.comments', [$organization, $commentPhoto]) }}">
                <h3 class="text-base font-black">Comentarios de la foto</h3>
                <ul data-comment-list class="mt-2 space-y-1 text-sm"></ul>
                @auth
                    <form class="mt-3 flex flex-wrap gap-2" action="{{ route('companies.photos.comments.store', [$organization, $commentPhoto]) }}" method="POST">
                        @csrf
                        <textarea name="body" required minlength="2" maxlength="500" class="min-h-16 flex-1 rounded-xl border border-slate-200 p-2 text-sm" placeholder="Escribe un comentario"></textarea>
                        <button type="button" data-emoji="😊" class="h-11 rounded-xl border px-3">😊</button>
                        <button class="h-11 rounded-xl bg-[#F4B820] px-3 text-sm font-extrabold">Comentar</button>
                    </form>
                @endauth
            </div>
            <script>
                (function () {
                    var box = document.querySelector('[data-photo-comments]');
                    if (!box) return;
                    var load = function () {
                        fetch(box.getAttribute('data-photo-comments'), { headers: { 'Accept': 'application/json' } })
                            .then(function (response) { return response.json(); })
                            .then(function (data) {
                                var list = box.querySelector('[data-comment-list]');
                                list.textContent = '';
                                (data.comments || []).forEach(function (item) {
                                    var row = document.createElement('li');
                                    row.textContent = item.author + ': ' + item.body;
                                    list.appendChild(row);
                                });
                            });
                    };
                    load();
                    window.setInterval(load, 12000);
                    box.addEventListener('click', function (event) {
                        var emoji = event.target && event.target.getAttribute && event.target.getAttribute('data-emoji');
                        var field = box.querySelector('textarea');
                        if (emoji && field) field.value += emoji;
                    });
                })();
            </script>
        @endif
    </section>

    <section id="flota" data-panel="flota" class="company-panel mt-5 grid gap-4 sm:grid-cols-2" hidden>
        @forelse($fleet as $index => $fleetItem)
            @php
                $unit = $fleetItem['unit'];
                $unitIsAir = $fleetItem['mode'] === 'air';
                $unitDepartureCount = $departuresByVehicle->get($fleetItem['mode'].'-'.$unit->id)?->count() ?? 0;
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
                    @if($unitDepartureCount > 0)
                        <button type="button" class="company-quiet mt-3" data-open="salidas" data-vessel="{{ $fleetItem['mode'] }}-{{ $unit->id }}" data-vessel-name="{{ $unit->name }}">Ver sus salidas ({{ $unitDepartureCount }})</button>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Sin salidas próximas.</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center sm:col-span-2"><h2 class="text-base font-black text-emerald-950">Flota</h2><p class="mt-2 text-sm text-slate-600">No hay unidades publicadas en esta ficha.</p></div>
        @endforelse
    </section>

    <section data-panel="salidas" class="company-panel mt-5 space-y-3" hidden>
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <h2 class="text-base font-black text-emerald-950">Salidas</h2>
            <p class="mt-1 text-sm text-slate-600">Elige una salida para ver los asientos y reservar. El WhatsApp de cada salida incluye la ruta y la fecha.</p>
            @if($departures->isNotEmpty())
                <div class="company-filters mt-3">
                    <label>Origen
                        <select id="salida-origen">
                            <option value="">Todos</option>
                            @foreach($departureOrigins as $city)<option value="{{ $city }}">{{ $city }}</option>@endforeach
                        </select>
                    </label>
                    <label>Destino
                        <select id="salida-destino">
                            <option value="">Todos</option>
                            @foreach($departureDestinations as $city)<option value="{{ $city }}">{{ $city }}</option>@endforeach
                        </select>
                    </label>
                    <label>Fecha
                        <input id="salida-fecha" type="date">
                    </label>
                    <button type="button" id="salida-limpiar" class="company-quiet">Limpiar</button>
                    <p id="salida-count" class="text-sm font-bold text-emerald-950">{{ $departures->count() }} {{ $departures->count() === 1 ? 'salida' : 'salidas' }}</p>
                </div>
                <p id="salida-embarcacion" class="mt-2 text-sm font-semibold text-emerald-800" hidden></p>
            @endif
        </div>
        @foreach($departures as $item)
            <div data-departure data-origin="{{ $item['origin'] }}" data-destination="{{ $item['destination'] }}" data-date="{{ $item['departure']->departure_at->toDateString() }}" data-vehicle="{{ $item['mode'] }}-{{ $item['vehicle']->id }}">
                <x-ui.departure-card :departure="$item['departure']" />
            </div>
        @endforeach
        <p id="salida-empty" class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-6 text-sm text-slate-600" hidden>Ninguna salida coincide con la búsqueda.</p>
        @if($departures->isEmpty())
            <div class="company-card px-6 py-10 text-center"><h3 class="text-xl">Todavía no hay salidas publicadas</h3><p class="mt-2 text-[#607069]">Cuando la empresa publique un zarpe, aquí verás fecha, hora, ruta, unidad y tarifa.</p>@if($supportNumber)<a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}" class="company-go company-go-wa mt-4">Consultar próxima salida</a>@endif</div>
        @endif
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

    <x-b2b-ad-banner placement="company_footer" :city="$organization->basePort?->city" class="mt-8" />
</main>
<dialog id="company-photo" class="company-viewer">
    <div class="company-viewer-bar">
        <button type="button" data-photo-step="-1">Anterior</button>
        <button type="button" data-photo-step="1">Siguiente</button>
        <form method="dialog"><button>Cerrar</button></form>
    </div>
    <img id="company-photo-img" alt="">
    <p id="company-photo-count" class="company-viewer-count"></p>
</dialog>
<script>
    (function () {
        var root = document.getElementById('company-ficha');
        if (!root) return;
        var names = ['informacion', 'galeria', 'flota', 'salidas', 'opiniones', 'politicas', 'donde-comprar'];
        var cargoType = 'mediano';
        var cargoWeight = 5;
        var cargoLabels = { pequeno: 'pequeña', mediano: 'mediana', grande: 'grande' };
        var cargoBox = root.querySelector('.company-cargo');
        var cargoBases = {
            pequeno: cargoBox ? Number(cargoBox.getAttribute('data-price-pequeno')) : 15,
            mediano: cargoBox ? Number(cargoBox.getAttribute('data-price-mediano')) : 20,
            grande: cargoBox ? Number(cargoBox.getAttribute('data-price-grande')) : 35
        };
        function price() {
            var amount = cargoBases[cargoType];
            return Number.isFinite(amount) ? amount : 20;
        }
        function paintCargo() {
            var weight = document.getElementById('cargo-weight');
            var amount = document.getElementById('cargo-price');
            var amountWrap = document.getElementById('cargo-amount');
            var note = document.getElementById('cargo-note');
            var link = document.getElementById('cargo-whatsapp');
            var port = cargoBox ? cargoBox.getAttribute('data-port') : 'el puerto de embarque';
            var customPortNote = cargoBox ? (cargoBox.getAttribute('data-port-note') || '') : '';
            var portSentence = customPortNote || ('Desde 25 kg se cotiza en ' + port + '.');
            var atPort = cargoWeight >= 25;
            var label = cargoLabels[cargoType] || cargoType;
            if (weight) weight.textContent = cargoWeight + ' kg';
            if (amountWrap) amountWrap.hidden = atPort;
            if (amount && !atPort) amount.textContent = price().toFixed(2);
            if (note) {
                note.classList.toggle('is-port', atPort);
                note.textContent = atPort
                    ? portSentence
                    : 'Referencia para cotizar. La empresa confirma el precio final.';
            }
            root.querySelectorAll('[data-cargo]').forEach(function (button) {
                var on = button.getAttribute('data-cargo') === cargoType;
                button.classList.toggle('bg-emerald-700', on);
                button.classList.toggle('text-white', on);
                button.classList.toggle('bg-emerald-50', !on);
                button.classList.toggle('text-emerald-900', !on);
            });
            if (link) {
                var text = atPort
                    ? 'Hola, deseo cotizar una carga ' + label + ' de aproximadamente ' + cargoWeight + ' kg. ' + portSentence
                    : 'Hola, deseo cotizar una carga ' + label + ' de aproximadamente ' + cargoWeight + ' kg.';
                link.href = link.href.split('?')[0] + '?text=' + encodeURIComponent(text);
            }
        }
        var vesselFilter = '';
        var vesselName = '';
        var photos = [];
        var photoIndex = 0;
        root.querySelectorAll('[data-photo]').forEach(function (button, index) {
            photos.push({ src: button.getAttribute('data-photo'), alt: button.getAttribute('data-photo-alt') || 'Foto de la empresa' });
            button.setAttribute('data-photo-index', String(index));
        });
        function toast(text) {
            var el = document.getElementById('company-toast');
            if (!el) return;
            el.textContent = text;
            clearTimeout(toast.timer);
            toast.timer = setTimeout(function () { el.textContent = ''; }, 2500);
        }
        function legacyCopy(text) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
            return Promise.resolve();
        }
        function copyText(text) {
            return new Promise(function (resolve) {
                var settled = false;
                function finish() {
                    if (!settled) {
                        settled = true;
                        resolve();
                    }
                }
                legacyCopy(text).then(finish);
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(finish, finish);
                }
                setTimeout(finish, 400);
            });
        }
        function departureCount(shown) {
            return shown === 1 ? '1 salida' : shown + ' salidas';
        }
        function applyFilters() {
            var origin = document.getElementById('salida-origen');
            var destination = document.getElementById('salida-destino');
            var date = document.getElementById('salida-fecha');
            var shown = 0;
            root.querySelectorAll('[data-departure]').forEach(function (card) {
                var ok = true;
                if (origin && origin.value && card.getAttribute('data-origin') !== origin.value) ok = false;
                if (destination && destination.value && card.getAttribute('data-destination') !== destination.value) ok = false;
                if (date && date.value && card.getAttribute('data-date') !== date.value) ok = false;
                if (vesselFilter && card.getAttribute('data-vehicle') !== vesselFilter) ok = false;
                card.hidden = !ok;
                if (ok) shown++;
            });
            var count = document.getElementById('salida-count');
            var empty = document.getElementById('salida-empty');
            var note = document.getElementById('salida-embarcacion');
            if (count) count.textContent = departureCount(shown);
            if (empty) empty.hidden = shown !== 0;
            if (note) {
                note.hidden = !vesselFilter;
                note.textContent = vesselFilter ? 'Salidas de ' + vesselName : '';
            }
        }
        function showPhoto(index) {
            var viewerImg = document.getElementById('company-photo-img');
            var label = document.getElementById('company-photo-count');
            if (!photos.length || !viewerImg) return;
            photoIndex = (index + photos.length) % photos.length;
            viewerImg.src = photos[photoIndex].src;
            viewerImg.alt = photos[photoIndex].alt;
            if (label) label.textContent = (photoIndex + 1) + ' de ' + photos.length;
            root.querySelectorAll('[data-photo-step]').forEach(function (button) {
                button.disabled = photos.length < 2;
            });
        }
        function show(name) {
            if (names.indexOf(name) === -1) name = 'informacion';
            root.querySelectorAll('[data-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-panel') !== name;
            });
            root.querySelectorAll('[data-open]').forEach(function (button) {
                button.classList.toggle('is-on', button.getAttribute('data-open') === name && !button.hasAttribute('data-vessel'));
            });
            if (location.hash !== '#' + name) history.replaceState(null, '', '#' + name);
        }
        root.addEventListener('click', function (event) {
            var tab = event.target.closest('[data-open]');
            if (tab && root.contains(tab)) {
                if (tab.hasAttribute('data-vessel')) {
                    vesselFilter = tab.getAttribute('data-vessel');
                    vesselName = tab.getAttribute('data-vessel-name') || '';
                } else if (tab.getAttribute('data-open') === 'salidas') {
                    vesselFilter = '';
                    vesselName = '';
                }
                show(tab.getAttribute('data-open'));
                if (tab.getAttribute('data-open') === 'salidas') applyFilters();
                document.getElementById('empresa-menu').scrollIntoView({ block: 'nearest' });
                return;
            }
            var cargo = event.target.closest('[data-cargo]');
            if (cargo && root.contains(cargo)) {
                cargoType = cargo.getAttribute('data-cargo');
                cargoWeight = cargoType === 'pequeno' ? 2 : (cargoType === 'grande' ? 15 : 5);
                var range = document.getElementById('cargo-range');
                if (range) range.value = String(cargoWeight);
                paintCargo();
                return;
            }
            var step = event.target.closest('[data-photo-step]');
            if (step && !step.disabled) {
                showPhoto(photoIndex + Number(step.getAttribute('data-photo-step')));
                return;
            }
            var photo = event.target.closest('[data-photo]');
            var viewer = document.getElementById('company-photo');
            if (photo && viewer && typeof viewer.showModal === 'function') {
                showPhoto(Number(photo.getAttribute('data-photo-index') || 0));
                viewer.showModal();
                return;
            }
            var copy = event.target.closest('[data-copy]');
            if (copy) {
                copyText(copy.getAttribute('data-copy')).then(function () { toast('Teléfono copiado'); });
                return;
            }
            var share = event.target.closest('[data-share]');
            if (share) {
                var url = share.getAttribute('data-share');
                var title = share.getAttribute('data-share-title') || document.title;
                if (navigator.share) {
                    navigator.share({ title: title, text: title, url: url }).catch(function () {});
                } else {
                    copyText(url).then(function () { toast('Enlace copiado'); });
                }
            }
        });
        root.addEventListener('change', function (event) {
            if (event.target && event.target.closest && event.target.closest('.company-filters')) applyFilters();
        });
        var clearFilters = document.getElementById('salida-limpiar');
        if (clearFilters) clearFilters.addEventListener('click', function () {
            ['salida-origen', 'salida-destino', 'salida-fecha'].forEach(function (id) {
                var field = document.getElementById(id);
                if (field) field.value = '';
            });
            vesselFilter = '';
            vesselName = '';
            applyFilters();
        });
        document.addEventListener('keydown', function (event) {
            var viewer = document.getElementById('company-photo');
            if (!viewer || !viewer.open) return;
            if (event.key === 'ArrowLeft') showPhoto(photoIndex - 1);
            if (event.key === 'ArrowRight') showPhoto(photoIndex + 1);
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
<a href="#salidas" class="fixed inset-x-4 bottom-4 z-40 flex h-12 items-center justify-center rounded-xl bg-[#F4B820] text-base font-extrabold text-[#10231D] shadow-lg lg:hidden">Comprar</a>
<section id="politicas" data-panel="politicas" class="company-panel company-card mx-auto mt-5 max-w-3xl" hidden>
    <h2>Políticas de viaje</h2>
    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
        @foreach($policyPoints as $point)
            <li>{{ $point }}</li>
        @endforeach
    </ul>
    @if($travelPolicy)
        <a href="{{ route('companies.policy', $organization) }}" class="company-map mt-4">Leer la política completa</a>
    @endif
</section>
<section id="donde-comprar" data-panel="donde-comprar" class="company-panel company-card mx-auto mt-5 max-w-3xl" hidden>
    <h2>Dónde comprar</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse($organization->sites->where('is_active', true)->where('show_on_where_to_buy', true)->where('hidden_by_admin', false) as $site)
            <li><strong>{{ $site->name }}</strong> · {{ $site->city }} · {{ $site->address }}</li>
        @empty
            <li>La empresa todavía no publicó puntos de venta.</li>
        @endforelse
    </ul>
</section>
</div>
</x-public-layout>
