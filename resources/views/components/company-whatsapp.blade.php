@props(['company', 'departure' => null, 'code' => null, 'compact' => false])
@php
    $number = $company->show_public_whatsapp ? \App\Support\PhoneNumber::peru($company->whatsapp ?: $company->phone) : null;
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
    $classes = $compact
        ? 'inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-[#25D366] px-3 text-xs font-bold text-white shadow-sm transition hover:brightness-95'
        : 'inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 text-xs font-bold text-white shadow-sm transition hover:brightness-95';
@endphp
@if($number)
<a href="https://wa.me/{{$number}}?text={{urlencode($message)}}" target="_blank" rel="noopener" {{$attributes->merge(['class'=>$classes])}}>{{$compact ? 'WhatsApp' : '💬 WhatsApp boletería'}}</a>
@endif
