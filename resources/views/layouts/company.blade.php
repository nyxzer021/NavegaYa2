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
        [x-cloak] { display: none !important; }
        .company-shell { display: flex; flex-direction: row; height: 100vh; min-height: 100vh; width: 100%; overflow: hidden; }
        .company-sidebar { position: relative; flex: 0 0 16rem; width: 16rem; min-width: 16rem; max-width: 16rem; height: 100vh; overflow-y: auto; }
        .company-main { flex: 1 1 0%; min-width: 0; width: calc(100% - 16rem); height: 100vh; overflow-y: auto; }
        .company-menu-toggle { display: none; align-items: center; justify-content: center; min-height: 44px; min-width: 44px; border: 0; border-radius: 12px; background: #062c21; color: #fff; font-weight: 800; }
        @media (max-width: 1023px) {
            .company-menu-toggle { display: inline-flex; }
            .company-shell { display: block; height: auto; min-height: 100vh; overflow: visible; }
            .company-sidebar { position: fixed; z-index: 70; top: 0; left: 0; width: min(18rem, 88vw); min-width: 0; max-width: none; height: 100vh; transform: translateX(-105%); transition: transform .2s ease; }
            .company-sidebar.is-open { transform: none; box-shadow: 0 16px 40px #062c2155; }
            .company-sidebar nav { flex-direction: column; overflow: visible; }
            .company-main { width: 100%; height: auto; min-height: 100vh; overflow: auto; padding: 1rem; }
            .company-main table { display: block; max-width: 100%; overflow-x: auto; }
        }
    </style>
</head>
@php
    $isCounter = auth()->user()->roles()->where('code', 'company_counter')->exists();
    $companyName = $organization->commercial_name ?: $organization->legal_name;
    $basePort = $organization->vessels->first()?->basePort?->name ?? $organization->address ?? 'Base operativa por registrar';
    $hasRiver = in_array($organization->modality ?: 'fluvial', ['fluvial', 'mixto'], true);
    $hasAir = in_array($organization->modality ?: 'fluvial', ['aereo', 'mixto'], true);
    $isOperational = $organization->status === 'active';
@endphp
<body class="ny-company-portal min-h-full bg-slate-50 text-slate-800 antialiased">
    <div class="company-shell min-h-screen bg-slate-50 flex flex-row">
        <aside id="company-sidebar" class="company-sidebar flex h-screen min-h-screen w-64 min-w-[16rem] max-w-[16rem] flex-shrink-0 flex-col overflow-y-auto bg-[#062c21] p-4 text-white">
            <a href="{{ route('home') }}" class="mb-6 flex items-center gap-3 border-b border-white/10 pb-5">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500 text-emerald-950"><x-nav-icon name="boat" class="h-5 w-5" /></span>
                <span>
                    <strong class="block text-lg font-black">Navega<span class="text-amber-400">YA</span></strong>
                    <span class="text-xs font-semibold text-emerald-300">Portal del operador</span>
                </span>
            </a>

            <div class="mb-6 min-w-0 border-b border-white/10 pb-5">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Empresa conectada</span>
                <h2 class="mt-1 truncate text-lg font-bold leading-tight text-white" title="{{ $companyName }}">{{ $companyName }}</h2>
                <p class="mt-1 text-sm text-slate-300">RUC: {{ $organization->ruc ?? '---' }}</p>
                <p class="mt-2 truncate text-sm text-slate-300" title="{{ $basePort }}">{{ $basePort }}</p>
            </div>

            @php
                $menuLink = 'relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
                $menuOn = 'bg-white/10 text-white before:absolute before:-left-4 before:h-6 before:w-1 before:rounded-r-full before:bg-amber-400';
                $menuOff = 'text-slate-300 hover:bg-white/10 hover:text-white';
            @endphp
            <nav class="flex flex-1 flex-col gap-5" data-menu-lead="{{ $isOperational || $isCounter ? 'operacion' : 'configuracion' }}">
                <div class="space-y-1 {{ $isOperational || $isCounter ? 'order-1' : 'order-2' }}">
                    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-emerald-400">Operación</p>
                    @unless($isCounter)<a href="{{ route('company.dashboard') }}" class="{{ $menuLink }} {{ request()->routeIs('company.dashboard') ? $menuOn : $menuOff }}"><span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="dashboard" class="h-4 w-4" /></span><span>Resumen</span></a>@endunless
                    @if($isOperational)
                    <a href="{{ route('company.departures.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.departures.*') && !request()->routeIs('company.boarding.*') ? $menuOn : $menuOff }}">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="calendar" class="h-4 w-4" /></span><span>Salidas y despacho</span>
                    </a>
                    @unless($isCounter)
                        <a href="{{ route('company.sales.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.sales.*') ? $menuOn : $menuOff }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="ticket" class="h-4 w-4" /></span><span>Boletos vendidos</span>
                        </a>
                    @endunless
                    <a href="{{ route('company.counter.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.counter.*') || request()->routeIs('company.boarding.*') ? $menuOn : $menuOff }}">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="seats" class="h-4 w-4" /></span><span>Control de embarque</span>
                    </a>
                    <a href="{{ route('company.pos.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.pos.*') ? $menuOn : $menuOff }}">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="payment" class="h-4 w-4" /></span><span>Venta en muelle</span>
                    </a>
                    @endif
                </div>
                @unless($isCounter)
                    <div class="space-y-1 {{ $isOperational ? 'order-2' : 'order-1' }}">
                        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-emerald-400">{{ $isOperational ? 'Flota y personal' : 'Configuración inicial' }}</p>
                        <a href="{{ route('company.fleet.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.fleet.*', 'company.vessels.*', 'company.aircraft.*') ? $menuOn : $menuOff }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="{{ $hasAir && !$hasRiver ? 'plane' : 'boat' }}" class="h-4 w-4" /></span><span>Flota y capacidad</span>
                        </a>
                        <a href="{{ route('company.staff.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.staff.*') ? $menuOn : $menuOff }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="users" class="h-4 w-4" /></span><span>Personal y accesos</span>
                        </a>
                    </div>
                    <div class="order-3 space-y-1">
                        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-emerald-400">Comercial</p>
                        @if($isOperational)
                        <a href="{{ route('company.commissions.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.commissions.*', 'company.settlements.*') ? $menuOn : $menuOff }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="chart" class="h-4 w-4" /></span><span>Comisión del convenio</span>
                        </a>
                        <a href="{{ route('company.advertising.index') }}" class="{{ $menuLink }} {{ request()->routeIs('company.advertising.*') ? $menuOn : $menuOff }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="megaphone" class="h-4 w-4" /></span><span>Publicidad y campañas</span>
                        </a>
                        @endif
                        <a href="{{ route('support.index') }}" class="{{ $menuLink }} {{ request()->routeIs('support.*') ? $menuOn : $menuOff }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="shield" class="h-4 w-4" /></span><span>Soporte e incidencias</span>
                        </a>
                        <a href="{{ route('messages.index') }}" class="{{ $menuLink }} {{ request()->routeIs('messages.*') ? $menuOn : $menuOff }}">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10"><x-nav-icon name="mail" class="h-4 w-4" /></span><span>Mensajes comerciales</span>
                        </a>
                    </div>
                @endunless
            </nav>

        </aside>

        <main class="company-main flex-1 overflow-y-auto p-8">
            <header class="mb-6 flex items-center justify-between gap-3 border-b border-slate-200 pb-4">
                <button type="button" class="company-menu-toggle" aria-controls="company-sidebar" aria-expanded="false">Menú</button>
                <div>
                    <p class="text-xs font-semibold text-emerald-800">{{ $isCounter ? 'Terminal / Counter' : 'Administración de empresa' }}</p>
                    <h1 class="text-lg font-bold text-slate-900">@yield('page-title', 'Operación')</h1>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <strong class="block max-w-52 truncate text-xs text-slate-900">{{ auth()->user()->name }}</strong>
                        <span class="text-xs text-slate-500">{{ $isCounter ? 'Personal Counter' : 'Administrador de empresa' }}</span>
                    </div>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-50 font-black text-emerald-800">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">Cerrar sesión</button>
                    </form>
                </div>
            </header>

            @if(session('success'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
            @endif
            @if($organization->status === 'pending')
                <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm leading-relaxed text-amber-900"><strong class="block font-black">Empresa pendiente de verificación</strong>Ya puedes configurar tu flota y revisar el panel. La publicación de salidas y la venta de pasajes se habilitarán cuando NavegaYA valide el RUC y los permisos operativos.</div>
            @endif

            @yield('content')
        </main>
    </div>
    <script>
        (() => {
            const button = document.querySelector('.company-menu-toggle');
            const side = document.getElementById('company-sidebar');
            if (!button || !side) return;
            button.addEventListener('click', () => {
                const open = side.classList.toggle('is-open');
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            side.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
                side.classList.remove('is-open');
                button.setAttribute('aria-expanded', 'false');
            }));
        })();
    </script>
</body>
</html>
