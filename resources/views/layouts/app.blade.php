<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    @php($pageTitle=$title??config('app.name','NavegaYA').' · Transporte Fluvial y Aéreo en Loreto')
    @php($defaultDescription=app()->getLocale()==='en'?'Compare verified river and air services, check official schedules, buy tickets securely and discover trusted destinations across the Peruvian Amazon.':'Compara servicios fluviales y aéreos verificados, consulta horarios oficiales, compra pasajes de forma segura y descubre destinos de la Amazonía peruana.')
    @php($pageDescription=\Illuminate\Support\Str::limit(strip_tags($description??$defaultDescription),160,''))
    @php($shareImage=$image?:asset('images/branding/banner-home-viajeros-amazonia.webp'))
    <meta name="description" content="{{ $pageDescription }}">
    <meta property="og:type" content="website"><meta property="og:site_name" content="NavegaYA"><meta property="og:title" content="{{ $pageTitle }}"><meta property="og:description" content="{{ $pageDescription }}"><meta property="og:image" content="{{ $shareImage }}"><meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $pageTitle }}"><meta name="twitter:description" content="{{ $pageDescription }}"><meta name="twitter:image" content="{{ $shareImage }}">
    <title>{{ $pageTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}@stack('styles')
    <style>html,body{background:var(--ny-background)!important}body{font-family:'Plus Jakarta Sans',sans-serif!important}[x-cloak]{display:none!important}</style>
</head>
<body class="ny-public-portal bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex flex-col">
    @include('layouts.partials.topbar')
    @include('layouts.partials.header', ['current' => $current ?? ''])
    <main class="w-full flex-grow">{{ $slot ?? '' }}@yield('content')</main>
    @include('layouts.partials.footer')
    @php($floatingWhatsApp=\App\Support\PhoneNumber::peru(\App\Models\SystemSetting::value('whatsapp_number')))
    @php($showFloating=\App\Models\SystemSetting::value('show_whatsapp_floating','1')==='1')
    @php($floatingMessage=\App\Models\SystemSetting::value('whatsapp_message','Hola, necesito ayuda para planificar mi viaje con NavegaYA.'))
    @if($showFloating && $floatingWhatsApp)<a href="https://wa.me/{{$floatingWhatsApp}}?text={{urlencode($floatingMessage)}}" target="_blank" rel="noopener" class="fixed bottom-5 right-5 z-[9000] grid h-14 w-14 place-items-center rounded-full bg-[#25D366] text-white shadow-2xl ring-4 ring-white transition hover:-translate-y-1 focus:outline-none focus:ring-amber-300" aria-label="Abrir ayuda por WhatsApp"><svg class="h-7 w-7" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 3A13 13 0 0 0 5 22.9L3.3 29l6.3-1.7A13 13 0 1 0 16 3Zm0 23.8a10.8 10.8 0 0 1-5.5-1.5l-.4-.2-3.9 1 1-3.8-.2-.4A10.8 10.8 0 1 1 16 26.8Zm5.9-8.1c-.3-.2-1.9-.9-2.2-1-.3-.1-.5-.2-.7.1l-1 1.3c-.2.2-.4.2-.7.1-2.1-.9-3.5-2.4-4.5-4.3-.2-.3 0-.5.1-.7l.8-1.1c.1-.2.1-.4 0-.6l-1-2.4c-.3-.6-.5-.6-.7-.6h-.6c-.2 0-.6.1-.9.4-.3.3-1.1 1.1-1.1 2.7s1.2 3.1 1.3 3.4c.2.2 2.3 3.5 5.6 4.9.8.3 1.4.5 1.9.7.8.2 1.5.2 2 .1.6-.1 1.9-.8 2.2-1.5.3-.8.3-1.4.2-1.6-.1-.1-.3-.2-.7-.4Z"/></svg></a>@endif
    @stack('scripts')
</body>
</html>

