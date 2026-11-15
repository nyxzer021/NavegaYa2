<x-public-layout title="{{ $listing->name }} · Guía Loreto">
@php($cover = function (?string $path): ?string { if (blank($path)) { return null; } if (str_starts_with($path, 'http') || str_starts_with($path, '/')) { return $path; } return asset('storage/'.$path); })
@php($hero = $cover($listing->banner_path ?: $listing->images->first()?->path))
@php($gallery = $listing->images->sortByDesc(fn ($image) => (bool) $image->is_cover)->values())
@php($days = ['mon' => 'Lunes', 'tue' => 'Martes', 'wed' => 'Miércoles', 'thu' => 'Jueves', 'fri' => 'Viernes', 'sat' => 'Sábado', 'sun' => 'Domingo'])
@php($hoursFor = function (array $day): string { if ($day['closed'] ?? true) { return 'Cerrado'; } return collect($day['periods'] ?? [])->map(fn ($period) => ($period['from'] ?? '').'–'.($period['to'] ?? ''))->join(' / '); })
@php($shortType = ['lodging' => 'Hospedajes', 'gastronomy' => 'Gastronomía', 'attraction' => 'Tours', 'tourist_transport' => 'Transporte turístico', 'traveler_service' => 'Servicios', 'local_business' => 'Comercios'][$listing->type] ?? 'Negocio')
<main class="ny-biz">
 <header class="ny-biz-hero">
  @if($hero)<img src="{{ $hero }}" alt="{{ $listing->name }}">@endif
  <div class="ny-shade"></div>
  <div class="ny-copy">
   <p class="ny-kicker">{{ $shortType }} · {{ $listing->city->name }}</p>
   <h1 class="ny-display">{{ $listing->name }}</h1>
   <div class="ny-plans-actions">
    @if($reviews->isNotEmpty())
     <p class="ny-stars" aria-label="{{ $ratingAverage }} de 5, {{ $reviews->count() }} opiniones">
      @for($star = 1; $star <= 5; $star++)<x-ui.icon name="star" @class(['is-on' => $star <= round($ratingAverage)]) />@endfor
      <b>{{ $ratingAverage }}</b>
     </p>
    @endif
    @if($listing->price_reference)<span class="ny-chip">Desde {{ $listing->price_reference }}</span>@endif
    @if($listing->contact_phone)<a class="ny-btn-main" href="{{ route('guide.contact', $listing) }}"><x-ui.icon name="whatsapp" /> WhatsApp</a>@endif
   </div>
  </div>
 </header>
 <div class="ny-biz-wrap">
  <div class="ny-biz-main">
   @if($listing->description)<article class="ny-biz-block"><h2>Sobre este lugar</h2><p class="ny-biz-copy ny-side-gap">{{ $listing->description }}</p></article>@endif
   <section class="ny-biz-block">
    <h2>Galería</h2>
    @if($gallery->isEmpty())
     <div class="ny-biz-empty"><span class="ny-mark ny-tone-leaf"><x-ui.icon name="gallery" /></span><h2>Todavía no hay fotografías</h2><p>Cuando el negocio publique su galería, vas a verla aquí.</p></div>
    @else
     <div class="ny-gallery">
      @foreach($gallery as $image)
       @php($publishedImageReviews = $image->reviews->where('status', 'published'))
       <button type="button" onclick="document.getElementById('photo-{{ $image->id }}').showModal()" aria-label="Abrir fotografía de {{ $listing->name }}">
        <img src="{{ $image->path }}" alt="Fotografía de {{ $listing->name }}">
        @if($publishedImageReviews->isNotEmpty())<span class="ny-gallery-note">{{ $publishedImageReviews->count() }} {{ $publishedImageReviews->count() === 1 ? 'comentario' : 'comentarios' }}</span>@endif
       </button>
       <dialog id="photo-{{ $image->id }}">
        <div class="ny-dialog">
         <figure><img src="{{ $image->path }}" alt="Fotografía ampliada de {{ $listing->name }}"></figure>
         <aside>
          <div class="ny-dialog-head">
           <div><h3>{{ $listing->name }}</h3><p class="ny-meta">Opiniones sobre esta fotografía</p></div>
           <button type="button" onclick="this.closest('dialog').close()" class="ny-btn-soft ny-dialog-close" aria-label="Cerrar"><x-ui.icon name="close" /></button>
          </div>
          <div class="ny-stack">
           @forelse($publishedImageReviews as $photoReview)
            <article class="ny-review">
             <strong>{{ $photoReview->user?->name ?? 'Viajero' }}</strong>
             <p class="ny-stars" aria-label="{{ $photoReview->rating }} de 5">@for($star = 1; $star <= 5; $star++)<x-ui.icon name="star" @class(['is-on' => $star <= $photoReview->rating]) />@endfor</p>
             <p>{{ $photoReview->comment }}</p>
            </article>
           @empty
            <p class="ny-biz-empty">Sé el primero en comentar esta fotografía.</p>
           @endforelse
          </div>
          @auth
           @if(auth()->user()->isCustomer())
            <form method="POST" action="{{ route('guide.images.reviews.store', [$listing, $image]) }}">
             @csrf
             <label>Tu valoración<select name="rating"><option value="5">5 Excelente</option><option value="4">4 Muy buena</option><option value="3">3 Buena</option><option value="2">2 Regular</option><option value="1">1 Deficiente</option></select></label>
             <label>Comentario<textarea name="comment" required minlength="3" maxlength="700" rows="3" placeholder="Qué te transmite esta fotografía"></textarea></label>
             <button class="ny-btn-main" type="submit">Publicar comentario</button>
            </form>
           @endif
          @else
           <a class="ny-btn-main" href="{{ route('login') }}">Inicia sesión para comentar</a>
          @endauth
         </aside>
        </div>
       </dialog>
      @endforeach
     </div>
    @endif
   </section>
   @if($listing->services)<article class="ny-biz-block"><h2>Servicios</h2><p class="ny-biz-copy ny-side-gap ny-pre">{{ $listing->services }}</p></article>@endif
   @if($listing->offers->isNotEmpty())
    <section class="ny-biz-block">
     <h2>Ofertas</h2>
     <div class="ny-offer-grid">
      @foreach($listing->offers as $offer)
       <article class="ny-offer">
        <div class="ny-photo">@if($offer->image_path)<img src="{{ $offer->image_path }}" alt="{{ $offer->title }}">@else<div class="ny-photo-fallback"><span class="ny-mark ny-tone-sun"><x-ui.icon name="ticket" /></span></div>@endif</div>
        <div>
         @if($offer->offer_type)<p class="ny-meta">{{ $offer->offer_type }}</p>@endif
         <h3>{{ $offer->title }}</h3>
         @if($offer->description)<p>{{ $offer->description }}</p>@endif
         @if($offer->price)<p class="ny-price">S/ {{ number_format($offer->price, 2) }}@if($offer->price_unit) {{ $offer->price_unit }}@endif</p>@endif
         @if($offer->capacity)<p class="ny-meta">Hasta {{ $offer->capacity }} personas</p>@endif
        </div>
       </article>
      @endforeach
     </div>
    </section>
   @endif
   <section class="ny-biz-block">
    <h2>Opiniones</h2>
    @if($reviews->isEmpty())
     <div class="ny-biz-empty"><span class="ny-mark ny-tone-sun"><x-ui.icon name="star" /></span><h2>Todavía no hay opiniones</h2><p>Las estrellas aparecen cuando un viajero deja una opinión aprobada.</p></div>
    @else
     <div class="ny-stack">
      @foreach($reviews as $review)
       <article class="ny-review">
        <strong>{{ $review->user?->name ?? 'Viajero NavegaYA' }}</strong>
        <p class="ny-stars" aria-label="{{ $review->rating }} de 5">@for($star = 1; $star <= 5; $star++)<x-ui.icon name="star" @class(['is-on' => $star <= $review->rating]) />@endfor</p>
        <p>{{ $review->comment }}</p>
       </article>
      @endforeach
     </div>
    @endif
    @auth
     @if(auth()->user()->isCustomer() && $listing->organization_id)
      <form method="POST" action="{{ route('guide.reviews.store', $listing) }}" class="ny-review-form">
       @csrf
       <h3>Comparte tu experiencia</h3>
       <select name="rating"><option value="5">5 Excelente</option><option value="4">4 Muy bueno</option><option value="3">3 Bueno</option><option value="2">2 Regular</option><option value="1">1 Deficiente</option></select>
       <textarea name="comment" required minlength="10" rows="3" placeholder="Cuenta qué fue lo que más te gustó"></textarea>
       <button class="ny-btn-main" type="submit">Enviar valoración</button>
      </form>
     @endif
    @endauth
   </section>
  </div>
  <aside class="ny-biz-side">
   <div class="ny-biz-block">
    <p class="ny-kicker">Contacto</p>
    <h2 class="ny-side-gap">{{ $listing->name }}</h2>
    @if($listing->opening_schedule)
     <div class="ny-side-gap">
      @foreach($days as $key => $label)
       @php($day = $listing->opening_schedule[$key] ?? ['closed' => true, 'periods' => []])
       <div class="ny-day"><span>{{ $label }}</span><b>{{ $hoursFor($day) }}</b></div>
      @endforeach
     </div>
    @elseif($listing->opening_hours)
     <p class="ny-biz-copy ny-side-gap">{{ $listing->opening_hours }}</p>
    @endif
    <p class="ny-biz-copy ny-side-gap"><x-ui.icon name="map-pin" class="inline h-4 w-4" /> {{ $listing->address ?: $listing->city->name }}</p>
    @if($listing->category)<p class="ny-meta ny-side-gap">{{ $listing->category }}</p>@endif
    @if($listing->contact_phone)<a class="ny-btn-main ny-side-gap" href="{{ route('guide.contact', $listing) }}"><x-ui.icon name="whatsapp" /> WhatsApp</a>@endif
    @if($listing->website_url)<a class="ny-btn-soft ny-side-gap" href="{{ $listing->website_url }}" target="_blank" rel="noopener">Visitar sitio web</a>@endif
   </div>
  </aside>
 </div>
</main>
</x-public-layout>
