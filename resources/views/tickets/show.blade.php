<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boleto confirmado · NavegaYA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body{margin:0;background:#f4f7f6;color:#173b33}.wrap{max-width:900px;margin:24px auto;padding:0 20px}.ticket{margin-top:15px;padding:24px;border:1px solid #dce8e3;border-radius:18px;background:#fff}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.grid span{display:block;color:#6b8179;font-size:12px}.qr{display:flex;gap:20px;align-items:center;margin-top:18px}.qr img{width:145px}.button{display:inline-block;margin-top:15px;margin-right:8px;padding:11px 15px;border-radius:10px;background:#f2b624;color:#173b33;font-weight:850;text-decoration:none}.review{margin-top:18px;padding:18px;border:1px solid #a7e6d1;border-radius:16px;background:#ecfdf5}.review p{margin:5px 0;color:#45675f;font-size:13px}.void{display:grid;gap:8px;margin-top:16px}.void input{min-height:44px;border:1px solid #dce8e3;border-radius:10px;padding:8px 12px;font-size:14px}.void button{min-height:44px;border:0;border-radius:10px;background:#b42336;color:#fff;font-weight:800}.ask{display:grid;gap:8px;margin-top:16px;padding:16px;border:1px solid #dce8e3;border-radius:16px;background:#fff}.ask p,.ask small{margin:0;font-size:14px}.ask input,.ask button{min-height:44px;border:1px solid #dce8e3;border-radius:10px;padding:8px 12px;font-size:14px}.ask button{border:0;background:#047857;color:#fff;font-weight:800}@media(max-width:640px){.grid{grid-template-columns:1fr}.qr{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<x-public-nav current="travels" />
<main class="wrap">
    <h1>Boletos confirmados</h1>
    @if(session('success'))<p class="review" role="status">{{ session('success') }}</p>@endif
    @if(session('cargo_offer'))<p class="review" role="status">{{ __('pos.cargo_ready') }} <a href="{{ session('cargo_offer') }}">{{ __('pos.cargo_link') }}</a></p>@endif
    @php
        $isAir = $reservation->isAir();
        $trip = $isAir ? $reservation->airDeparture : $reservation->departure;
        $route = $isAir ? $trip->airRoute : $trip->transportRoute;
        $organization = $route->organization;
    @endphp
    @foreach($reservation->seats as $seat)
        <article class="ticket">
            <small>{{ $isAir ? 'VIAJE AÉREO' : 'VIAJE FLUVIAL' }}</small>
            <h2>{{ $seat->passenger_name }}</h2>
            <div class="grid">
                <div><span>Ruta</span><strong>{{ $isAir ? $route->origin_city : $route->originPort->city }} → {{ $isAir ? $route->destination_city : $route->destinationPort->city }}</strong>@if($stopsLabel = \App\Support\RouteStops::label($route))<span>{{ $stopsLabel }}</span>@endif</div>
                <div><span>Salida</span><strong>{{ \App\Support\DepartureSchedule::show($trip->departure_at) }}</strong></div>
                <div><span>Asiento</span><strong>{{ $isAir ? ($seat->aircraftSeat->code ?? '—') : ($seat->seat->code ?? '—') }}</strong></div>
            </div>
            @if($seat->ticket)
            <div class="qr">
                <img src="{{ route('tickets.qr', $seat->ticket->code) }}" alt="Código QR del boleto">
                <div><strong>QR y código seguro de abordaje</strong><br>Reserva {{ $reservation->code }}<br>Boleto {{ $seat->ticket->code }}<br><a class="button" href="{{ route('tickets.pdf', $seat->ticket->code) }}">{{ __('pos.print_a4') }}</a><a class="button" href="{{ route('tickets.pdf', ['ticketCode' => $seat->ticket->code, 'papel' => '80']) }}">{{ __('pos.print_80') }}</a></div>
            </div>
            @else
            <p>Boleto pendiente de emisión. Reserva {{ $reservation->code }}</p>
            @endif
        </article>
    @endforeach
    <a class="button" href="{{ route('claims.create', ['empresa' => $organization->id, 'reserva' => $reservation->code]) }}"><x-ui.icon name="claims" class="h-4 w-4" /> {{ __('claims.book') }}</a>
    @if($refundPanel)
        <section class="ask">
            <strong>{{ __('refunds.ask') }}</strong>
            @if($refundPanel['boarded'])
                <p>{{ __('refunds.boarded') }}</p>
            @elseif($refundPanel['open'])
                <p>{{ $refundPanel['open']->explanation }}</p>
                <small>{{ __('refunds.status_'.$refundPanel['open']->status) }}</small>
            @elseif($refundPanel['quote'])
                <p>{{ $refundPanel['quote']['explanation'] }}</p>
                <form method="POST" action="{{ route('tickets.refund', $reservation->code) }}">
                    @csrf
                    <label>{{ __('refunds.ask_reason') }}<input name="reason" required minlength="5" maxlength="500"></label>
                    <button type="submit">{{ __('refunds.ask_submit') }}</button>
                </form>
            @endif
        </section>
    @endif
    @if($canVoid)
        <form class="void" method="POST" action="{{ route('company.pos.void', $reservation) }}">
            @csrf
            <label>{{ __('pos.void_title') }}<input name="reason" required minlength="3" placeholder="{{ __('pos.void_reason') }}"></label>
            <button type="submit">{{ __('pos.void_submit') }}</button>
        </form>
    @endif
    <x-company-whatsapp :company="$organization" :departure="$trip" :code="$reservation->code" class="mt-4" />
    @if($trip->status === 'completed')
        <section class="review">
            <strong>¿Cómo estuvo tu viaje con {{ $organization->commercial_name ?: $organization->legal_name }}?</strong>
            <p>Tu opinión quedará vinculada a esta reserva y ayudará a otros pasajeros.</p>
            <a class="button" href="{{ route('companies.show', $organization) }}#opiniones">Opinar sobre este viaje</a>
        </section>
    @endif
    @if($destinationAds->isNotEmpty())
        <section class="review">
            <small>COMPLETA TU VIAJE · RECOMENDACIONES PARA {{ mb_strtoupper($destination) }}</small>
            <h2>Hospedajes, restaurantes y experiencias</h2>
            <p>Promociones relacionadas con tu destino; no afectan tu reserva.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-top:14px">
                @foreach($destinationAds as $ad)
                    <a href="{{ route('discover.ad', $ad) }}" target="_blank" rel="sponsored noopener" style="overflow:hidden;border:1px solid #dce8e3;border-radius:14px;background:#fff;color:#173b33;text-decoration:none">
                        @if($ad->creative_url)<img src="{{ $ad->creative_url }}" alt="{{ $ad->business_name }}" style="width:100%;height:120px;object-fit:cover">@endif
                        <div style="padding:12px"><small>Patrocinado · {{ $ad->category }}</small><strong style="display:block;margin-top:5px">{{ $ad->title ?: $ad->business_name }}</strong><span style="display:block;margin-top:8px;font-size:12px;color:#047857">Ver recomendación →</span></div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</main>
<x-public-footer />
</body>
</html>
