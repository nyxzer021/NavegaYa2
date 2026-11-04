@props(['label', 'value', 'hint' => null])
<article {{ $attributes->merge(['class' => 'ny-ui-kpi']) }}>
    <span>{{ $label }}</span>
    <strong>{{ $value }}</strong>
    @if($hint)<small>{{ $hint }}</small>@endif
</article>
