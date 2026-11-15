<x-public-layout title="Guía de Loreto: hospedajes, gastronomía y experiencias · NavegaYA">
@php($types = [
    null => ['Todos', 'compass', 'ny-tone-leaf'],
    'lodging' => ['Hospedajes', 'hotel', 'ny-tone-river'],
    'gastronomy' => ['Gastronomía', 'utensils', 'ny-tone-sun'],
    'attraction' => ['Tours', 'leaf', 'ny-tone-leaf'],
    'tourist_transport' => ['Transporte turístico', 'sailboat', 'ny-tone-river'],
    'traveler_service' => ['Servicios', 'support', 'ny-tone-violet'],
    'local_business' => ['Comercios', 'company_details', 'ny-tone-coral'],
])
@php($iconFor = fn (string $kind) => match ($kind) { 'lodging' => 'hotel', 'gastronomy' => 'utensils', 'attraction' => 'leaf', 'tourist_transport' => 'sailboat', 'traveler_service' => 'support', 'local_business' => 'company_details', default => 'compass' })
@php($toneFor = fn (string $kind) => match ($kind) { 'lodging' => 'ny-tone-river', 'gastronomy' => 'ny-tone-sun', 'attraction' => 'ny-tone-leaf', 'tourist_transport' => 'ny-tone-river', 'traveler_service' => 'ny-tone-violet', default => 'ny-tone-coral' })
@php($cover = function (?string $path): ?string { if (blank($path)) { return null; } if (str_starts_with($path, 'http') || str_starts_with($path, '/')) { return $path; } return asset('storage/'.$path); })
<main class="ny-guide pb-16">
 <header class="ny-guide-hero">
  <img src="/images/branding/banner-guia-loreto.webp" alt="Selva y río en Loreto">
  <div class="ny-shade"></div>
  <div class="ny-copy">
   <p class="ny-kicker">Amazonía peruana</p>
   <h1 class="ny-display">Guía Loreto</h1>
   <p>Hospedajes, restaurantes y experiencias de quienes ya viajan por el río.</p>
  </div>
 </header>
 <div class="mx-auto max-w-6xl px-4 sm:px-6">
  <section class="ny-guide-filters" aria-label="Filtros de la guía">
   <nav class="ny-guide-types">
    @foreach($types as $key => $item)
     <a href="{{ route('guide.index', array_filter(['ciudad' => $cityId, 'tipo' => $key])) }}" class="ny-guide-type {{ (($key === '' || $key === null) && $type === null) || $type === $key ? 'is-on' : '' }}" @if((($key === '' || $key === null) && $type === null) || $type === $key) aria-current="true" @endif>
      <span class="ny-mark {{ $item[2] }}"><x-ui.icon :name="$item[1]" /></span>{{ $item[0] }}
     </a>
    @endforeach
   </nav>
   <form class="ny-guide-city" method="GET">
    <label for="guia-ciudad">Ciudad</label>
    <input type="hidden" name="tipo" value="{{ $type }}">
    <select id="guia-ciudad" name="ciudad" onchange="this.form.submit()">
     <option value="">Todas las ciudades</option>
     @foreach($cities as $city)<option value="{{ $city->id }}" @selected($cityId === $city->id)>{{ $city->name }}</option>@endforeach
    </select>
   </form>
  </section>

  @if($ads->isNotEmpty())
  <section class="ny-guide-grid" aria-label="Anuncios patrocinados">
   @foreach($ads as $ad)
    @php($adPhoto = $cover($ad->image_url))
    <a href="{{ route('discover.ad', $ad) }}" target="_blank" rel="sponsored noopener" class="ny-card-link">
     <div class="ny-photo">
      @if($adPhoto)<img src="{{ $adPhoto }}" alt="{{ $ad->business_name }}">@else<div class="ny-photo-fallback"><span class="ny-mark ny-tone-sun"><x-ui.icon name="megaphone" /></span></div>@endif
      <span class="ny-badge is-paid">Patrocinado</span>
     </div>
     <div class="ny-card-body">
      <p class="ny-meta">{{ $ad->city?->name ?? 'Loreto' }}@if($ad->category) · {{ $ad->category }}@endif</p>
      <h2>{{ $ad->title ?: $ad->business_name }}</h2>
      @if($ad->description)<p>{{ $ad->description }}</p>@endif
      <span class="ny-btn-main">Ver promoción</span>
     </div>
    </a>
   @endforeach
  </section>
  @endif

  @if($listings->isEmpty())
   <section class="ny-guide-empty">
    <span class="ny-mark ny-tone-leaf"><x-ui.icon name="compass" /></span>
    <h2>Todavía no hay negocios en esta búsqueda</h2>
    <p>Prueba otra ciudad o categoría. Si tienes un hotel, restaurante o tour, puedes publicar tu página en la guía.</p>
    <a class="ny-btn-main" href="{{ route('advertising.create') }}">Publica tu negocio</a>
   </section>
  @else
   <section class="ny-guide-grid" aria-label="Negocios">
    @foreach($listings as $listing)
     @php($label = $types[$listing->type][0] ?? 'Negocio')
     @php($photo = $cover($listing->banner_path ?: $listing->images->first()?->path))
     @php($reviewCount = $listing->organization_id ? $listing->organization->reviews()->where('status', 'approved')->count() : 0)
     @php($reviewAvg = $reviewCount > 0 ? round((float) $listing->organization->reviews()->where('status', 'approved')->avg('rating'), 1) : null)
     <article class="ny-card-link">
      <a href="{{ route('guide.show', $listing) }}" class="ny-photo">
       @if($photo)<img src="{{ $photo }}" alt="{{ $listing->name }}">@else<div class="ny-photo-fallback"><span class="ny-mark {{ $toneFor($listing->type) }}"><x-ui.icon :name="$iconFor($listing->type)" /></span></div>@endif
       <span class="ny-badge">{{ $label }}</span>
      </a>
      <div class="ny-card-body">
       <p class="ny-meta">{{ $label }} · {{ $listing->city->name }}</p>
       <h2><a href="{{ route('guide.show', $listing) }}">{{ $listing->name }}</a></h2>
       @if($reviewAvg)
        <p class="ny-stars" aria-label="{{ $reviewAvg }} de 5, {{ $reviewCount }} opiniones">
         @for($star = 1; $star <= 5; $star++)<x-ui.icon name="star" @class(['is-on' => $star <= round($reviewAvg)]) />@endfor
         <b>{{ $reviewAvg }}</b>
        </p>
       @endif
       @if($listing->price_reference)<p class="ny-price">{{ $listing->price_reference }}</p>@endif
       <div class="ny-card-actions">
        <a class="ny-btn-main" href="{{ route('guide.show', $listing) }}">Ver página</a>
        @if($listing->contact_phone)<a class="ny-btn-soft" href="{{ route('guide.contact', $listing) }}"><x-ui.icon name="whatsapp" /> WhatsApp</a>@endif
       </div>
      </div>
     </article>
    @endforeach
    <article class="ny-card-link">
     <div class="ny-photo"><div class="ny-photo-fallback"><span class="ny-mark ny-tone-sun"><x-ui.icon name="megaphone" /></span></div></div>
     <div class="ny-card-body">
      <p class="ny-meta">Para negocios de Loreto</p>
      <h2>¿Quieres salir en esta guía?</h2>
      <p>Tu página y tus anuncios llegan a viajeros que ya están comprando su pasaje.</p>
      <a class="ny-btn-main" href="{{ route('advertising.create') }}">Publica tu negocio</a>
     </div>
    </article>
   </section>
   @if($listings->hasPages())<div class="ny-pager">{{ $listings->links() }}</div>@endif
  @endif
 </div>
</main>
</x-public-layout>
