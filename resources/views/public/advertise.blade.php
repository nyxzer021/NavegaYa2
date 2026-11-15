<x-public-layout title="Planes para promocionar tu negocio · NavegaYA">
<main class="ny-plans">
 <header class="ny-plans-hero">
  <img src="/images/branding/banner-guia-loreto.webp" alt="Viajeros en la Amazonía">
  <div class="ny-shade"></div>
  <div class="ny-copy">
   <p class="ny-kicker">Marketplace turístico de Loreto</p>
   <h1 class="ny-display">Haz visible tu negocio ante viajeros que exploran Loreto</h1>
   <p>Publica tu hotel, lodge, restaurante, tour o servicio ante personas que ya están organizando su viaje por la Amazonía.</p>
   <div class="ny-plans-actions">
    <a class="ny-btn-main" href="#planes">Ver planes</a>
    <a class="ny-btn-soft" href="{{ route('guide.index') }}">Ver la Guía Loreto</a>
   </div>
  </div>
 </header>
 <div class="ny-plans-body">
  @if(session('success_lead'))<p class="ny-guide-empty" role="status">{{ session('success_lead') }}</p>@endif
  <section aria-label="Cómo publicar">
   <p class="ny-kicker">Tres pasos</p>
   <div class="ny-steps">
    <article class="ny-step"><span class="ny-mark ny-tone-river"><x-ui.icon name="user" /></span><div><h2>Regístrate</h2><p>Crea la cuenta de tu negocio con tus datos y elige el tipo de servicio.</p></div></article>
    <article class="ny-step"><span class="ny-mark ny-tone-sun"><x-ui.icon name="online_payments" /></span><div><h2>Paga tu plan</h2><p>La vigencia empieza cuando el pago queda activo. Sin plan vigente, la página no se publica.</p></div></article>
    <article class="ny-step"><span class="ny-mark ny-tone-leaf"><x-ui.icon name="megaphone" /></span><div><h2>Tu página y tus anuncios salen en NavegaYA</h2><p>Después de la revisión, los viajeros te encuentran en la Guía Loreto y en las campañas de tu plan.</p></div></article>
   </div>
  </section>
  <section id="planes" class="ny-section">
   <p class="ny-kicker">Planes</p>
   <h2 class="ny-display ny-section-title">Elige cómo quieres crecer</h2>
   @if($plans->isEmpty())
    <div class="ny-guide-empty"><span class="ny-mark ny-tone-sun"><x-ui.icon name="megaphone" /></span><h2>Pronto publicaremos los planes</h2><p>Mientras tanto puedes mirar la Guía Loreto.</p><a class="ny-btn-main" href="{{ route('guide.index') }}">Ver la guía</a></div>
   @else
    @php($featuredPlan = (int) floor(($plans->count() - 1) / 2))
    <div class="ny-plans-grid">
     @foreach($plans as $index => $plan)
      <article class="ny-plan {{ $index === $featuredPlan ? 'is-mid' : '' }}">
       @if($index === $featuredPlan)<span class="ny-plan-flag">Recomendado</span>@endif
       <h2>{{ $plan->name }}</h2>
       @if($plan->description)<p class="ny-meta">{{ $plan->description }}</p>@endif
       <p><strong>S/ {{ number_format($plan->monthly_price, 0) }}</strong> <span class="ny-meta">/ mes</span></p>
       @if(! empty($plan->benefits['features']))
        <ul class="ny-plan-list">
         @foreach($plan->benefits['features'] as $feature)<li><x-ui.icon name="check" class="h-4 w-4 shrink-0" /> {{ $feature }}</li>@endforeach
        </ul>
       @endif
       <a class="ny-btn-main" href="{{ route('marketplace.register', ['plan' => $plan->code]) }}">Elegir {{ $plan->name }}</a>
      </article>
     @endforeach
    </div>
   @endif
  </section>
  <section class="ny-plans-help">
   <h2>¿Necesitas ayuda para elegir?</h2>
   <p class="ny-meta" style="margin-top:8px">Te ayudamos a preparar la primera ficha.</p>
   @if($supportNumber)
    <a class="ny-btn-main" href="https://wa.me/{{ $supportNumber }}?text={{ urlencode('Hola NavegaYA, deseo información sobre los planes del marketplace turístico.') }}">Atención comercial inmediata por WhatsApp</a>
   @else
    <p class="ny-btn-main">Atención comercial inmediata por WhatsApp</p>
   @endif
  </section>
 </div>
</main>
</x-public-layout>
