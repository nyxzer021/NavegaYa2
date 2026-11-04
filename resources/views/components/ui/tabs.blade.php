@props(['tabs' => []])
<nav {{ $attributes->merge(['class' => 'ny-ui-tabs']) }}>
    @foreach($tabs as $tab)
        <a href="{{ $tab['href'] }}" @class(['is-on' => ! empty($tab['active'])])>{{ $tab['label'] }}</a>
    @endforeach
</nav>
