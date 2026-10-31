@props(['company', 'departure' => null])
@php
    $number = $company->show_public_whatsapp ? \App\Support\PhoneNumber::peru($company->whatsapp ?: $company->phone) : null;
    $routeLabel = $departure?->transportRoute ? $departure->transportRoute->originPort->city.' → '.$departure->transportRoute->destinationPort->city : null;
    $schedule = $departure?->departure_at?->format('d/m/Y H:i');
    $message = $company->whatsapp_message ?: 'Hola, deseo información de '.($company->commercial_name ?: $company->legal_name).'.';
    if ($routeLabel) $message .= ' Ruta: '.$routeLabel.($schedule ? ' · '.$schedule : '').'.';
@endphp
@if($number)
<a href="https://wa.me/{{$number}}?text={{urlencode($message)}}" target="_blank" rel="noopener" {{$attributes->merge(['class'=>'inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 text-xs font-bold text-white shadow-sm transition hover:brightness-95'])}}>💬 WhatsApp boletería</a>
@endif
