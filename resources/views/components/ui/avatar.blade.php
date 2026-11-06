@props(['name', 'src' => null])
@php
    $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
    $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
@endphp
<span {{ $attributes->merge(['class' => 'ny-ui-avatar']) }}>
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}">
    @else
        {{ $initials }}
    @endif
</span>
