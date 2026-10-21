@php($adminInbox = app(\App\Support\AdminRequestInbox::class)->summary())
<header class="ny-admin-header" style="display:flex;align-items:center;justify-content:space-between;min-height:60px;padding:10px 24px;border-bottom:1px solid #dce7e2;">
    <div>{{ $header ?? '' }}@yield('header')</div>
    <div class="flex items-center gap-3">
        <details class="ny-account">
            <summary aria-label="Solicitudes pendientes" class="relative grid h-10 w-10 place-items-center rounded-xl border border-emerald-900/10 bg-white text-lg shadow-sm hover:bg-emerald-50">🔔@if($adminInbox['total'])<span class="absolute -right-1.5 -top-1.5 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-600 px-1 text-[9px] font-black text-white">{{ $adminInbox['total']>99?'99+':$adminInbox['total'] }}</span>@endif</summary>
            <div class="ny-account-menu w-[340px] max-w-[85vw]"><div class="flex items-center justify-between border-b border-slate-100 px-2 pb-2"><strong class="text-sm">Solicitudes pendientes</strong><span class="rounded-full bg-amber-100 px-2 py-1 text-[9px] font-black text-amber-800">{{ $adminInbox['total'] }}</span></div>@forelse($adminInbox['latest'] as $requestItem)<a href="{{ $requestItem['url'] }}" class="mt-1 !flex gap-3"><span class="text-lg">{{ $requestItem['icon'] }}</span><span><b class="block text-xs">{{ $requestItem['title'] }}</b><small class="line-clamp-1 text-[10px] text-slate-500">{{ $requestItem['status'] }}</small></span></a>@empty<p class="px-3 py-5 text-center text-xs text-slate-500">No hay solicitudes pendientes.</p>@endforelse<a href="{{ route('admin.requests.index') }}" class="mt-2 !bg-emerald-900 !text-center !font-black !text-white">Abrir bandeja completa →</a></div>
        </details>
        <details class="ny-account"><summary><span style="display:grid;place-items:center;width:32px;height:32px;border-radius:50%;background:#e8f5f0;color:#176d60;"><x-nav-icon name="user" style="width:18px;height:18px;"/></span><span>Bienvenido, {{ auth()->user()->name }}</span><span>⌄</span></summary><div class="ny-account-menu"><a href="{{ route('profile.edit') }}">Mi perfil</a><form method="POST" action="{{ route('logout') }}" data-turbo="false">@csrf<button class="!text-rose-700">Cerrar sesión</button></form></div></details>
    </div>
</header>

