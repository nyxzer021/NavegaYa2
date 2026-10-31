@props(['icon' => '🌿', 'title' => 'Aún no hay información', 'description' => null])
<section {{ $attributes->merge(['class' => 'ny-empty']) }}>
    <span class="ny-empty__icon" aria-hidden="true">{{ $icon }}</span>
    <strong class="ny-empty__title">{{ $title }}</strong>
    @if($description)<p class="ny-empty__description">{{ $description }}</p>@endif
    @if(isset($action))<div class="ny-empty__action">{{ $action }}</div>@endif
</section>
