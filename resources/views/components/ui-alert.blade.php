@props(['type' => 'success'])
<div {{ $attributes->merge(['class' => 'ny-alert ny-alert--'.$type]) }} role="alert">{{ $slot }}</div>
