@props(['type'])
@php($element = \App\Support\VesselLayout::ELEMENTS[$type] ?? null)
@if($element)
    <span {{ $attributes->merge(['class' => 'ny-deco']) }} title="{{ $element['label'] }}" role="img" aria-label="{{ $element['label'] }}"><x-ui.icon :name="$element['icon']" /></span>
@else
    <span class="ny-aisle"></span>
@endif
