@php
    $publicNavigation = \App\Models\NavigationItem::query()->where('is_active', true)->orderBy('position')->orderBy('id')->get();
    $desktopNavigation = $publicNavigation->take(5);
    $overflowNavigation = $publicNavigation->slice(5);
    $whatsAppLabel = trim((string) \App\Models\SystemSetting::value('whatsapp_number'));
    $whatsAppNumber = preg_replace('/\D/', '', $whatsAppLabel);
    $companyRegistrationActive = request()->routeIs('company.registration*');
    $isCurrentNavigation = function ($item): bool {
        if ($item->target_type === 'page') {
            return request()->routeIs('pages.show') && request()->route('slug') === $item->target;
        }

        return $item->target_type === 'route'
            && (request()->routeIs($item->target) || request()->routeIs(str_replace('.index', '.*', $item->target)));
    };
@endphp

<header class="sticky top-0 z-50 w-full border-b border-emerald-900/60 bg-[#062c21] px-4 py-3 text-white shadow-md sm:px-6 lg:px-8" data-public-header>
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10" aria-hidden="true">🚤</span><span class="text-lg font-extrabold tracking-tight">Navega<span class="text-amber-400">YA</span></span></a>

        <nav class="hidden min-w-0 flex-1 items-center justify-center gap-1 text-xs md:flex" aria-label="Navegación principal">
            @foreach($desktopNavigation as $item)
                @php($current = $isCurrentNavigation($item))
                <a href="{{ $item->url() }}" @if($item->opens_new_tab) target="_blank" rel="noopener noreferrer" @endif @if($current) aria-current="page" @endif class="max-w-40 truncate rounded-lg px-2.5 py-2 font-semibold transition {{ $current ? 'bg-amber-400 text-emerald-950' : 'text-emerald-100 hover:bg-white/10 hover:text-white' }}" title="{{ $item->text() }}">{{ $item->text() }}</a>
            @endforeach
            @if($overflowNavigation->isNotEmpty())
                <details class="group relative"><summary class="cursor-pointer list-none rounded-lg px-2.5 py-2 font-semibold text-emerald-100 transition hover:bg-white/10 hover:text-white">Más ▾</summary><div class="absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 text-slate-800 shadow-2xl">@foreach($overflowNavigation as $item)@php($current = $isCurrentNavigation($item))<a href="{{ $item->url() }}" @if($item->opens_new_tab) target="_blank" rel="noopener noreferrer" @endif @if($current) aria-current="page" @endif class="block rounded-lg px-3 py-2 text-sm font-semibold {{ $current ? 'bg-amber-100 text-emerald-950' : 'hover:bg-emerald-50' }}">{{ $item->text() }}</a>@endforeach</div></details>
            @endif
            <a href="{{ route('company.registration') }}" @if($companyRegistrationActive) aria-current="page" @endif class="ml-1 inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 font-semibold transition {{ $companyRegistrationActive ? 'bg-amber-400 text-emerald-950 shadow-sm' : 'border border-white/20 text-emerald-100 hover:bg-white/10 hover:text-white' }}">🏢 <span>Afiliar tu empresa</span></a>
        </nav>

        <div class="flex shrink-0 items-center gap-2">
            @auth<a href="{{ route('dashboard') }}" class="hidden min-h-10 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/15 sm:inline-flex">👤 {{ auth()->user()->isCustomer() ? 'Mis viajes' : 'Mi cuenta' }}</a>@else<a href="{{ route('login') }}" class="hidden min-h-10 items-center gap-2 rounded-xl border border-white/25 px-3 py-2 text-sm font-semibold text-white sm:inline-flex">👤 Iniciar sesión</a>@endauth
            <button type="button" class="grid h-10 w-10 place-items-center rounded-xl border border-white/20 md:hidden" data-mobile-menu-button aria-expanded="false" aria-controls="public-mobile-menu" aria-label="Abrir menú"><span data-mobile-menu-icon aria-hidden="true">☰</span></button>
        </div>
    </div>

    <nav id="public-mobile-menu" hidden class="mx-auto mt-3 max-h-[calc(100vh-5.5rem)] max-w-7xl overflow-y-auto border-t border-white/10 pt-3 md:hidden" aria-label="Navegación móvil">
        <div class="grid gap-1">
            @foreach($publicNavigation as $item)@php($current = $isCurrentNavigation($item))<a href="{{ $item->url() }}" data-mobile-menu-link @if($item->opens_new_tab) target="_blank" rel="noopener noreferrer" @endif @if($current) aria-current="page" @endif class="rounded-lg px-3 py-2.5 text-sm font-semibold {{ $current ? 'bg-amber-400 text-emerald-950' : 'text-emerald-50 hover:bg-white/10' }}">{{ $item->text() }}</a>@endforeach
            <a href="{{ route('company.registration') }}" data-mobile-menu-link class="mt-1 rounded-xl px-3 py-2.5 text-sm font-semibold {{ $companyRegistrationActive ? 'bg-amber-400 text-emerald-950' : 'border border-white/20 text-emerald-50' }}">🏢 Afiliar tu empresa</a>
            @auth<a href="{{ route('dashboard') }}" data-mobile-menu-link class="rounded-xl border border-white/20 px-3 py-2.5 text-sm font-semibold text-white">👤 {{ auth()->user()->isCustomer() ? 'Mis viajes' : 'Mi cuenta' }}</a>@else<a href="{{ route('login') }}" data-mobile-menu-link class="rounded-xl border border-white/20 px-3 py-2.5 text-sm font-semibold text-white">👤 Iniciar sesión</a>@endauth
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-white/10 px-3 pt-3 text-xs">@if($whatsAppNumber)<a href="https://wa.me/{{ $whatsAppNumber }}" target="_blank" rel="noopener" class="font-semibold text-emerald-100">WhatsApp: {{ $whatsAppLabel }}</a>@endif<div class="flex gap-2"><a href="{{ route('public.locale', ['locale' => 'es']) }}" class="rounded-md px-2 py-1 {{ app()->getLocale() === 'es' ? 'bg-white/15 text-white' : 'text-emerald-100' }}">ES</a><a href="{{ route('public.locale', ['locale' => 'en']) }}" class="rounded-md px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-white/15 text-white' : 'text-emerald-100' }}">EN</a></div></div>
        </div>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-public-header]');
    const button = header?.querySelector('[data-mobile-menu-button]');
    const menu = header?.querySelector('#public-mobile-menu');
    const icon = header?.querySelector('[data-mobile-menu-icon]');
    if (!button || !menu || !icon) return;
    const setOpen = open => {
        menu.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        icon.textContent = open ? '✕' : '☰';
    };
    button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
    menu.querySelectorAll('[data-mobile-menu-link]').forEach(link => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
    document.addEventListener('click', event => { if (!header.contains(event.target)) setOpen(false); });
    setOpen(false);
});
</script>
