<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>NavegaYA · Cuenta de viajero</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="ny-app ny-auth font-sans text-gray-900 antialiased">
        <style>.guest-page{min-height:100vh;padding:34px 18px;background:linear-gradient(135deg,#0a3029d9,#0d685dd9),url('/images/navegaya-hero-rio.png') center/cover;display:grid;place-items:center}.guest-shell{width:min(100%,720px)}.guest-brand{display:block;margin-bottom:15px;color:#fff;text-decoration:none;font-size:24px;font-weight:850;text-align:center}.guest-card{padding:30px;border:1px solid #ffffff45;border-radius:24px;background:#fffffff2;box-shadow:0 22px 50px #03221c70}.auth-heading p{margin:0;color:#0a7568;font-size:11px;font-weight:850;letter-spacing:.14em}.auth-heading h1{margin:6px 0;color:#12372d;font-size:30px;font-weight:850}.auth-heading span{color:#60796f;font-size:14px}.auth-form{margin-top:25px}.auth-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.auth-grid-single{grid-template-columns:1fr}.auth-full{grid-column:1/-1}.auth-field label{font-size:13px;font-weight:800;color:#173d34}.auth-field input{border-color:#c9dcd5;border-radius:11px}.auth-note{margin:17px 0;color:#6b8179;font-size:12px;line-height:1.5}.auth-remember{display:block;margin:17px 0;color:#5d756c;font-size:13px}.auth-remember input{margin-right:6px}.auth-submit{width:100%;justify-content:center;border-radius:11px!important;background:#eeb82b!important;color:#12372d!important;font-weight:850!important}.auth-switch{margin:17px 0 0;text-align:center;color:#657d75;font-size:13px}.auth-switch a,.auth-links a{color:#087669;font-weight:800}.auth-links{display:flex;justify-content:space-between;gap:14px;margin-top:17px;color:#657d75;font-size:13px}.auth-links span{text-align:right}.oauth-separator{display:flex;align-items:center;gap:10px;margin:20px 0 12px;color:#7b9088;font-size:12px}.oauth-separator:before,.oauth-separator:after{content:'';height:1px;flex:1;background:#d9e5e0}.google-button{display:flex;align-items:center;justify-content:center;gap:10px;border:1px solid #cedcd6;border-radius:11px;padding:11px;color:#294a42;text-decoration:none;font-size:14px;font-weight:800}.google-button b{display:grid;place-items:center;width:21px;height:21px;border-radius:50%;background:#fff;color:#4285f4;font-size:17px}.google-button.is-pending{cursor:not-allowed;opacity:.62}.google-button small{margin-left:auto;color:#7a8e87;font-size:10px;font-weight:700}@media(max-width:560px){.guest-card{padding:23px}.auth-grid{grid-template-columns:1fr}.auth-full{grid-column:auto}.auth-links{display:grid;gap:8px}.auth-links span{text-align:left}}</style>
        <div class="guest-page"><div class="guest-shell"><a href="{{ route('home') }}" class="guest-brand">⛴ NavegaYA</a><div class="guest-card">
                {{ $slot }}
            </div></div>
        </div>
    <x-public-i18n />
    </body>
</html>
