<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
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
        .company-shell { display: flex; flex-direction: row; height: 100vh; min-height: 100vh; width: 100%; overflow: hidden; background: #F7F9F8; }
        .company-sidebar { position: relative; flex: 0 0 16rem; width: 16rem; min-width: 16rem; max-width: 16rem; height: 100vh; overflow-y: auto; background: #064E3B; color: #fff; }
        .company-main { flex: 1 1 0%; min-width: 0; width: calc(100% - 16rem); height: 100vh; overflow-y: auto; background: #F7F9F8; color: #10231D; }
        .company-menu-toggle { display: none; align-items: center; justify-content: center; min-height: 44px; min-width: 44px; border: 1px solid #e1e8e4; border-radius: 8px; background: #fff; color: #064E3B; }
        .company-nav-link { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .65rem; min-height: 40px; border-radius: 8px; padding: .35rem .7rem; color: #d7ebe3; font-size: 14px; font-weight: 600; line-height: 1.2; text-decoration: none; }
        .company-nav-link span.company-nav-label { min-width: 0; }
        .company-nav-link.is-on { background: rgba(255,255,255,.12); color: #fff; }
        .company-nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .company-nav-link:focus-visible { outline: 2px solid #F4B820; outline-offset: 2px; }
        .company-badge { margin-left: auto; border-radius: 999px; background: #F4B820; color: #10231D; padding: .1rem .4rem; font-size: 11px; font-weight: 700; line-height: 1.3; }
        .company-badge.is-text { flex: 1 0 100%; margin-left: 1.55rem; max-width: none; white-space: normal; text-align: left; }
        .company-user { border-top: 1px solid rgba(255,255,255,.12); padding: .75rem; }
        .company-user button { display: flex; width: 100%; align-items: center; gap: .65rem; border: 0; background: transparent; color: #fff; text-align: left; }
        .company-user menu { position: absolute; right: .75rem; bottom: 4.5rem; z-index: 30; width: 13rem; margin: 0; border: 1px solid #e1e8e4; border-radius: 12px; background: #fff; padding: .35rem; list-style: none; }
        .company-user menu a, .company-user menu button { display: flex; width: 100%; min-height: 36px; align-items: center; border: 0; border-radius: 8px; background: transparent; padding: 0 .7rem; color: #10231D; font-size: 14px; font-weight: 600; text-decoration: none; }
        .company-user menu a:hover, .company-user menu button:hover { background: #F7F9F8; }
        @media (max-width: 1023px) {
            .company-menu-toggle { display: inline-flex; }
            .company-shell { display: block; height: auto; min-height: 100vh; overflow: visible; }
            .company-sidebar { position: fixed; z-index: 70; top: 0; left: 0; width: min(18rem, 88vw); min-width: 0; max-width: none; height: 100vh; transform: translateX(-105%); transition: transform .2s ease; }
            .company-sidebar.is-open { transform: none; box-shadow: 0 16px 40px #064E3B55; }
            .company-main { width: 100%; height: auto; min-height: 100vh; overflow: auto; padding: 1rem; }
            .company-nav-link, .company-user button, .company-user menu a, .company-user menu button { min-height: 44px; }
        }
    </style>
</head>
@php
    $portal = new \App\Support\CompanyPortalMenu($organization, auth()->user());
    $companyName = $organization->commercial_name ?: $organization->legal_name;
    $sales = $portal->salesState();
    $pageTitle = trim($__env->yieldContent('page-title'));
    if ($pageTitle === '') {
        $pageTitle = $portal->currentTitle() ?: __('company_portal.menu.home');
    }
@endphp
<body class="ny-company-portal min-h-full antialiased">
    <div class="company-shell">
        <aside id="company-sidebar" class="company-sidebar flex flex-col" data-company-shell>
            <a href="{{ route('home') }}" class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#F4B820] text-[#10231D]"><x-ui.icon name="fleet" class="h-5 w-5" /></span>
                <span>
                    <strong class="block text-base font-extrabold">Navega<span class="text-ny-action">YA</span></strong>
                    <span class="text-xs font-semibold text-white/70">{{ __('company_portal.portal') }}</span>
                </span>
            </a>

            <div class="border-b border-white/10 px-4 py-4">
                <div class="flex items-center gap-3">
                    <x-ui.avatar :name="$companyName" :src="$organization->logo_path" class="h-10 w-10" />
                    <div class="min-w-0">
                        <strong class="block truncate text-sm font-bold" title="{{ $companyName }}">{{ $companyName }}</strong>
                        <span class="block truncate text-xs text-white/70">{{ $portal->modalityLabel() }} · {{ $portal->baseLabel() }}</span>
                    </div>
                </div>
                <p class="mt-3 flex items-center gap-2 text-xs font-semibold">
                    <i class="h-2 w-2 rounded-full {{ $sales['tone'] === 'green' ? 'bg-[#34d399]' : ($sales['tone'] === 'amber' ? 'bg-[#F4B820]' : 'bg-[#fb7185]') }}"></i>
                    {{ $sales['label'] }}
                </p>
            </div>

            <nav class="flex flex-1 flex-col gap-4 px-3 py-4" data-menu-lead="{{ $portal->lead() }}">
                @foreach($portal->sections() as $section)
                    <div class="space-y-1">
                        @if($section['label'])<p class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-white/50">{{ $section['label'] }}</p>@endif
                        @foreach($section['items'] as $item)
                            <a href="{{ $item['href'] }}" class="company-nav-link {{ $item['active'] ? 'is-on' : '' }}">
                                <x-ui.icon :name="$item['icon']" class="h-4 w-4 shrink-0" />
                                <span class="company-nav-label">{{ $item['label'] }}</span>
                                @if($item['warning'])<x-ui.icon name="alert" class="ml-auto h-4 w-4 text-[#F4B820]" />@endif
                                @if($item['badge'])<span class="company-badge {{ strlen($item['badge']) > 3 ? 'is-text' : '' }}">{{ $item['badge'] }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="company-user relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()">
                    <x-ui.avatar :name="auth()->user()->name" class="h-9 w-9" />
                    <span class="min-w-0">
                        <strong class="block truncate text-sm">{{ auth()->user()->name }}</strong>
                        <span class="block text-xs text-white/70">{{ $portal->roleLabel() }}</span>
                    </span>
                    <x-ui.icon name="chevron" class="ml-auto h-4 w-4" />
                </button>
                <menu x-show="open" x-cloak @click.outside="open = false">
                    <li><a href="{{ route('profile.edit') }}">{{ __('company_portal.account.mine') }}</a></li>
                    <li><a href="{{ route('profile.edit') }}#cambiar-contrasena">{{ __('company_portal.account.password') }}</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">{{ __('company_portal.account.logout') }}</button>
                        </form>
                    </li>
                </menu>
            </div>
        </aside>

        <main class="company-main p-6 sm:p-8">
            <header class="mb-6 flex items-center gap-3">
                <button type="button" class="company-menu-toggle" aria-controls="company-sidebar" aria-expanded="false" aria-label="{{ __('company_portal.menu_button') }}"><x-ui.icon name="menu" class="h-5 w-5" /></button>
                <h1 class="text-xl font-extrabold tracking-tight">{{ $pageTitle }}</h1>
            </header>

            @if(session('success'))
                <x-ui.toast :message="session('success')" class="mb-5" />
            @endif
            @if($organization->status === 'pending')
                <x-ui.alert-item class="mb-5" tone="warning" :title="__('Validación pendiente')">Ya puedes configurar tu flota y revisar el panel. La publicación de salidas y la venta de pasajes se habilitarán cuando NavegaYA valide el RUC y los permisos operativos.</x-ui.alert-item>
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
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    side.classList.remove('is-open');
                    button.setAttribute('aria-expanded', 'false');
                }
            }));
        })();
    </script>
</body>
</html>
