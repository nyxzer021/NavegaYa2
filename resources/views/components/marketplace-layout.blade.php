<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal de mi negocio · NavegaYA' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased">
    <header class="sticky top-0 z-40 border-b border-emerald-950/10 bg-[#052f25] text-white shadow-sm">
        <div class="mx-auto flex h-16 max-w-[1500px] items-center justify-between px-5">
            <a href="{{ route('marketplace.dashboard') }}" class="flex items-center gap-3 font-black">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-400 text-xl text-emerald-950">🌿</span>
                <span>Navega<span class="text-amber-400">YA</span><small class="block text-[8px] uppercase tracking-[.2em] text-emerald-200">Portal del anunciante</small></span>
            </a>
            <div class="flex items-center gap-3">
                <span class="hidden text-xs text-emerald-100/70 sm:block">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="rounded-xl border border-white/15 bg-white/10 px-4 py-2 text-xs font-black transition hover:bg-white/20">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>
    {{ $slot }}
    <x-tracking />
</body>
</html>
