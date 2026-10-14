<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? 'Pasajes fluviales y aéreos en Loreto.' }}">
    <title>{{ $title ?? config('app.name', 'NavegaYA').' - Transporte Fluvial y Aéreo en Loreto' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}@stack('styles')
    <style>html,body{background:#f8fafc!important}body{font-family:'Plus Jakarta Sans',sans-serif!important}[x-cloak]{display:none!important}</style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex flex-col">
    @include('layouts.partials.topbar')
    @include('layouts.partials.header', ['current' => $current ?? ''])
    <main class="w-full flex-grow">{{ $slot ?? '' }}@yield('content')</main>
    @include('layouts.partials.footer')
    <x-public-i18n />@stack('scripts')
</body>
</html>

