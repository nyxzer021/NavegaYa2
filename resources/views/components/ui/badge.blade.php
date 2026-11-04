@props(['tone' => 'neutral'])
<span {{ $attributes->merge(['class' => 'ny-ui-badge is-'.$tone]) }}>{{ $slot }}</span>
