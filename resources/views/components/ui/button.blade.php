@props(['href' => null, 'variant' => 'secondary', 'size' => 'md'])
@php
    $class = 'ny-ui-btn is-'.$variant.($size === 'sm' ? ' is-sm' : '').($size === 'lg' ? ' is-lg' : '');
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $class]) }}>{{ $slot }}</button>
@endif
