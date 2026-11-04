@props(['title', 'subtitle' => null])
<header {{ $attributes->merge(['class' => 'ny-ui-page-header']) }}>
    <div>
        <h2>{{ $title }}</h2>
        @if($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="ny-ui-page-actions">{{ $actions }}</div>@endisset
</header>
