<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal de mi negocio · NavegaYA' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased">
    @php($unreadSupportCount = auth()->check() ? \App\Models\SupportTicket::query()->where('user_id', auth()->id())->whereNotNull('admin_response')->where(fn($query) => $query->whereNull('requester_viewed_at')->orWhereColumn('requester_viewed_at', '<', 'responded_at'))->count() : 0)
    @php($businessOrganizationId = auth()->check() ? auth()->user()->organizations()->value('organizations.id') : null)
    @php($unreadBusinessMessages = $businessOrganizationId ? \App\Models\BusinessConversation::query()->where('organization_id',$businessOrganizationId)->whereHas('messages',fn($query)=>$query->whereHas('sender.roles',fn($roles)=>$roles->whereIn('code',['super_admin','admin']))->where(fn($unread)=>$unread->whereColumn('business_messages.created_at','>','business_conversations.business_last_read_at')->orWhereNull('business_conversations.business_last_read_at')))->count() : 0)
    <header class="sticky top-0 z-40 border-b border-emerald-950/10 bg-[#052f25] text-white shadow-sm">
        <div class="mx-auto flex h-16 max-w-[1500px] items-center justify-between px-5">
            <a href="{{ route('marketplace.dashboard') }}" class="flex items-center gap-3 font-black">
                <span class="grid h-11 w-11 place-items-center overflow-hidden rounded-xl bg-gradient-to-br from-amber-300 via-orange-400 to-fuchsia-600 shadow-lg shadow-orange-950/20" aria-hidden="true">
                    <svg viewBox="0 0 48 48" class="h-8 w-8" fill="none"><path d="M8 27h7l19-9v20l-19-8H8a3 3 0 0 1-3-3v-1a3 3 0 0 1 3-3Z" fill="white"/><path d="m15 30 3 10h7l-3-8" fill="#FDE68A"/><path d="M37 20c3 2 4 4 4 7s-1 5-4 7" stroke="white" stroke-width="3" stroke-linecap="round"/><circle cx="13" cy="26.5" r="2.5" fill="#7C2D12"/></svg>
                </span>
                <span>Navega<span class="text-amber-400">YA</span><small class="block text-[8px] uppercase tracking-[.2em] text-emerald-200">Portal del anunciante</small></span>
            </a>
            <div class="flex items-center gap-3">
                <a href="{{ route('messages.index') }}" aria-label="Mensajes comerciales" class="relative grid h-10 w-10 place-items-center rounded-xl border border-white/15 bg-white/10 text-lg transition hover:bg-white/20">✉️@if($unreadBusinessMessages)<span class="absolute -right-1.5 -top-1.5 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1 text-[9px] font-black text-white">{{ $unreadBusinessMessages > 99 ? '99+' : $unreadBusinessMessages }}</span>@endif</a>
                <a href="{{ route('support.index') }}" aria-label="Respuestas nuevas de soporte" class="relative grid h-10 w-10 place-items-center rounded-xl border border-white/15 bg-white/10 text-lg transition hover:bg-white/20">🛟@if($unreadSupportCount)<span class="absolute -right-1.5 -top-1.5 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1 text-[9px] font-black text-white">{{ $unreadSupportCount > 99 ? '99+' : $unreadSupportCount }}</span>@endif</a>
                <span class="hidden text-xs text-emerald-100/70 sm:block">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="rounded-xl border border-white/15 bg-white/10 px-4 py-2 text-xs font-black transition hover:bg-white/20">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </header>
    {{ $slot }}
</body>
</html>
