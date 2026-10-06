<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NavegaYA · Panel Administrador')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script type="module">
        import * as Turbo from 'https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.12/+esm';
        window.Turbo = Turbo;
    </script>

    <style>

:root{--forest:#10352f;--deep:#09241f;--gold:#eab634;--ink:#12372d;--muted:#8db0a7}.ny-shell{display:flex;min-height:100vh}.ny-side{width:276px;flex:0 0 276px;background:linear-gradient(180deg,var(--forest),var(--deep));color:#fff}.ny-main{min-width:0;flex:1}.ny-page-title{margin:0;color:var(--ink);font-size:22px;font-weight:750}.ny-brand{display:flex;align-items:center;gap:12px;padding:27px 25px 22px;border-bottom:1px solid rgba(226,246,239,.11)}.ny-boat{width:35px;height:35px;padding:7px;border-radius:11px;color:#153b32;background:var(--gold)}.ny-name{font-size:21px;font-weight:800}.ny-subname{display:block;margin-top:1px;color:var(--muted);font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}.ny-nav{padding:14px 14px 30px}.ny-group{margin-top:19px}.ny-group:first-child{margin-top:0}.ny-category{display:flex;align-items:center;gap:8px;margin:0 10px 8px;font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.ny-category:after{content:"";flex:1;height:1px;background:currentColor;opacity:.2}.blue{color:#78c5ff}.green{color:#70e0b1}.gold{color:#f6cf61}.violet{color:#c9a9ff}.rose{color:#fb9fb6}.ny-link{position:relative;display:flex;align-items:center;gap:11px;min-height:42px;margin:3px 0;padding:10px 12px;border-radius:10px;color:#dcebe6;font-size:13px;font-weight:600;text-decoration:none}.ny-link:hover,.ny-link.active{background:rgba(255,255,255,.075);color:#fff}.ny-link.active:before{content:"";position:absolute;left:0;width:3px;height:21px;background:var(--gold)}.ny-icon{display:grid;place-items:center;width:26px;height:26px;border-radius:8px;background:rgba(255,255,255,.08);color:#b9d9cf}.ny-icon svg{width:16px;height:16px}.ny-link:hover .ny-icon,.ny-link.active .ny-icon{color:var(--gold);background:rgba(234,182,52,.12)}.ny-user{display:none}.ny-account{position:relative}.ny-account summary{display:flex;align-items:center;gap:10px;cursor:pointer;list-style:none}.ny-account summary::-webkit-details-marker{display:none}.ny-account-menu{position:absolute;right:0;top:42px;z-index:20;min-width:180px;padding:8px;border:1px solid #dce7e2;border-radius:10px;background:#fff;box-shadow:0 10px 28px #12372d25}.ny-account-menu a,.ny-account-menu button{display:block;width:100%;padding:9px 10px;border:0;border-radius:7px;background:transparent;color:#173b33;text-align:left;text-decoration:none;font:inherit;font-size:13px;cursor:pointer}.ny-account-menu a:hover,.ny-account-menu button:hover{background:#eef7f3}.ny-avatar{display:grid;place-items:center;width:31px;height:31px;border-radius:50%;background:#2a655a;color:#ddf7ed}.ny-avatar svg{width:18px;height:18px}.ny-user-name{font-size:12px;font-weight:700}.ny-user-role{margin-top:2px;color:var(--muted);font-size:10px}.ny-admin-header{position:relative;isolation:isolate;overflow:visible;background:linear-gradient(90deg,rgba(255,255,255,.97),rgba(242,248,246,.96))!important}.ny-admin-header:before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(255,255,255,.88),rgba(255,255,255,.78)),url('https://images.unsplash.com/photo-1516026672322-bc52d61a55d5?auto=format&fit=crop&w=1600&q=70') center 58%/cover;opacity:.22;pointer-events:none}.ny-admin-header>div,.ny-admin-header>.ny-account{position:relative;z-index:1}@media(max-width:768px){.ny-shell{display:block}.ny-side{width:100%}.ny-brand{padding:17px 20px}.ny-nav{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2px;padding:10px}.ny-group{display:contents}.ny-category{grid-column:1/-1;margin-top:12px}.ny-link{margin:0;padding:8px;min-height:38px;font-size:12px}.ny-user{display:none}.ny-main>header{padding:16px!important}}

        .turbo-progress-bar {
            height: 3px;
            background-color: #10b981;
        }
    </style>
</head>
<body class="ny-app bg-slate-100 font-sans antialiased text-slate-800">
    <div class="ny-shell min-h-screen">
        @include('layouts.partials.admin-sidebar')

        <section class="ny-main flex min-w-0 flex-1 flex-col">
            @include('layouts.partials.admin-header')

            <main class="@yield('main-class', 'flex-1 p-6 md:p-7')">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </section>
    </div>

    <x-platform-admin-theme />
    <x-public-i18n />
</body>
</html>
