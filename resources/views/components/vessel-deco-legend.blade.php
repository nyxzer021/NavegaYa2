@props(['plan'])
@php($present = collect($plan['grid'])->flatten(1)->pluck('type')->unique()->intersect(array_keys(\App\Support\VesselLayout::ELEMENTS))->values())
@if($present->isNotEmpty())
    <p class="ny-deco-legend">
        @foreach($present as $type)
            <span><x-vessel-deco :type="$type" />{{ \App\Support\VesselLayout::ELEMENTS[$type]['label'] }}</span>
        @endforeach
    </p>
@endif
