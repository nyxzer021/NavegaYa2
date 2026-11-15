<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal de empresa') · NavegaYA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .company-shell { display: flex; min-height: 100vh; background: #F6F8F7; color: #10231D; }
        .company-sidebar { position: sticky; top: 0; display: flex; flex-direction: column; width: 272px; flex: 0 0 272px; height: 100vh; overflow-y: auto; overscroll-behavior: contain; background: linear-gradient(180deg, #062C21 0%, #04241B 100%); color: #fff; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.18) transparent; }
        .company-sidebar::-webkit-scrollbar { width: 6px; }
        .company-sidebar::-webkit-scrollbar-thumb { border-radius: 999px; background: rgba(255,255,255,.18); }
        .company-main { min-width: 0; flex: 1; }
        .company-top { display: flex; align-items: center; gap: 12px; height: 56px; padding: 0 16px; border-bottom: 1px solid #E6ECE9; background: #fff; }
        .company-crumb { min-width: 0; color: #607069; font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .company-crumb strong { color: #10231D; font-weight: 700; }
        .company-search { display: flex; align-items: center; gap: 8px; height: 36px; min-width: 0; flex: 1; max-width: 420px; margin-left: auto; border: 1px solid #E6ECE9; border-radius: 8px; background: #F6F8F7; padding: 0 10px; color: #607069; }
        .company-search input { width: 100%; border: 0; background: transparent; color: #10231D; font-size: 13.5px; outline: none; }
        .company-search kbd { border: 1px solid #E6ECE9; border-radius: 6px; background: #fff; padding: 1px 5px; color: #607069; font-size: 12.5px; }
        .company-icon-btn { display: inline-grid; position: relative; width: 36px; height: 36px; place-items: center; border: 1px solid #E6ECE9; border-radius: 8px; background: #fff; color: #064E3B; text-decoration: none; }
        .company-icon-btn:focus-visible, .company-menu-toggle:focus-visible, .company-user-btn:focus-visible, .company-nav-link:focus-visible, .company-search input:focus-visible { outline: 2px solid #F4B820; outline-offset: 2px; }
        .company-note { position: absolute; top: 4px; right: 4px; min-width: 16px; height: 16px; border-radius: 999px; background: #F4B820; color: #10231D; font-size: 12.5px; font-weight: 700; line-height: 16px; text-align: center; }
        .company-menu-toggle, .company-close { display: none; align-items: center; justify-content: center; min-height: 44px; min-width: 44px; border: 1px solid #E6ECE9; border-radius: 8px; background: #fff; color: #064E3B; }
        .company-card { margin: 12px; border: 1px solid rgba(255,255,255,.12); border-radius: 12px; padding: 12px; }
        .company-chip { display: inline-flex; margin-top: 8px; border-radius: 999px; background: rgba(244,184,32,.16); color: #F4B820; padding: 2px 8px; font-size: 12.5px; font-weight: 700; }
        .company-section { margin: 2px 0; border-top: 1px solid rgba(255,255,255,.06); padding-top: 2px; }
        .company-group { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 36px; margin: 2px 8px; border-radius: 8px; padding: 0 8px; color: rgba(215,235,227,.72); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; cursor: pointer; list-style: none; transition: color .15s ease, background .15s ease; }
        .company-group::-webkit-details-marker { display: none; }
        .company-group:hover { background: rgba(255,255,255,.05); color: #fff; }
        .company-group svg { width: 14px; height: 14px; transition: transform .2s ease; }
        .company-section[open] > .company-group svg { transform: rotate(180deg); }
        .company-section.has-active > .company-group { color: #F4B820; }
        .company-group-count { margin-left: auto; color: rgba(215,235,227,.5); font-size: 12px; letter-spacing: 0; }
        .company-section[open] .company-group-count { display: none; }
        .company-nav-link { display: flex; align-items: center; gap: 10px; min-height: 38px; margin: 1px 8px; border-radius: 10px; padding: 4px 8px; color: #d7ebe3; font-size: 13.5px; font-weight: 600; line-height: 1.2; text-decoration: none; transition: background .15s ease, color .15s ease; }
        .company-nav-link.is-on { background: linear-gradient(90deg, rgba(244,184,32,.18), rgba(244,184,32,.04)); color: #fff; box-shadow: inset 3px 0 0 #F4B820; }
        .company-nav-link.is-locked { opacity: .5; cursor: not-allowed; }
        .company-nav-link:hover { background: rgba(255,255,255,.07); color: #fff; }
        .company-nav-icon { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: color-mix(in srgb, var(--c) 30%, transparent); color: color-mix(in srgb, var(--c) 25%, white); transition: background .15s ease, color .15s ease; }
        .company-nav-link:hover .company-nav-icon, .company-nav-link.is-on .company-nav-icon { background: var(--c); color: #fff; }
        .company-nav-icon svg { width: 15px; height: 15px; }
        .company-badge { margin-left: auto; border-radius: 999px; background: #F4B820; color: #10231D; padding: 0 6px; font-size: 12.5px; font-weight: 700; }
        .company-user { position: relative; }
        .company-user-btn { display: flex; align-items: center; gap: 8px; min-height: 36px; border: 0; background: transparent; color: #10231D; font-weight: 700; }
        .company-user menu { position: absolute; right: 0; top: calc(100% + 8px); z-index: 40; width: 13rem; margin: 0; border: 1px solid #E6ECE9; border-radius: 12px; background: #fff; padding: 6px; list-style: none; box-shadow: 0 12px 28px #10231d22; }
        .company-user menu a, .company-user menu button { display: flex; width: 100%; min-height: 36px; align-items: center; border: 0; border-radius: 8px; background: transparent; padding: 0 10px; color: #10231D; font-size: 13.5px; font-weight: 600; text-align: left; text-decoration: none; }
        .company-user menu a:hover, .company-user menu button:hover { background: #F6F8F7; }
        .company-backdrop { display: none; }
        .company-content { padding: 24px; }
        .company-content h1 { margin: 0 0 16px; font-size: 22px; font-weight: 700; letter-spacing: -.02em; }
        @media (max-width: 1023px) {
            .company-menu-toggle, .company-close { display: inline-flex; }
            .company-sidebar { position: fixed; z-index: 70; top: 0; left: 0; width: min(280px, 88vw); transform: translateX(-105%); transition: transform .2s ease; }
            .company-sidebar.is-open { transform: none; }
            .company-backdrop { position: fixed; z-index: 60; inset: 0; background: rgba(6, 44, 33, .45); }
            .company-backdrop.is-open { display: block; }
            .company-top { padding-left: 8px; }
            .company-search kbd { display: none; }
            .company-content { padding: 16px; }
            .company-nav-link, .company-user-btn, .company-user menu a, .company-user menu button { min-height: 44px; }
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
    $current = null;
    foreach ($portal->sections() as $section) {
        foreach ($section['items'] as $item) {
            if ($item['active']) {
                $current = $item;
            }
        }
    }
    $unread = $portal->unreadMessageCount();
@endphp
<body class="ny-company-portal min-h-full antialiased">
    <div class="company-shell">
        <aside id="company-sidebar" class="company-sidebar" data-company-shell>
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#F4B820] text-[#10231D]"><x-ui.icon name="fleet" class="h-5 w-5" /></span>
                    <span>
                        <strong class="block text-base font-extrabold">Navega<span class="text-[#F4B820]">YA</span></strong>
                        <span class="block text-[12.5px] font-semibold text-white/70">{{ __('company_portal.portal') }}</span>
                    </span>
                </a>
                <button type="button" class="company-close" data-company-close aria-label="{{ __('company_portal.close_menu') }}"><x-ui.icon name="close" class="h-5 w-5" /></button>
            </div>

            <div class="company-card">
                <div class="flex items-center gap-3">
                    <x-ui.avatar :name="$companyName" :src="$organization->logo_path" class="h-10 w-10" />
                    <div class="min-w-0">
                        <strong class="block truncate text-sm font-bold" title="{{ $companyName }}">{{ $companyName }}</strong>
                        <span class="block truncate text-[12.5px] text-white/70">{{ $portal->modalityLabel() }}</span>
                        @if($portal->hasBasePort())
                            <span class="block truncate text-[12.5px] text-white/70">{{ $portal->baseLabel() }}</span>
                        @else
                            <a href="{{ route('company.details.index') }}" class="block truncate text-[12.5px] font-semibold text-[#F4B820]">{{ $portal->baseLabel() }}</a>
                        @endif
                    </div>
                </div>
                <span class="company-chip">{{ $sales['label'] }}</span>
            </div>

            <nav class="flex-1 pb-6" data-menu-lead="{{ $portal->lead() }}" data-company-nav aria-label="{{ __('company_portal.portal') }}">
                @foreach($portal->sections() as $section)
                    @php($sectionActive = collect($section['items'])->contains('active', true))
                    @if($section['label'])
                    <details class="company-section {{ $sectionActive ? 'has-active' : '' }}" data-section="{{ $section['key'] }}" open>
                    <summary class="company-group"><span>{{ $section['label'] }}</span><span class="company-group-count">{{ count($section['items']) }}</span><x-ui.icon name="chevron" /></summary>
                    @endif
                    @foreach($section['items'] as $item)
                        @if($item['locked'])
                            <span class="company-nav-link is-locked" title="{{ $item['reason'] }}">
                                <span class="company-nav-icon" style="--c: {{ $item['color'] }}"><x-ui.icon :name="$item['icon']" /></span>
                                <span>{{ $item['label'] }}</span>
                                <x-ui.icon name="lock" class="ml-auto h-4 w-4" />
                            </span>
                        @else
                            <a href="{{ $item['href'] }}" class="company-nav-link {{ $item['active'] ? 'is-on' : '' }}" @if($item['active']) aria-current="page" @endif>
                                <span class="company-nav-icon" style="--c: {{ $item['color'] }}"><x-ui.icon :name="$item['icon']" /></span>
                                <span>{{ $item['label'] }}</span>
                                @if($item['badge'])<span class="company-badge">{{ $item['badge'] }}</span>@endif
                            </a>
                        @endif
                    @endforeach
                    @if($section['label'])</details>@endif
                @endforeach
            </nav>
        </aside>
        <div class="company-backdrop" data-company-backdrop></div>

        <main class="company-main">
            <header class="company-top">
                <button type="button" class="company-menu-toggle" aria-controls="company-sidebar" aria-expanded="false" aria-label="{{ __('company_portal.menu_button') }}"><x-ui.icon name="menu" class="h-5 w-5" /></button>
                <p class="company-crumb">
                    @if($current && $current['group'])
                        {{ $current['group'] }} <span aria-hidden="true">›</span>
                        @if(request()->routeIs('company.commissions.*'))
                            {{ $current['label'] }} <span aria-hidden="true">›</span> <strong>Cargo de servicio</strong>
                        @else
                            <strong>{{ $current['label'] }}</strong>
                        @endif
                    @else
                        <strong>{{ $pageTitle }}</strong>
                    @endif
                </p>
                <label class="company-search">
                    <x-ui.icon name="search" class="h-4 w-4 shrink-0" />
                    <input id="company-search" type="search" placeholder="{{ __('company_portal.search') }}" aria-label="{{ __('company_portal.search') }}" autocomplete="off">
                    <kbd>Ctrl+K</kbd>
                </label>
                <a class="company-icon-btn" href="{{ route('messages.index') }}" aria-label="{{ __('company_portal.notifications') }}">
                    <x-ui.icon name="bell" class="h-4 w-4" />
                    @if($unread > 0)<span class="company-note">{{ $unread }}</span>@endif
                </a>
                <div class="company-user" x-data="{ open: false }">
                    <button type="button" class="company-user-btn" @click="open = !open" :aria-expanded="open.toString()">
                        <x-ui.avatar :name="auth()->user()->name" class="h-8 w-8" />
                        <span class="hidden min-w-0 text-left sm:block">
                            <strong class="block max-w-[10rem] truncate text-[13.5px]">{{ auth()->user()->name }}</strong>
                            <span class="block text-[12.5px] font-semibold text-[#607069]">{{ $portal->roleLabel() }}</span>
                        </span>
                        <x-ui.icon name="chevron" class="h-4 w-4" />
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
            </header>

            <div class="company-content">
                <h1>{{ $pageTitle }}</h1>
                @if(session('success'))
                    <x-ui.toast :message="session('success')" class="mb-5" />
                @endif
                @if($organization->status === 'pending')
                    <x-ui.alert-item class="mb-5" tone="warning" :title="__('Validación pendiente')">Ya puedes configurar tu flota y revisar el panel. La publicación de salidas y la venta de pasajes se habilitarán cuando NavegaYA valide el RUC y los permisos operativos.</x-ui.alert-item>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
    <script>
        (() => {
            const button = document.querySelector('.company-menu-toggle');
            const side = document.getElementById('company-sidebar');
            const backdrop = document.querySelector('[data-company-backdrop]');
            const close = document.querySelector('[data-company-close]');
            const search = document.getElementById('company-search');
            if (!button || !side || !backdrop) return;
            const setOpen = (open) => {
                side.classList.toggle('is-open', open);
                backdrop.classList.toggle('is-open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            };
            button.addEventListener('click', () => setOpen(!side.classList.contains('is-open')));
            close?.addEventListener('click', () => setOpen(false));
            backdrop.addEventListener('click', () => setOpen(false));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setOpen(false);
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    search?.focus();
                }
            });
            side.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
                sessionStorage.setItem('ny-company-menu-scroll', String(side.scrollTop));
                if (window.matchMedia('(max-width: 1023px)').matches) setOpen(false);
            }));
        })();
        (() => {
            const side = document.getElementById('company-sidebar');
            if (!side) return;
            const key = 'ny-company-menu-sections';
            let saved = {};
            try { saved = JSON.parse(localStorage.getItem(key) || '{}'); } catch (error) { saved = {}; }
            side.querySelectorAll('details[data-section]').forEach((section) => {
                const name = section.dataset.section;
                if (!section.classList.contains('has-active') && saved[name] === false) section.open = false;
                section.addEventListener('toggle', () => {
                    saved[name] = section.open;
                    localStorage.setItem(key, JSON.stringify(saved));
                });
            });
            const scroll = Number(sessionStorage.getItem('ny-company-menu-scroll'));
            if (scroll > 0) side.scrollTop = scroll;
            const active = side.querySelector('.company-nav-link.is-on');
            if (active) {
                const box = side.getBoundingClientRect();
                const item = active.getBoundingClientRect();
                if (item.top < box.top + 120 || item.bottom > box.bottom - 24) {
                    side.scrollTop += item.top - box.top - box.height / 3;
                }
            }
        })();
    </script>
</body>
</html>
