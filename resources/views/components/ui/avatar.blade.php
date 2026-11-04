@props(['name', 'src' => null])
<span {{ $attributes->merge(['class' => 'ny-ui-avatar']) }}>
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}">
    @else
        {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
    @endif
</span>
