<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal de empresa') · NavegaYA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .company-shell { display: flex; flex-direction: row; height: 100vh; min-height: 100vh; width: 100%; overflow: hidden; }
        .company-sidebar { position: relative; flex: 0 0 16rem; width: 16rem; min-width: 16rem; max-width: 16rem; height: 100vh; overflow-y: auto; }
        .company-main { flex: 1 1 0%; min-width: 0; width: calc(100% - 16rem); height: 100vh; overflow-y: auto; }
    </style>
</head>
@php
    $isCounter = auth()->user()->roles()->where('code', 'company_counter')->exists();
    $companyName = $organization->commercial_name ?: $organization->legal_name;
    $basePort = $organization->vessels->first()?->basePort?->name ?? $organization->address ?? 'Base operativa por registrar';
@endphp
<body class="min-h-full bg-slate-50 text-slate-800 antialiased">
    <div class="company-shell min-h-screen bg-slate-50 flex flex-row">
        <aside class="company-sidebar flex h-screen min-h-screen w-64 min-w-[16rem] max-w-[16rem] flex-shrink-0 flex-col overflow-y-auto bg-[#062c21] p-4 text-white">
            <a href="{{ route('home') }}" class="mb-6 flex items-center gap-3 border-b border-white/10 pb-5">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500 text-lg">⚓</span>
                <span>
                    <strong class="block text-lg font-black">Navega<span class="text-amber-400">YA</span></strong>
                    <span class="text-[9px] font-bold uppercase tracking-[.18em] text-emerald-300">Portal del operador</span>
                </span>
            </a>

            <div class="mb-6 min-w-0 border-b border-white/10 pb-5">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Empresa conectada</span>
                <h2 class="mt-1 truncate text-lg font-bold leading-tight text-white" title="{{ $companyName }}">{{ $companyName }}</h2>
                <p class="mt-1 text-xs text-slate-300">RUC: {{ $organization->ruc ?? '---' }}</p>
                <p class="mt-2 truncate text-xs text-slate-300" title="{{ $basePort }}">📍 {{ $basePort }}</p>
            </div>

            <nav class="flex flex-1 flex-col gap-5">
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[9px] font-bold uppercase tracking-[.18em] text-emerald-400">Operación</p>
                    <a href="{{ route('company.departures.index') }}" class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition {{ request()->routeIs('company.departures.*') && !request()->routeIs('company.boarding.*') ? 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">🗓️</span><span>Salidas y despacho</span>
                    </a>
                    @unless($isCounter)
                        <a href="{{ route('company.sales.index') }}" class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition {{ request()->routeIs('company.sales.*') ? 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">💳</span><span>Ventas &amp; reportes</span>
                        </a>
                    @endunless
                    <a href="{{ route('company.counter.index') }}" class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition {{ request()->routeIs('company.counter.*') || request()->routeIs('company.boarding.*') ? 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">📱</span><span>Control de embarque</span>
                    </a>
                    <a href="{{ route('company.pos.index') }}" class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition {{ request()->routeIs('company.pos.*') ? 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">🧾</span><span>Venta en muelle</span>
                    </a>
                </div>
                @unless($isCounter)
                    <div class="space-y-1">
                        <p class="px-3 pb-1 text-[9px] font-bold uppercase tracking-[.18em] text-emerald-400">Gestión de empresa</p>
                        <a href="{{ route('company.fleet.index') }}" class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition {{ request()->routeIs('company.fleet.*') ? 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">🚤</span><span>Flota y capacidad</span>
                        </a>
                        <a href="{{ route('company.staff.index') }}" class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition {{ request()->routeIs('company.staff.*') ? 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">👥</span><span>Personal y accesos</span>
                        </a>
                    </div>
                    <div class="space-y-1">
                        <p class="px-3 pb-1 text-[9px] font-bold uppercase tracking-[.18em] text-emerald-400">Comercial</p>
                        <a href="{{ route('company.commissions.index') }}" class="relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition {{ request()->routeIs('company.commissions.*', 'company.settlements.*') ? 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10">📈</span><span>Comisiones NavegaYA</span>
                        </a>
                    </div>
                @endunless
            </nav>

            <div class="mt-auto border-t border-white/10 pt-4">
                <p class="truncate text-xs font-semibold text-white">{{ auth()->user()->name }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="w-full rounded-lg border border-white/15 px-3 py-2 text-left text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white">Cerrar sesión</button>
                </form>
            </div>
        </aside>

        <main class="company-main flex-1 overflow-y-auto p-8">
            <header class="mb-6 flex items-center justify-between border-b border-slate-200 pb-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">{{ $isCounter ? 'Terminal / Counter' : 'Administración de empresa' }}</p>
                    <h1 class="text-lg font-bold text-slate-900">@yield('page-title', 'Operación')</h1>
                </div>
            </header>

            @if(session('success'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-xs font-semibold text-emerald-800">✓ {{ session('success') }}</div>
            @endif
            @if($organization->status === 'pending')
                <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-xs leading-relaxed text-amber-900"><strong class="block font-black">⏳ Empresa pendiente de verificación</strong>Ya puedes configurar tu flota y revisar el panel. La publicación de salidas y la venta de pasajes se habilitarán cuando NavegaYA valide el RUC y los permisos operativos.</div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
