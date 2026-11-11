<x-public-layout current="companies" :title="($organization->commercial_name ?: $organization->legal_name).' · Horarios y pasajes oficiales'">
<style>
    .company-wrap{width:min(1200px,calc(100% - 2rem));margin-inline:auto}
    #company-ficha{--tone-salidas:#0F6E56;--tone-informacion:#185FA5;--tone-flota:#534AB7;--tone-galeria:#D4537E;--tone-agencias:#D85A30;--tone-carga:#BA7517;--tone-comentarios:#185FA5;--tone-whatsapp:#25A35A;--tone-like:#D4537E;--tone-emoji:#EF9F27;--tone-send:#064E3B}
    .company-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
    .company-stat{display:flex;align-items:center;gap:.75rem;width:100%;border:1px solid #e4ebe7;border-radius:1rem;background:#fff;padding:.85rem 1rem;text-align:left;cursor:pointer}
    .company-tone{display:grid;width:36px;height:36px;flex:none;place-items:center;border-radius:999px;color:#fff}
    .company-tone svg{width:18px;height:18px;fill:currentColor}
    .company-tab .company-tone{width:22px;height:22px}
    .company-tab .company-tone svg{width:12px;height:12px}
    .company-tone.is-salidas{background:var(--tone-salidas)}.company-tone.is-informacion{background:var(--tone-informacion)}.company-tone.is-flota{background:var(--tone-flota)}.company-tone.is-galeria{background:var(--tone-galeria)}.company-tone.is-comentarios{background:var(--tone-comentarios)}.company-tone.is-agencias{background:var(--tone-agencias)}.company-tone.is-carga{background:var(--tone-carga)}.company-tone.is-like{background:var(--tone-like)}.company-tone.is-emoji{background:var(--tone-emoji)}.company-tone.is-send{background:var(--tone-send)}.company-tone.is-whatsapp{background:var(--tone-whatsapp)}
    .company-stat strong{display:block;font-size:1.35rem;line-height:1.1;color:#10231D}
    .company-stat strong.is-empty{font-size:1rem}
    .company-stat strong.is-phrase{font-size:.95rem;line-height:1.3;font-weight:800}
    .company-stat em{font-style:normal;font-size:.75rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#607069}
    .company-tabs{display:flex;gap:.35rem;max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;position:sticky;top:4.25rem;z-index:40;scroll-margin-top:4.75rem;margin-top:1.25rem;padding:.4rem;border:1px solid #e4ebe7;border-radius:1rem;background:#fff;scrollbar-width:none}
    .company-tabs::-webkit-scrollbar{display:none}
    .company-tab{display:inline-flex;min-height:44px;align-items:center;gap:.45rem;white-space:nowrap;border:0;border-radius:.8rem;background:transparent;padding:.4rem .7rem;font-size:1rem;font-weight:700;color:#607069;cursor:pointer}
    .company-tab:hover{background:#F7F9F8;color:#10231D}
    .company-tab:focus-visible{outline:2px solid #087F5B;outline-offset:2px}
    .company-tab.is-on{background:var(--menu-bar,#064E3B);color:#fff}
    .company-layout{display:grid;gap:1rem}
    .company-side{display:grid;gap:.75rem;align-content:start}
    .company-card{border:1px solid #e4ebe7;border-radius:1rem;background:#fff;padding:1rem 1.1rem}
    .company-card h2,.company-card h3{font-size:1.15rem;font-weight:800;color:#10231D}
    .company-panel-title{display:flex;align-items:center;gap:.65rem;margin:0 0 12px}
    .company-panel-title h2{margin:0;font-size:1.15rem;font-weight:800;color:#10231D}
    .cargo-scale{display:flex;justify-content:space-between;margin-top:.35rem;font-size:12px;font-weight:700;color:#607069}
    #cargo-weight-label{display:block;margin-top:12px;font-size:14px;font-weight:700;color:#10231D}
    .company-card p,.company-side p{font-size:1rem;line-height:1.55;color:#10231D}
    .company-kicker{display:block;font-size:.75rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#607069}
    .company-cargo{border:1px solid #e4ebe7;border-radius:1rem;background:#fff;padding:1rem 1.1rem}
    .company-cargo-note{margin-top:.65rem;font-size:.95rem;font-weight:600;color:#607069}
    .company-cargo-note.is-port{color:#9a3412}
    .company-quote{display:inline-flex;min-height:44px;align-items:center;border:0;border-radius:.8rem;background:#F4B820;padding:0 1rem;font-size:1rem;font-weight:800;color:#10231D}
    .company-map{display:inline-flex;min-height:44px;align-items:center;border:1px solid #087F5B;border-radius:.8rem;background:#fff;padding:0 1rem;font-size:1rem;font-weight:700;color:#064E3B}
    .company-go{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:.45rem;border-radius:.8rem;padding:0 1rem;font-size:1rem;font-weight:800}
    .company-mini{display:inline-grid;width:16px;height:16px;flex:none;place-items:center;border-radius:999px;background:var(--tone-whatsapp);color:#fff}
    .company-mini svg{width:10px;height:10px;fill:currentColor}
    .company-go-main{background:#0F6E56;color:#fff}
    .company-go-gold{background:#F4B820;color:#10231D}
    .company-go-wa{background:#087F5B;color:#fff}
    .company-go-seat{border:1px solid #d5e0db;background:#fff;color:#064E3B}
    @media (min-width:1024px){.company-layout{grid-template-columns:minmax(0,1fr) 320px;align-items:start}}
    .company-panel[hidden]{display:none!important}
    .company-fact{display:flex;flex-direction:column;justify-content:center;min-height:4.25rem;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:.65rem .8rem;box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .company-fact span{font-size:.68rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#047857}
    .company-fact strong{margin-top:.15rem;font-size:.92rem;line-height:1.3;color:#022c22}
    .company-fact .is-quiet{text-transform:none;letter-spacing:0;font-weight:600;color:#64748b}
    .company-viewer{width:min(960px,92vw);border:0;border-radius:1rem;padding:.5rem;background:#fff}
    .company-viewer::backdrop{background:rgba(6,44,33,.72)}
    .company-hero{position:relative}
    .company-carousel{display:flex;aspect-ratio:2.4/1;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none}
    .company-carousel::-webkit-scrollbar{display:none}
    .company-slide{position:relative;flex:0 0 100%;height:100%;overflow:hidden;scroll-snap-align:start;background:#062C21}
    .company-slide-blur{position:absolute;inset:-8%;width:116%;height:116%;object-fit:cover;filter:blur(18px);transform:scale(1.08)}
    .company-slide-photo{position:relative;z-index:1;width:100%;height:100%;object-fit:contain}
    .company-carousel-nav{position:absolute;right:.75rem;bottom:3.2rem;z-index:3;display:flex;gap:.35rem}
    .company-carousel-nav button{min-width:44px;min-height:44px;border:0;border-radius:999px;background:rgba(6,44,33,.82);color:#fff;font-weight:800;cursor:pointer}
    .company-top{display:flex;flex-direction:column;gap:1rem}
    .company-head{display:flex;flex-direction:column;align-items:center;gap:.75rem;min-width:0}
    figure[data-photo-extra][hidden]{display:none!important}
    .company-logo{display:grid;place-items:center;position:relative;z-index:5;box-sizing:border-box;flex:none;width:88px;height:88px;margin-top:-44px;overflow:hidden;border:4px solid #fff;border-radius:999px;background:#F4B820;color:#412402;box-shadow:0 8px 18px rgba(16,35,29,.18);font-size:28px;font-weight:800}
    .company-logo img{width:100%;height:100%;object-fit:cover}
    .company-name{min-width:0}
    .company-buy{position:fixed;left:1rem;right:5.5rem;bottom:1rem;z-index:40;display:flex;height:48px;align-items:center;justify-content:center;border:0;border-radius:12px;background:#0F6E56;color:#fff;font-size:16px;font-weight:800;box-shadow:0 8px 20px rgba(16,35,29,.18)}
    #company-ficha{padding-bottom:calc(48px + 2rem)}
    @media (min-width:640px){.company-head{flex-direction:row;align-items:flex-start;text-align:left}.company-logo{width:120px;height:120px;margin-top:-60px;font-size:36px}.company-name{padding-top:68px}}
    @media (min-width:1024px){#company-ficha{padding-bottom:2rem}.company-buy{display:none}.company-top{flex-direction:row;align-items:flex-end;justify-content:space-between}}
    .company-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .company-grid figure{position:relative;margin:0}
    .company-grid button.company-photo{width:100%}
    .company-more{position:absolute;top:.35rem;right:.35rem}
    .company-more summary{display:grid;width:36px;height:36px;place-items:center;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;list-style:none}
    .company-more summary::-webkit-details-marker{display:none}
    .company-more div{position:absolute;right:0;z-index:5;display:grid;min-width:12rem;margin-top:.25rem;border:1px solid #e4ebe7;border-radius:10px;background:#fff;padding:.35rem;box-shadow:0 8px 20px rgba(16,35,29,.12)}
    .company-more button{min-height:36px;border:0;border-radius:8px;background:transparent;padding:0 .5rem;text-align:left;font-weight:700;cursor:pointer}
    .company-lightbox{width:min(1080px,96vw);height:min(88vh,820px);max-height:92vh;border:0;border-radius:16px;padding:0;background:#fff;overflow:hidden}
    .company-lightbox::backdrop{background:rgba(6,44,33,.72)}
    .company-lightbox-grid{display:grid;height:100%;min-height:0}
    .company-lightbox-photo{display:grid;height:100%;min-height:0;place-items:center;background:#062C21}
    .company-lightbox-photo img{width:100%;height:100%;max-height:100%;object-fit:contain}
    .company-lightbox-side{display:flex;min-height:0;flex-direction:column;overflow:hidden}
    .company-lightbox-scroll{min-height:0;flex:1;overflow:auto;padding:1rem}
    .company-photo-title{font-size:1.05rem;font-weight:800;color:#10231D}
    .company-photo-meta{margin-top:.2rem;font-size:.8rem;font-weight:600;color:#607069}
    .company-react{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.85rem}
    .company-react-btn{display:inline-flex;min-height:44px;align-items:center;gap:.45rem;border:0;background:transparent;padding:0;font-weight:800;color:#10231D;cursor:pointer}
    .company-bubble{display:flex;gap:.55rem;margin-top:.7rem}
    .company-bubble.is-reply{margin-left:2.4rem}
    .company-avatar{display:grid;width:32px;height:32px;flex:none;place-items:center;border-radius:999px;background:#E7EEF8;color:#185FA5;font-size:12px;font-weight:800}
    .company-bubble-body{border-radius:16px;background:#F1F3F4;padding:.55rem .75rem}
    .company-bubble-body p{margin-top:.15rem}
    .company-composer{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem;border-top:1px solid #e4ebe7;padding:.75rem 1rem}
    .company-emoji{flex-basis:100%}
    .company-composer input{height:40px;min-width:0;flex:1;border:1px solid #e4ebe7;border-radius:999px;padding:0 .9rem;font-size:14px}
    .company-round{display:grid;width:36px;height:36px;flex:none;place-items:center;border:0;border-radius:999px;color:#fff;cursor:pointer}
    .company-round svg{width:16px;height:16px;fill:currentColor}
    .company-round.is-emoji{background:var(--tone-emoji)}
    .company-round.is-send{background:var(--tone-send)}
    .company-count{position:absolute;right:6px;bottom:6px;display:inline-flex;align-items:center;gap:4px;border-radius:999px;background:rgba(6,44,33,.82);padding:2px 7px 2px 3px;color:#fff;font-size:12px;font-weight:800;pointer-events:none}
    .company-count .company-tone{width:16px;height:16px}
    .company-count .company-tone svg{width:9px;height:9px}
    .company-more-photos{min-height:44px;margin-top:8px;border:1px solid #d5e0db;border-radius:10px;background:#fff;padding:0 1rem;font-weight:800;color:#064E3B;cursor:pointer}
    @media (max-width:767px){.company-lightbox{height:auto;max-height:92vh;overflow:auto}.company-lightbox-grid{height:auto}.company-lightbox-photo{height:42vh}}
    .company-seal{display:inline-flex;border-radius:999px;background:#E1F5EE;padding:.1rem .45rem;color:#04342C;font-size:.75rem;font-weight:800}
    .company-emoji{display:flex;flex-wrap:wrap;gap:.25rem;margin-top:.4rem}
    .company-emoji button{min-width:36px;min-height:36px;border:1px solid #e4ebe7;border-radius:8px;background:#fff}
    .policy-modal{width:min(640px,94vw);max-height:88vh;border:0;border-radius:16px;padding:0;background:#fff;color:#10231D}
    .policy-modal::backdrop{background:rgba(6,44,33,.72)}
    .policy-modal header,.policy-modal .policy-body{padding:1rem 1.1rem}
    .policy-modal header{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e4ebe7}
    .policy-tabs{display:flex;gap:.35rem;padding:.6rem 1.1rem 0}
    .policy-tabs button{min-height:36px;border:0;border-radius:8px;background:#F7F9F8;padding:0 .8rem;font-weight:800}
    .policy-tabs button.is-on{background:#0F6E56;color:#fff}
    .policy-point{display:flex;gap:.7rem;margin-top:.8rem}
    .policy-point strong{display:block}
    @media (min-width:768px){.company-lightbox-grid{grid-template-columns:minmax(0,1.2fr) minmax(16rem,.8fr)}}
    .company-photo{position:relative;overflow:hidden;border:0;border-radius:10px;background:#f8fafc;padding:0;cursor:pointer}
    .company-photo img{display:block;width:100%;aspect-ratio:1/1;object-fit:cover}
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
    @media (max-width:639px){.company-stat:first-child{grid-column:1 / -1}.company-head{text-align:center}.company-name{width:100%}.company-name .flex{justify-content:center}.company-actions{justify-content:center}.company-actions .company-go{padding:0 .65rem;font-size:14px}}
    @media (min-width:640px){.company-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
    @media (min-width:768px){.company-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media (min-width:1024px){.company-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
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
    $fleet = $organization->vessels->filter(fn ($unit) => \App\Support\CompanyBase::sellable($unit))->map(fn ($unit) => ['mode' => 'river', 'unit' => $unit])
        ->concat($organization->aircraft->filter(fn ($unit) => \App\Support\CompanyBase::sellable($unit))->map(fn ($unit) => ['mode' => 'air', 'unit' => $unit]))->values();
    $publicFleet = \App\Support\CompanyBase::publicFleetLabel($organization);
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
    $modalityChip = $hasRiver && $hasAir ? 'Aéreo y fluvial' : ($hasAir ? 'Aéreo' : 'Fluvial');
    $portChip = $baseLabel && $baseLabel !== 'Puerto base por definir' ? $baseLabel : null;
    $dicapiChip = $organization->vessels->contains(fn ($unit) => filled($unit->dicapi_certificate_number) && $unit->inspection_expires_at && ! $unit->inspection_expires_at->isPast());
    $slides = $profilePublished && $organization->banners->isNotEmpty()
        ? $organization->banners
        : collect([(object) ['path' => $profilePublished && $organization->cover_image_path ? $organization->cover_image_path : asset('images/navegaya-hero-rio.png'), 'position' => 1]]);
    $threadsByPhoto = ($threads ?? collect())->groupBy('organization_photo_id');
    $traveledLookup = array_flip($traveledIds ?? []);
    $shareUrl = route('companies.show', $organization);
@endphp

<div id="company-ficha" style="background:#F7F9F8;color:#10231D;{{ $menuStyle }}@if($organization->public_primary_color)--company-primary:{{ $organization->public_primary_color }};@endif @if($organization->public_accent_color)--company-accent:{{ $organization->public_accent_color }};@endif">
<main class="company-wrap pt-4">
    <a href="{{ route('companies.index') }}" class="mb-4 inline-flex text-sm font-bold text-emerald-800">← Empresas</a>
    @if(session('success'))<div class="mb-4 rounded-2xl bg-emerald-700 px-5 py-4 text-sm font-semibold text-white">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800"><strong>No pudimos enviar la opinión:</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="company-sheet rounded-3xl bg-white shadow-sm">
        <div class="company-hero overflow-hidden rounded-t-3xl bg-[#064E3B]">
            <div class="company-carousel" id="company-carousel">
                @foreach($slides as $banner)
                    <div class="company-slide">
                        <img class="company-slide-blur" src="{{ $banner->path }}" alt="" aria-hidden="true">
                        <img class="company-slide-photo" src="{{ $banner->path }}" alt="Portada {{ $banner->position }} de {{ $name }}">
                    </div>
                @endforeach
            </div>
            @if($slides->count() > 1)
                <div class="company-carousel-nav">
                    <button type="button" data-banner="-1" aria-label="Portada anterior">‹</button>
                    <button type="button" data-banner="1" aria-label="Portada siguiente">›</button>
                </div>
            @endif
        </div>
        <div class="company-identity px-5 pb-6 sm:px-8">
            <div class="company-top">
                <div class="company-head">
                    <div class="company-logo" id="company-logo">
                        @if($profilePublished && $organization->logo_path)
                            <img src="{{ $organization->logo_path }}" alt="Logo de {{ $name }}">
                        @else
                            @php
                                $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                            @endphp
                            <span>{{ $initials !== '' ? $initials : 'N' }}</span>
                        @endif
                    </div>
                    <div class="company-name">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-3xl font-black tracking-tight text-[#10231D] sm:text-4xl">{{ $name }}</h1>
                            @if($organization->verified_at)
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800">Empresa verificada</span>
                            @endif
                        </div>
                        <p class="mt-2 text-sm font-semibold text-emerald-800">{{ $commentCount }} {{ $commentCount === 1 ? 'comentario' : 'comentarios' }} · {{ $reactionCount }} {{ $reactionCount === 1 ? 'reacción' : 'reacciones' }}</p>
                        <p class="mt-2 flex flex-wrap gap-2 text-sm font-semibold text-[#10231D]">
                            <span class="rounded-full bg-[#F7F9F8] px-3 py-1">{{ $modalityChip }}</span>
                            @if($portChip)<span class="rounded-full bg-[#F7F9F8] px-3 py-1">{{ $portChip }}</span>@endif
                            @if($dicapiChip)<span class="rounded-full bg-[#E1F5EE] px-3 py-1 text-[#04342C]">DICAPI vigente</span>@endif
                            @if($organization->service_hours)<span class="rounded-full bg-[#F7F9F8] px-3 py-1">{{ $organization->service_hours }}</span>@endif
                        </p>
                        @if($brief)
                            <p class="mt-2 max-w-3xl text-base leading-7 text-[#607069]">{{ $brief }}</p>
                        @endif
                    </div>
                </div>
                <div class="company-actions flex flex-wrap gap-2">
                    <button type="button" data-open="salidas" class="company-go company-go-main">Ver salidas y comprar</button>
                    @if($supportNumber)
                        <a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode($whatsappText) }}" class="company-go company-go-gold"><span class="company-mini" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3a8.7 8.7 0 0 0-7.5 13L3.2 21l5-1.3A8.7 8.7 0 1 0 12 3zm4.8 11.7c-.2.6-1.1 1-1.6 1.1-.4 0-.9.2-2.9-.6-2.4-1-3.9-3.4-4-3.6-.1-.2-1-1.2-1-2.3s.6-1.6.8-1.8.4-.3.6-.3h.5c.2 0 .4 0 .5.4.2.5.7 1.8.7 1.9s.1.3 0 .5l-.3.4c-.1.1 0 .3.8 1.3.8.7 1.3.9 1.5 1 .2.1.3 0 .4-.1l.5-.6c.1-.2.3-.1.5 0l1.4.7c.2.1.3.2.4.3.1.4 0 .9-.2 1.1z"/></svg></span>WhatsApp</a>
                    @endif
                    <button type="button" data-open-policy class="company-go company-go-seat">Políticas de viaje</button>
                </div>
            </div>
        </div>
    </section>

    <div class="company-stats my-4">
        @if($publicFleet)
            <button type="button" class="company-stat" data-jump="flota"><span class="company-tone is-flota"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 15.2 12 19l9-3.8v-2.2L12 16.2 3 13v2.2ZM12 3 4 8.2 12 12l8-3.8L12 3Z"/></svg></span><span><strong class="is-phrase">{{ $publicFleet }}</strong><em>Flota</em></span></button>
        @endif
        @if($publishedRouteCount > 0)
            <button type="button" class="company-stat" data-jump="salidas"><span class="company-tone is-salidas"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20a1 1 0 0 1-1-1V4h2v14h15v2H4Zm3-3V12h3v5H7Zm5 0V7h3v10h-3Zm5 0v-6h3v6h-3Z"/></svg></span><span><strong>{{ $publishedRouteCount }}</strong><em>Rutas</em></span></button>
        @endif
        @if($organization->photos->count() > 0)
            <button type="button" class="company-stat" data-jump="galeria"><span class="company-tone is-galeria"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm2.2 3.2a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2ZM19 17l-4.2-5.2-2.6 3.1-1.7-2L6 17h13Z"/></svg></span><span><strong>{{ $organization->photos->count() }}</strong><em>Fotos</em></span></button>
        @endif
        @if($commentCount > 0)
            <button type="button" class="company-stat" data-jump="galeria" data-comments="1"><span class="company-tone is-comentarios"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-4 3.2V6a2 2 0 0 1 2-2Z"/></svg></span><span><strong>{{ $commentCount }}</strong><em>Comentarios</em></span></button>
        @endif
    </div>

    <nav id="empresa-menu" class="company-tabs" aria-label="Secciones de la empresa">
        <button type="button" data-open="salidas" class="company-tab is-on"><span class="company-tone is-salidas"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20a1 1 0 0 1-1-1V4h2v14h15v2H4Zm3-3V12h3v5H7Zm5 0V7h3v10h-3Zm5 0v-6h3v6h-3Z"/></svg></span>Salidas y tarifas</button>
        <button type="button" data-open="informacion" class="company-tab"><span class="company-tone is-informacion"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/></svg></span>Información</button>
        <button type="button" data-open="flota" class="company-tab"><span class="company-tone is-flota"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 15.2 12 19l9-3.8v-2.2L12 16.2 3 13v2.2ZM12 3 4 8.2 12 12l8-3.8L12 3Z"/></svg></span>Flota</button>
        <button type="button" data-open="galeria" class="company-tab"><span class="company-tone is-galeria"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm2.2 3.2a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2ZM19 17l-4.2-5.2-2.6 3.1-1.7-2L6 17h13Z"/></svg></span>Galería</button>
        <button type="button" data-open="agencias" class="company-tab"><span class="company-tone is-agencias"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9.2L12 4l8 5.2V20h-5.2v-5.5H9.2V20H4Z"/></svg></span>Agencias</button>
        <button type="button" data-open="carga" class="company-tab"><span class="company-tone is-carga"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.2 12 3l9 4.2v9.6L12 21l-9-4.2V7.2Z"/></svg></span>Encomiendas y carga</button>
    </nav>

    <section id="informacion" data-panel="informacion" class="company-panel company-layout mt-5" hidden>
        @include('companies.partials.panel-heading', ['tone' => 'informacion', 'title' => 'Información'])
        <div class="grid gap-4">
            <article class="company-card">
                <h2>Equipaje</h2>
                @foreach(\App\Support\BaggageAllowance::lines($organization) as $line)
                    <p class="mt-2">{{ $line }}</p>
                @endforeach
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

    <section id="galeria" data-panel="galeria" class="company-panel mt-5" hidden>
        @include('companies.partials.panel-heading', ['tone' => 'galeria', 'title' => 'Galería'])
        @if($organization->photos->isNotEmpty())
            <div class="company-grid">
                @foreach($organization->photos as $photo)
                    <figure @if($loop->index >= 8) data-photo-extra hidden @endif>
                        <button type="button" class="company-photo text-left" data-photo-open="{{ $photo->id }}" data-photo-index="{{ $loop->index }}" data-photo-src="{{ $photo->path }}" data-photo-alt="{{ $photo->alt_text ?: ($photo->caption ?: 'Foto de '.$name) }}" data-photo-title="{{ $photo->caption ?: 'Foto de '.$name }}" data-photo-album="{{ $photo->album?->title }}" data-photo-date="{{ $photo->created_at?->format('d/m/Y') }}" data-photo-comments="{{ route('companies.photos.comments', [$organization, $photo]) }}" data-photo-like="{{ route('companies.photos.likes', [$organization, $photo]) }}" data-photo-store="{{ route('companies.photos.comments.store', [$organization, $photo]) }}">
                            <img src="{{ $photo->path }}" alt="{{ $photo->alt_text ?: ($photo->caption ?: 'Foto de '.$name) }}" loading="lazy">
                            <span class="company-count"><span class="company-tone is-comentarios"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-4 3.2V6a2 2 0 0 1 2-2Z"/></svg></span>{{ $photo->published_comments_count }}</span>
                        </button>
                        @if($canManageMedia)
                            <details class="company-more">
                                <summary aria-label="Acciones de la foto">⋯</summary>
                                <div>
                                    <button type="button" data-set-cover="{{ $photo->id }}" data-cover-url="{{ route('company.gallery.photos.cover', $photo) }}">Establecer como portada</button>
                                    <button type="button" data-set-logo="{{ $photo->id }}" data-logo-url="{{ route('company.gallery.photos.logo', $photo) }}">Establecer como logo/perfil</button>
                                </div>
                            </details>
                        @endif
                    </figure>
                @endforeach
            </div>
            @if($organization->photos->count() > 8)
                <button type="button" id="company-photos-more" class="company-more-photos">Ver todas las fotos ({{ $organization->photos->count() }})</button>
            @endif
        @else
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <p class="text-sm text-slate-600">La empresa todavía no publicó fotografías propias.</p>
            </div>
        @endif
        @foreach($organization->photos as $photo)
            <div hidden data-thread="{{ $photo->id }}">
                @foreach($threadsByPhoto->get($photo->id, collect()) as $thread)
                    <article class="company-bubble">
                        <span class="company-avatar">{{ mb_strtoupper(mb_substr($thread->user?->name ?: 'V', 0, 1)) }}</span>
                        <div class="company-bubble-body">
                        <strong>{{ $thread->user?->name ?: 'Viajero' }}</strong>
                        @if(isset($traveledLookup[$thread->user_id]))<span class="company-seal">Viajó con la empresa</span>@endif
                        <p>{{ $thread->body }}</p>
                        </div>
                        @foreach($thread->replies as $reply)
                            <p><strong>{{ $reply->user?->name ?: 'Viajero' }}</strong> @if(isset($traveledLookup[$reply->user_id]))<span class="company-seal">Viajó con la empresa</span>@endif {{ $reply->body }}</p>
                        @endforeach
                    </article>
                @endforeach
            </div>
        @endforeach
    </section>

    <section id="flota" data-panel="flota" class="company-panel mt-5" hidden>
        @include('companies.partials.panel-heading', ['tone' => 'flota', 'title' => 'Flota'])
        <div class="grid gap-4 sm:grid-cols-2">
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
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center sm:col-span-2"><p class="text-sm text-slate-600">No hay unidades publicadas en esta ficha.</p></div>
        @endforelse
        </div>
    </section>

    <section id="salidas" data-panel="salidas" class="company-panel mt-5 space-y-3">
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            @include('companies.partials.panel-heading', ['tone' => 'salidas', 'title' => 'Salidas y tarifas'])
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

    <section id="agencias" data-panel="agencias" class="company-panel company-card mx-auto mt-5 max-w-3xl" hidden>
        @include('companies.partials.panel-heading', ['tone' => 'agencias', 'title' => 'Agencias'])
        <p class="mt-1 text-sm text-[#607069]">Terminales propios y agencias autorizadas</p>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse($organization->sites->where('is_active', true)->where('show_on_where_to_buy', true)->where('hidden_by_admin', false) as $site)
                <li><strong>{{ $site->name }}</strong> · {{ $site->city }} · {{ $site->address }}</li>
            @empty
                <li>La empresa todavía no publicó puntos de venta.</li>
            @endforelse
        </ul>
    </section>
    <section id="carga" data-panel="carga" class="company-panel mt-5" hidden>
        <section class="company-cargo" data-port="{{ $dock?->name ?: 'el puerto de embarque' }}" data-price-pequeno="{{ number_format($presentation['cargo']['pequeno'], 2, '.', '') }}" data-price-mediano="{{ number_format($presentation['cargo']['mediano'], 2, '.', '') }}" data-price-grande="{{ number_format($presentation['cargo']['grande'], 2, '.', '') }}" data-port-note="{{ $presentation['cargo']['port_note'] }}" data-weight-label="{{ trans('public.cargo_weight', ['weight' => '__KG__'], 'es') }}" data-reference-note="{{ trans('public.cargo_reference', [], 'es') }}">
            @include('companies.partials.panel-heading', ['tone' => 'carga', 'title' => 'Encomiendas y carga'])
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" data-cargo="pequeno" class="h-11 rounded-lg bg-emerald-50 px-3 text-base font-bold text-emerald-900">Pequeño</button>
                <button type="button" data-cargo="mediano" class="is-on h-11 rounded-lg bg-emerald-700 px-3 text-base font-bold text-white">Mediano</button>
                <button type="button" data-cargo="grande" class="h-11 rounded-lg bg-emerald-50 px-3 text-base font-bold text-emerald-900">Grande</button>
            </div>
            <p id="cargo-quote" class="mt-4 text-2xl font-black text-[#10231D]"><span id="cargo-amount">S/ <span id="cargo-price">{{ number_format($presentation['cargo']['mediano'], 2, '.', '') }}</span></span></p>
            <p id="cargo-note" class="company-cargo-note">{{ trans('public.cargo_reference', [], 'es') }}</p>
            <label id="cargo-weight-label" for="cargo-range">{{ trans('public.cargo_weight', ['weight' => 5], 'es') }}</label>
            <div class="cargo-scale"><span id="cargo-min">5 kg</span><span id="cargo-max">15 kg</span></div>
            <input id="cargo-range" type="range" min="5" max="15" value="5" class="mt-1 w-full accent-[#087F5B]" aria-label="{{ trans('public.cargo_weight', ['weight' => 5], 'es') }}">
            @if($cargoNumber)
                <a id="cargo-whatsapp" target="_blank" rel="noopener" href="https://wa.me/{{ $cargoNumber }}?text={{ urlencode('Hola, deseo cotizar una carga mediana de aproximadamente 5 kg.') }}" class="company-quote mt-3">Consultar por WhatsApp</a>
            @endif
        </section>
    </section>

    <x-b2b-ad-banner placement="company_footer" :city="$organization->basePort?->city" class="mt-8" />
</main>
<dialog id="company-photo" class="company-lightbox" aria-label="Foto">
    <div class="company-lightbox-grid">
        <div class="company-lightbox-photo"><img id="company-photo-img" alt=""></div>
        <div class="company-lightbox-side">
            <div class="company-lightbox-scroll">
                <div class="company-viewer-bar">
                    <button type="button" data-photo-step="-1">Anterior</button>
                    <button type="button" data-photo-step="1">Siguiente</button>
                    <button type="button" data-photo-close>Cerrar</button>
                </div>
                <h3 id="company-photo-title" class="company-photo-title">Foto de {{ $name }}</h3>
                <p id="company-photo-meta" class="company-photo-meta"></p>
                <div class="company-react">
                    @auth
                        <button type="button" id="company-photo-like" class="company-react-btn"><span class="company-tone is-like"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-6.5-4.1-6.5-8.4A3.6 3.6 0 0 1 12 8.7a3.6 3.6 0 0 1 6.5 2.9C18.5 15.9 12 20 12 20Z"/></svg></span>Me gusta <span id="company-photo-reactions">0</span></button>
                        <button type="button" id="company-photo-comment" class="company-react-btn"><span class="company-tone is-comentarios"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-4 3.2V6a2 2 0 0 1 2-2Z"/></svg></span>Comentar</button>
                    @else
                        <p class="text-sm font-semibold text-[#607069]">{{ trans('public.login_to_react', [], 'es') }}</p>
                        <a id="company-photo-login" data-auth-base="{{ route('login') }}" href="{{ route('login') }}" class="company-go company-go-main">{{ trans('public.log_in', [], 'es') }}</a>
                        <a id="company-photo-register" data-auth-base="{{ route('register') }}" href="{{ route('register') }}" class="company-go company-go-seat">{{ trans('public.create_account', [], 'es') }}</a>
                    @endauth
                </div>
                <div id="company-photo-thread" class="mt-2 text-sm"></div>
            </div>
            @auth
                <form id="company-photo-form" class="company-composer" method="POST">
                    @csrf
                    <input type="hidden" name="parent_id" value="">
                    <input type="text" name="body" required minlength="2" maxlength="500" placeholder="Escribe un comentario…" autocomplete="off">
                    <button type="button" id="company-emoji-toggle" class="company-round is-emoji" aria-label="Emojis"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM8.5 9.5a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 0 1 0-2.8Zm7 0a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 0 1 0-2.8ZM12 17.2a4.6 4.6 0 0 1-4-2.3l1.6-1a2.9 2.9 0 0 0 4.8 0l1.6 1a4.6 4.6 0 0 1-4 2.3Z"/></svg></button>
                    <div id="company-emoji-panel" class="company-emoji" hidden></div>
                    <button class="company-round is-send" aria-label="Enviar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.2 21 3l-6.8 18-2.4-7.2L3 11.2Z"/></svg></button>
                </form>
            @endauth
        </div>
    </div>
</dialog>
<dialog id="policy-modal" class="policy-modal" aria-labelledby="policy-title">
    <header>
        <h2 id="policy-title">Políticas de viaje</h2>
        <button type="button" data-policy-close aria-label="Cerrar">X</button>
    </header>
    <div class="policy-tabs" role="tablist">
        <button type="button" data-policy-tab="resumen" class="is-on" role="tab" aria-selected="true">Resumen</button>
        <button type="button" data-policy-tab="completo" role="tab" aria-selected="false">Completo</button>
    </div>
    <div class="policy-body" data-policy-panel="resumen">
        <div class="policy-point"><span class="company-tone is-informacion"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h7l5 5v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm6 1.5V8h4.2L13 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Z"/></svg></span><p><strong>Documentación</strong>Viaja con tu documento de identidad original.</p></div>
        <div class="policy-point"><span class="company-tone is-salidas"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 11.2-3.2 1.9-1-1.6 2.2-1.3V7h2v6.2Z"/></svg></span><p><strong>Presentación</strong>Llega entre 45 y 60 minutos antes del zarpe.</p></div>
        <div class="policy-point"><span class="company-tone is-carga"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.2 12 3l9 4.2v9.6L12 21l-9-4.2V7.2Z"/></svg></span><div><strong>Equipaje</strong>@foreach(\App\Support\BaggageAllowance::lines($organization) as $line)<p>{{ $line }}</p>@endforeach</div></div>
        <div class="policy-point"><span class="company-tone is-agencias"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5V2L7 6.5 12 11V8a5 5 0 1 1-4.6 7H5.2A7 7 0 1 0 12 5Z"/></svg></span><p><strong>Cancelaciones y reprogramaciones</strong>Si la empresa cancela o reprograma, el pasajero elige reembolso total o cambio sin costo.</p></div>
        <div class="policy-point"><span class="company-tone is-flota"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5.2v6.3c0 5 3.4 7.7 8 9.3 4.6-1.6 8-4.3 8-9.3V5.2L12 2Z"/></svg></span><p><strong>Conducta y objetos prohibidos</strong>La empresa puede negar el embarque por ebriedad, riesgo a otros pasajeros u objetos prohibidos.</p></div>
        <button type="button" class="company-go company-go-main mt-4" data-policy-tab="completo">Ver documento completo</button>
    </div>
    <div class="policy-body" data-policy-panel="completo" hidden>
        @if($travelPolicy)
            <article class="text-sm leading-7">{!! \App\Support\PolicyHtml::present($travelPolicy->body_html, $organization) !!}</article>
        @else
            <p>Esta empresa todavía no publicó el documento completo.</p>
        @endif
    </div>
</dialog>
<script>
    (function () {
        var root = document.getElementById('company-ficha');
        if (!root) return;
        var names = ['salidas', 'informacion', 'flota', 'galeria', 'agencias', 'carga'];
        var aliases = { testimonios: 'galeria', opiniones: 'galeria', experiencias: 'galeria', 'donde-comprar': 'agencias' };
        var cargoType = 'mediano';
        var cargoWeight = 5;
        var cargoLabels = { pequeno: 'pequeña', mediano: 'mediana', grande: 'grande' };
        var cargoRanges = { pequeno: { min: 1, max: 5 }, mediano: { min: 5, max: 15 }, grande: { min: 15, max: 50 } };
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
            var band = cargoRanges[cargoType] || cargoRanges.mediano;
            var range = document.getElementById('cargo-range');
            var weightLabel = document.getElementById('cargo-weight-label');
            var minLabel = document.getElementById('cargo-min');
            var maxLabel = document.getElementById('cargo-max');
            var amount = document.getElementById('cargo-price');
            var amountWrap = document.getElementById('cargo-amount');
            var note = document.getElementById('cargo-note');
            var link = document.getElementById('cargo-whatsapp');
            var port = cargoBox ? cargoBox.getAttribute('data-port') : 'el puerto de embarque';
            var customPortNote = cargoBox ? (cargoBox.getAttribute('data-port-note') || '') : '';
            var referenceNote = cargoBox ? cargoBox.getAttribute('data-reference-note') : 'Precio referencial. La empresa confirma el precio final.';
            var weightTemplate = cargoBox ? cargoBox.getAttribute('data-weight-label') : 'Peso aproximado: __KG__ kg';
            var portSentence = customPortNote || ('Desde 25 kg se cotiza en ' + port + '.');
            var atPort = cargoWeight >= 25;
            var label = cargoLabels[cargoType] || cargoType;
            if (range) {
                range.min = String(band.min);
                range.max = String(band.max);
                range.value = String(cargoWeight);
                range.setAttribute('aria-label', (weightTemplate || '').replace('__KG__', String(cargoWeight)));
            }
            if (weightLabel) weightLabel.textContent = (weightTemplate || '').replace('__KG__', String(cargoWeight));
            if (minLabel) minLabel.textContent = band.min + ' kg';
            if (maxLabel) maxLabel.textContent = band.max + ' kg';
            if (amountWrap) amountWrap.hidden = atPort;
            if (amount && !atPort) amount.textContent = price().toFixed(2);
            if (note) {
                note.classList.toggle('is-port', atPort);
                note.textContent = atPort ? portSentence : referenceNote;
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
        var photoIndex = 0;
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
        function show(name) {
            if (aliases[name]) name = aliases[name];
            if (names.indexOf(name) === -1) name = 'salidas';
            root.querySelectorAll('[data-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-panel') !== name;
            });
            root.querySelectorAll('[data-open]').forEach(function (button) {
                button.classList.toggle('is-on', button.getAttribute('data-open') === name && !button.hasAttribute('data-vessel'));
            });
            if (location.hash !== '#' + name) history.replaceState(null, '', '#' + name);
        }
        var policy = document.getElementById('policy-modal');
        var policyReturn = null;
        function openPolicy() {
            if (!policy || typeof policy.showModal !== 'function') return;
            policyReturn = document.activeElement;
            document.body.style.overflow = 'hidden';
            policy.showModal();
            var close = policy.querySelector('[data-policy-close]');
            if (close) close.focus();
        }
        function closePolicy() {
            if (!policy) return;
            policy.close();
            document.body.style.overflow = '';
            if (policyReturn && policyReturn.focus) policyReturn.focus();
        }
        function policyTab(name) {
            policy.querySelectorAll('[data-policy-tab]').forEach(function (button) {
                var on = button.getAttribute('data-policy-tab') === name;
                button.classList.toggle('is-on', on);
                button.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            policy.querySelectorAll('[data-policy-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-policy-panel') !== name;
            });
        }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && policy && policy.open) {
                event.preventDefault();
                closePolicy();
            }
            if (event.key !== 'Tab' || !policy || !policy.open) return;
            var items = Array.prototype.filter.call(policy.querySelectorAll('button, a, textarea, input'), function (item) {
                return !item.closest('[hidden]');
            });
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        var photoDialog = document.getElementById('company-photo');
        if (photoDialog) photoDialog.addEventListener('close', function () { document.body.style.overflow = ''; });
        var photos = Array.prototype.slice.call(root.querySelectorAll('[data-photo-open]'));
        var photoIndex = 0;
        var emojis = ['😊', '👍', '❤️', '😮', '😢', '🙏', '🔥', '👏'];
        var emojiPanel = document.getElementById('company-emoji-panel');
        if (emojiPanel) {
            emojis.forEach(function (emoji) {
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = emoji;
                button.addEventListener('click', function () {
                    var field = document.querySelector('#company-photo-form [name=body]');
                    if (field) field.value += emoji;
                });
                emojiPanel.appendChild(button);
            });
        }
        var emojiToggle = document.getElementById('company-emoji-toggle');
        if (emojiToggle && emojiPanel) emojiToggle.addEventListener('click', function () { emojiPanel.hidden = !emojiPanel.hidden; });
        function paintThread(photo) {
            var box = document.getElementById('company-photo-thread');
            var seed = root.querySelector('[data-thread="' + photo.getAttribute('data-photo-open') + '"]');
            if (!box) return;
            box.textContent = '';
            if (seed) box.appendChild(seed.cloneNode(true));
            var cloned = box.firstChild;
            if (cloned) cloned.hidden = false;
            fetch(photo.getAttribute('data-photo-comments'), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var reactions = document.getElementById('company-photo-reactions');
                    if (reactions) reactions.textContent = String(data.reactions || 0);
                    box.textContent = '';
                    (data.comments || []).forEach(function (item) { box.appendChild(commentNode(item)); });
                });
        }
        function initials(name) {
            var parts = String(name || 'V').trim().split(/\s+/).filter(Boolean);
            return ((parts[0] || 'V').charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
        }
        function commentNode(item) {
            var article = document.createElement('article');
            article.className = 'company-bubble' + (item.reply ? ' is-reply' : '');
            var avatar = document.createElement('span');
            avatar.className = 'company-avatar';
            avatar.textContent = initials(item.author);
            var bubble = document.createElement('div');
            bubble.className = 'company-bubble-body';
            var author = document.createElement('strong');
            author.textContent = item.author;
            bubble.appendChild(author);
            if (item.traveled) {
                var seal = document.createElement('span');
                seal.className = 'company-seal';
                seal.textContent = 'Viajó con la empresa';
                bubble.appendChild(seal);
            }
            var body = document.createElement('p');
            body.textContent = item.body;
            bubble.appendChild(body);
            article.appendChild(avatar);
            article.appendChild(bubble);
            var wrap = document.createElement('div');
            wrap.appendChild(article);
            if (document.getElementById('company-photo-form') && !item.reply) {
                var answer = document.createElement('button');
                answer.type = 'button';
                answer.className = 'company-quiet';
                answer.textContent = 'Responder';
                answer.addEventListener('click', function () {
                    var parent = document.querySelector('#company-photo-form [name=parent_id]');
                    var field = document.querySelector('#company-photo-form [name=body]');
                    if (parent) parent.value = String(item.id);
                    if (field) {
                        field.placeholder = 'Respuesta para ' + item.author;
                        field.focus();
                    }
                });
                bubble.appendChild(answer);
            }
            (item.replies || []).forEach(function (reply) {
                reply.reply = true;
                wrap.appendChild(commentNode(reply));
            });
            return wrap;
        }
        function openPhoto(index) {
            if (!photos.length) return;
            photoIndex = (index + photos.length) % photos.length;
            var photo = photos[photoIndex];
            var viewer = document.getElementById('company-photo');
            var image = document.getElementById('company-photo-img');
            var title = document.getElementById('company-photo-title');
            var meta = document.getElementById('company-photo-meta');
            if (image) { image.src = photo.getAttribute('data-photo-src'); image.alt = photo.getAttribute('data-photo-alt') || ''; }
            if (title) title.textContent = photo.getAttribute('data-photo-title') || title.textContent;
            if (meta) meta.textContent = [photo.getAttribute('data-photo-album'), photo.getAttribute('data-photo-date')].filter(Boolean).join(' · ');
            var form = document.getElementById('company-photo-form');
            if (form) {
                form.action = photo.getAttribute('data-photo-store');
                var parent = form.querySelector('[name=parent_id]');
                var field = form.querySelector('[name=body]');
                if (parent) parent.value = '';
                if (field) field.placeholder = 'Escribe un comentario…';
            }
            var back = window.location.pathname + '?foto=' + encodeURIComponent(photo.getAttribute('data-photo-open') || '');
            ['company-photo-login', 'company-photo-register'].forEach(function (id) {
                var link = document.getElementById(id);
                if (link) link.href = link.getAttribute('data-auth-base') + '?redirect=' + encodeURIComponent(back);
            });
            paintThread(photo);
            document.body.style.overflow = 'hidden';
            if (viewer && typeof viewer.showModal === 'function' && !viewer.open) viewer.showModal();
        }
        function applyMedia(kind, url, replaceId) {
            var token = document.querySelector('meta[name="csrf-token"]');
            var body = new FormData();
            if (replaceId) body.append('replace_id', replaceId);
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' }, body: body })
                .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, status: response.status, data: data }; }); })
                .then(function (result) {
                    if (result.status === 409 && result.data.choose) {
                        var choice = result.data.banners.map(function (banner) { return banner.position + ' (' + banner.id + ')'; }).join(' o ');
                        var picked = window.prompt('Ya hay dos portadas. Escribe el id de la que quieres reemplazar: ' + choice);
                        if (picked) applyMedia(kind, url, picked);
                        return;
                    }
                    if (!result.ok) return;
                    if (kind === 'logo' && result.data.logo) {
                        var logo = document.querySelector('#company-logo img') || document.createElement('img');
                        logo.src = result.data.logo;
                        logo.alt = 'Logo';
                        var holder = document.getElementById('company-logo');
                        if (holder && !holder.querySelector('img')) { holder.textContent = ''; holder.appendChild(logo); }
                    }
                    if (kind === 'cover' && result.data.banners) {
                        var carousel = document.getElementById('company-carousel');
                        if (!carousel) return;
                        carousel.textContent = '';
                        result.data.banners.forEach(function (banner) {
                            var slide = document.createElement('div');
                            slide.className = 'company-slide';
                            var blur = document.createElement('img');
                            blur.className = 'company-slide-blur';
                            blur.alt = '';
                            blur.src = banner.path;
                            var photo = document.createElement('img');
                            photo.className = 'company-slide-photo';
                            photo.alt = 'Portada';
                            photo.src = banner.path;
                            slide.appendChild(blur);
                            slide.appendChild(photo);
                            carousel.appendChild(slide);
                        });
                    }
                });
        }
        root.addEventListener('click', function (event) {
            var jump = event.target.closest('[data-jump]');
            if (jump && root.contains(jump)) {
                show(jump.getAttribute('data-jump'));
                if (jump.hasAttribute('data-comments') && photos[0]) openPhoto(0);
                document.getElementById('empresa-menu').scrollIntoView({ block: 'nearest' });
                return;
            }
            var policyHit = event.target.closest('[data-open-policy]');
            if (policyHit) { event.preventDefault(); openPolicy(); return; }
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
                var band = cargoRanges[cargoType] || cargoRanges.mediano;
                cargoWeight = cargoType === 'pequeno' ? 2 : (cargoType === 'grande' ? 15 : 5);
                if (cargoWeight < band.min) cargoWeight = band.min;
                if (cargoWeight > band.max) cargoWeight = band.max;
                paintCargo();
                return;
            }
            var step = event.target.closest('[data-photo-step]');
            if (step && !step.disabled) {
                openPhoto(photoIndex + Number(step.getAttribute('data-photo-step')));
                return;
            }
            var photo = event.target.closest('[data-photo-open]');
            if (photo && !event.target.closest('.company-more')) {
                openPhoto(Number(photo.getAttribute('data-photo-index') || 0));
                return;
            }
            var cover = event.target.closest('[data-set-cover]');
            if (cover) { applyMedia('cover', cover.getAttribute('data-cover-url')); return; }
            var logo = event.target.closest('[data-set-logo]');
            if (logo) { applyMedia('logo', logo.getAttribute('data-logo-url')); return; }
            if (event.target.closest('[data-policy-close]')) { closePolicy(); return; }
            var policyChoice = event.target.closest('[data-policy-tab]');
            if (policyChoice) { policyTab(policyChoice.getAttribute('data-policy-tab')); return; }
            if (event.target.closest('[data-photo-close]')) {
                var viewerClose = document.getElementById('company-photo');
                if (viewerClose) viewerClose.close();
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
            if (event.key === 'ArrowLeft') openPhoto(photoIndex - 1);
            if (event.key === 'ArrowRight') openPhoto(photoIndex + 1);
        });
        var range = document.getElementById('cargo-range');
        if (range) range.addEventListener('input', function () {
            cargoWeight = Number(range.value);
            paintCargo();
        });
        var carousel = document.getElementById('company-carousel');
        root.addEventListener('click', function (event) {
            var step = event.target.closest && event.target.closest('[data-banner]');
            if (!step || !carousel) return;
            carousel.scrollBy({ left: carousel.clientWidth * Number(step.getAttribute('data-banner')), behavior: 'smooth' });
        });
        var start = location.hash.replace('#', '');
        var openPolicies = start === 'politicas' || new URLSearchParams(location.search).get('politica') === '1';
        if (aliases[start]) start = aliases[start];
        show(names.indexOf(start) === -1 ? 'salidas' : start);
        window.addEventListener('hashchange', function () {
            var next = location.hash.replace('#', '');
            if (next === 'politicas') openPolicy();
            if (aliases[next]) next = aliases[next];
            if (names.indexOf(next) !== -1) show(next);
        });
        if (openPolicies) openPolicy();
        var requestedPhoto = new URLSearchParams(location.search).get('foto');
        if (requestedPhoto) {
            var requestedIndex = photos.findIndex(function (item) { return item.getAttribute('data-photo-open') === requestedPhoto; });
            if (requestedIndex >= 0) openPhoto(requestedIndex);
        }
        var morePhotos = document.getElementById('company-photos-more');
        if (morePhotos) morePhotos.addEventListener('click', function () {
            root.querySelectorAll('[data-photo-extra]').forEach(function (item) { item.hidden = false; });
            morePhotos.hidden = true;
        });
        var commentButton = document.getElementById('company-photo-comment');
        if (commentButton) commentButton.addEventListener('click', function () {
            var field = document.querySelector('#company-photo-form [name=body]');
            if (field) field.focus();
        });
        var photoForm = document.getElementById('company-photo-form');
        if (photoForm) photoForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var token = document.querySelector('meta[name="csrf-token"]');
            fetch(photoForm.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' }, body: new FormData(photoForm) })
                .then(function (response) {
                    if (!response.ok) return;
                    var field = photoForm.querySelector('[name=body]');
                    var parent = photoForm.querySelector('[name=parent_id]');
                    if (field) { field.value = ''; field.placeholder = 'Escribe un comentario…'; }
                    if (parent) parent.value = '';
                });
        });
        var like = document.getElementById('company-photo-like');
        if (like) like.addEventListener('click', function () {
            var photo = photos[photoIndex];
            if (!photo) return;
            var token = document.querySelector('meta[name="csrf-token"]');
            fetch(photo.getAttribute('data-photo-like'), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var reactions = document.getElementById('company-photo-reactions');
                    if (reactions && data.likes !== undefined) reactions.textContent = String(data.likes);
                });
        });
    })();
</script>
<button type="button" data-open="salidas" class="company-buy">Comprar</button>
</div>
</x-public-layout>
