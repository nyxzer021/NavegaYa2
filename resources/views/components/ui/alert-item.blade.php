@props(['tone' => 'warning', 'title'])
<div {{ $attributes->merge(['class' => 'ny-ui-alert is-'.$tone]) }}>
    <strong>{{ $title }}</strong>
    <p>{{ $slot }}</p>
</div>
