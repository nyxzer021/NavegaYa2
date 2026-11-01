@php($portal = \App\Support\PortalHome::for(auth()->user()))
@php($insidePortal = auth()->check() && request()->is('admin', 'admin/*', 'empresa', 'empresa/*', 'mi-negocio', 'mi-negocio/*'))
@if($insidePortal)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Acceso no autorizado · NavegaYA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-16">
        <section class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm sm:p-12">
            <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">NavegaYA</p>
            <span class="mt-3 block text-6xl font-black text-amber-400">403</span>
            <h1 class="mt-4 text-3xl font-black text-emerald-950">Acceso no autorizado</h1>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-slate-600">Tu cuenta no tiene permiso para entrar en esta sección. Puedes volver al panel que sí te corresponde.</p>
            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ $portal['url'] }}" class="ny-button">{{ $portal['label'] }}</a>
                <a href="{{ route('home') }}" class="ny-button ny-button--accent">Ir al sitio público</a>
            </div>
        </section>
    </main>
</body>
</html>
@else
@include('errors.base',['code'=>403,'title'=>'Acceso no autorizado','message'=>'Tu cuenta no tiene permiso para entrar en esta sección. Regresa al sitio público o consulta tus pasajes.'])
@endif
