<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="NavegaYA">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:image" content="{{ asset('images/branding/banner-home-viajeros-amazonia.webp') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="ny-app ny-auth ny-auth-portal font-sans text-gray-900 antialiased">
        <style>.guest-page{min-height:100vh;padding:34px 18px;background:linear-gradient(135deg,#0a3029d9,#0d685dd9),url('/images/navegaya-hero-rio.png') center/cover;display:grid;place-items:center}.guest-shell{width:min(100%,720px)}.guest-brand{display:block;margin-bottom:15px;color:#fff;text-decoration:none;font-family:'Playfair Display',Georgia,serif;font-size:28px;font-weight:700;text-align:center;letter-spacing:-.02em}.guest-card{position:relative;overflow:hidden;padding:36px 30px 30px;border:1px solid #ffffff55;border-radius:28px;background:#fffcf8f7;box-shadow:0 28px 60px #03221c78}.guest-card:before{content:'';position:absolute;left:0;right:0;top:0;height:6px;background:linear-gradient(90deg,#103a31,#176d60 58%,#f2b624)}.auth-heading p{margin:0;color:#0a7568;font-size:11px;font-weight:850;letter-spacing:.14em}.auth-heading h1{margin:6px 0;color:#12372d;font-family:'Playfair Display',Georgia,serif;font-size:32px;font-weight:700;letter-spacing:-.02em}.auth-heading span{color:#60796f;font-size:14px}.auth-form{margin-top:25px}.auth-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.auth-grid-single{grid-template-columns:1fr}.auth-full{grid-column:1/-1}.auth-field label{font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#3e5c54}.auth-field input{height:48px;border:1px solid #d5e6df;border-radius:14px;background:#fff;padding:0 14px;box-shadow:0 1px 2px rgba(16,58,49,.04)}.auth-note{margin:17px 0;color:#6b8179;font-size:12px;line-height:1.5}.auth-remember{display:flex;align-items:center;gap:9px;margin:17px 0;color:#5d756c;font-size:13px}.auth-remember input[type=checkbox]{width:18px;height:18px;min-width:18px;margin:0;padding:0;border:1.5px solid #0b7569;border-radius:4px;appearance:auto;accent-color:#087669}.auth-submit{width:100%;height:52px;justify-content:center;border-radius:16px!important;background:linear-gradient(180deg,#ffd15a,#f2b624)!important;color:#12372d!important;font-weight:850!important;box-shadow:0 12px 24px rgba(242,182,36,.35);text-transform:none!important;letter-spacing:0!important;font-size:15px!important}.auth-switch{margin:17px 0 0;text-align:center;color:#657d75;font-size:13px}.auth-switch a,.auth-links a{color:#087669;font-weight:800}.auth-links{display:flex;justify-content:space-between;gap:14px;margin-top:17px;color:#657d75;font-size:13px}.auth-links span{text-align:right}.oauth-separator{display:flex;align-items:center;gap:10px;margin:20px 0 12px;color:#7b9088;font-size:12px}.oauth-separator:before,.oauth-separator:after{content:'';height:1px;flex:1;background:#d9e5e0}.google-button{display:flex;align-items:center;justify-content:center;gap:10px;border:1px solid #cedcd6;border-radius:11px;padding:11px;color:#294a42;text-decoration:none;font-size:14px;font-weight:800}.google-button b{display:grid;place-items:center;width:21px;height:21px;border-radius:50%;background:#fff;color:#4285f4;font-size:17px}.google-button.is-pending{cursor:not-allowed;opacity:.62}.google-button small{margin-left:auto;color:#7a8e87;font-size:10px;font-weight:700}@media(max-width:560px){.guest-card{padding:28px 20px 22px}.auth-grid{grid-template-columns:1fr}.auth-full{grid-column:auto}.auth-links{display:grid;gap:8px}.auth-links span{text-align:left}}</style>
        <div class="guest-page"><div class="guest-shell"><a href="{{ route('home') }}" class="guest-brand">⛴ NavegaYA</a><div class="guest-card"><a href="{{route('home')}}" class="mb-5 inline-flex text-sm font-bold text-emerald-800 hover:underline">← Volver a NavegaYA</a>
                {{ $slot }}
            </div></div>
        </div>
    </body>
</html>
