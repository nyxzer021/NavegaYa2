@props(['company', 'departure' => null, 'label' => 'Consultar por WhatsApp'])
@php
    $name = $company?->commercial_name ?: $company?->legal_name;
    $message = $company?->whatsapp_message ?: "Hola, quiero información sobre las salidas de {$name}";
    if ($departure) {
        $route = $departure->transportRoute ?? $departure->airRoute;
        $origin = $route?->originPort?->city ?? $route?->origin_city;
        $destination = $route?->destinationPort?->city ?? $route?->destination_city;
        $message .= ". Ruta {$origin} - {$destination}, ".$departure->departure_at?->format('d/m/Y H:i').'.';
    }
    $url = $company?->show_whatsapp ? \App\Support\PhoneNumber::whatsappUrl($company?->whatsapp, $message) : null;
@endphp
@if($url)
<a href="{{$url}}" target="_blank" rel="noopener" {{ $attributes->merge(['class'=>'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5 text-xs font-black text-emerald-950 shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2']) }}>💬 {{$label}}</a>
@endif
