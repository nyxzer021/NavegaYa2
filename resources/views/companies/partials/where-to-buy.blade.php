@php
    $cards = $saleSites->map(fn ($site) => [
        'kind' => 'site',
        'kicker' => trans($site->kind === 'office' ? 'public.where_branch' : 'public.where_site', [], 'es'),
        'name' => $site->name,
        'address' => trim((string) $site->address),
        'place' => collect([$site->address, $site->city])->map(fn ($value) => trim((string) $value))->filter()->implode(', '),
        'hours' => trim((string) $site->hours),
        'phone' => trim((string) $site->phone),
    ])->concat($saleAgencies->map(fn ($agency) => [
        'kind' => 'agency',
        'kicker' => trans('public.where_agency', [], 'es'),
        'name' => $agency->name,
        'address' => '',
        'place' => '',
        'hours' => '',
        'phone' => trim((string) $agency->phone),
    ]));
@endphp
<div class="where-grid">
    @foreach($cards as $card)
        @php
            $whatsapp = \App\Support\PhoneNumber::peru($card['phone']);
            $phoneLabel = \App\Support\PhoneNumber::display($card['phone']) ?: $card['phone'];
        @endphp
        <article class="where-card">
            <div class="where-head">
                <span class="where-pill {{ $card['kind'] === 'agency' ? 'is-agency' : 'is-site' }}"><x-ui.icon name="store" class="where-icon" /></span>
                <div>
                    <p class="where-kicker">{{ $card['kicker'] }}</p>
                    <h3>{{ $card['name'] }}</h3>
                </div>
            </div>
            @if($card['place'] !== '')
                <p class="where-line"><x-ui.icon name="map-pin" class="where-icon" /><span>{{ $card['place'] }}</span></p>
            @endif
            @if($card['hours'] !== '')
                <p class="where-line"><x-ui.icon name="clock" class="where-icon" /><span>{{ trans('public.where_hours', [], 'es') }}: {{ $card['hours'] }}</span></p>
            @endif
            @if($phoneLabel !== '')
                <p class="where-line"><x-ui.icon name="phone" class="where-icon" /><span>{{ trans('public.where_phone', [], 'es') }}: {{ $phoneLabel }}</span></p>
            @endif
            @if($whatsapp || $card['address'] !== '')
                <div class="where-actions">
                    @if($whatsapp)
                        <a class="where-btn is-wa" target="_blank" rel="noopener" href="https://wa.me/{{ $whatsapp }}"><x-ui.icon name="whatsapp" class="where-icon" />{{ trans('public.where_whatsapp', [], 'es') }}</a>
                    @endif
                    @if($card['address'] !== '')
                        <a class="where-btn is-map" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($card['place']) }}"><x-ui.icon name="compass" class="where-icon" />{{ trans('public.where_directions', [], 'es') }}</a>
                    @endif
                </div>
            @endif
        </article>
    @endforeach
</div>
