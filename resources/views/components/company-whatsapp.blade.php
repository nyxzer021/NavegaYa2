@props(['company', 'departure' => null, 'code' => null, 'compact' => false, 'secondary' => false])
@php
    $number = $company->publicWhatsapp();
    $routeLabel = $departure?->transportRoute
        ? $departure->transportRoute->originPort->city.' → '.$departure->transportRoute->destinationPort->city
        : ($departure?->airRoute ? $departure->airRoute->origin_city.' → '.$departure->airRoute->destination_city : null);
    $schedule = $departure?->departure_at?->format('d/m/Y H:i');
    $companyName = $company->commercial_name ?: $company->legal_name;
    $message = $company->whatsapp_message ?: ($code
        ? 'Hola, acabo de comprar un pasaje con '.$companyName.'.'
        : 'Hola, deseo información de '.$companyName.'.');
    if ($routeLabel) $message .= ' Ruta: '.$routeLabel.($schedule ? ' · '.$schedule : '').'.';
    if (! empty($code)) $message .= ' Reserva: '.$code.'.';
    if ($notice = $company->whatsappNotice()) $message .= ' '.$notice;
    $icon = $attributes->has('icon');
    $classes = $icon
        ? 'ny-trip-icon'
        : ($secondary
        ? 'ny-wa-secondary inline-flex h-[34px] shrink-0 items-center justify-center gap-1.5 rounded-lg border border-[#d5e0db] bg-white px-3 text-xs font-bold text-[#064E3B]'
        : ($compact
        ? 'inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-[#25D366] px-3 text-xs font-bold text-white shadow-sm transition hover:brightness-95'
        : 'inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 text-xs font-bold text-white shadow-sm transition hover:brightness-95'));
@endphp
@if($number)
<a href="https://wa.me/{{$number}}?text={{urlencode($message)}}" target="_blank" rel="noopener" @if($icon) title="{{ __('public.card_whatsapp') }}" aria-label="{{ __('public.card_whatsapp') }}" @endif {{ $attributes->except('icon')->merge(['class' => $classes]) }}>@if($icon)<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.7-1.2A9 9 0 1 0 12 3Zm4.7 12.7c-.2.6-1.2 1.1-1.7 1.1-.4.1-.9.2-3-.6-2.5-1-4.1-3.6-4.2-3.8-.2-.2-1.3-1.7-1.3-3.2s.8-2.3 1.1-2.6c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5.2.6.8 2 .8 2.1.1.2 0 .3-.2.5l-.4.5c-.1.2-.3.3-.1.6.2.3.7 1.2 1.6 1.9 1.1.9 2 1.2 2.3 1.3.3.1.4.1.6-.1l.5-.6c.2-.2.3-.2.6-.1.2.1 1.6.8 1.9.9.3.2.4.2.5.3.1.2.1.8-.1 1.4Z"/></svg>@else{{ $compact ? 'WhatsApp' : 'WhatsApp boletería' }}@endif</a>
@endif
