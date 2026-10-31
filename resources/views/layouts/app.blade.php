<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    @php($pageTitle=$title??config('app.name','NavegaYA').' · Transporte Fluvial y Aéreo en Loreto')
    @php($pageDescription=\Illuminate\Support\Str::limit(strip_tags($description??'Compra pasajes fluviales y aéreos, consulta rutas oficiales y descubre empresas verificadas para viajar por Loreto.'),160,''))
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
    <x-public-i18n />@stack('scripts')
</body>
</html>

