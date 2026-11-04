@props(['message'])
<div {{ $attributes->merge(['class' => 'ny-ui-toast', 'role' => 'status']) }}>{{ $message }}</div>
