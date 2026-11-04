@props(['title', 'text' => null])
<div {{ $attributes->merge(['class' => 'ny-ui-empty']) }}>
    <strong>{{ $title }}</strong>
    @if($text)<p>{{ $text }}</p>@endif
    {{ $slot }}
</div>
