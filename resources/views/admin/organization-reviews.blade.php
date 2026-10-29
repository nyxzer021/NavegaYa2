<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.dashboard') }}" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-lg text-slate-700 shadow-sm" aria-label="Regresar al dashboard">←</a>
            <div><p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Empresas y operación</p><h2 class="ny-page-title">Solicitudes de afiliación</h2></div>
        </div>
    </x-slot>
    <div class="mx-auto max-w-7xl space-y-5 py-3">
        @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong class="block">La solicitud todavía no puede aprobarse</strong><span>{{ session('error') }} La empresa debe confirmar el enlace enviado a su correo.</span></div>@endif

        <section class="rounded-3xl bg-gradient-to-r from-[#07382c] to-slate-900 p-6 text-white shadow-lg">
            <div class="flex flex-wrap items-center justify-between gap-5">
                <div><span class="text-[10px] font-black uppercase tracking-widest text-emerald-300">Bandeja de validación</span><h1 class="mt-2 text-2xl font-black">Decide qué empresas pueden operar</h1><p class="mt-1 max-w-2xl text-sm text-emerald-100/75">Revisa identidad, RUC, responsable y confirmación del correo. Aprobar habilita el acceso empresarial.</p></div>
                <div class="rounded-2xl bg-white/10 px-5 py-3 text-center"><strong class="block text-3xl">{{ $organizations->count() }}</strong><span class="text-[10px] font-bold uppercase text-emerald-100">Pendientes</span></div>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-[10px] font-black uppercase text-slate-400">Paso 1</span><strong class="mt-1 block text-sm text-slate-900">Verifica la empresa</strong><p class="mt-1 text-xs text-slate-500">Comprueba RUC, razón social y tipo de operación.</p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-[10px] font-black uppercase text-slate-400">Paso 2</span><strong class="mt-1 block text-sm text-slate-900">Confirma al responsable</strong><p class="mt-1 text-xs text-slate-500">El correo debe estar confirmado antes de aprobar.</p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-[10px] font-black uppercase text-slate-400">Paso 3</span><strong class="mt-1 block text-sm text-slate-900">Toma una decisión</strong><p class="mt-1 text-xs text-slate-500">Aprobar habilita el panel; rechazar conserva el registro.</p></article>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4"><div><h2 class="font-black text-slate-900">Empresas esperando revisión</h2><p class="text-xs text-slate-500">Ordenadas desde la solicitud más reciente.</p></div><a href="{{ route('admin.organization-directory.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-700">Ver empresas afiliadas</a></div>
            @forelse($organizations as $organization)
                <article class="grid gap-4 border-b border-slate-100 p-5 last:border-0 lg:grid-cols-[1.4fr_.8fr_1fr_auto] lg:items-center">
                    <div><div class="flex flex-wrap items-center gap-2"><strong class="text-base text-slate-950">{{ $organization->commercial_name ?: $organization->legal_name }}</strong><span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-black uppercase text-slate-600">{{ $organization->type === 'agency' ? 'Agencia' : 'Empresa de transporte' }}</span></div><p class="mt-1 text-xs text-slate-500">Razón social: {{ $organization->legal_name }}</p></div>
                    <div><span class="text-[9px] font-black uppercase text-slate-400">RUC</span><strong class="block text-sm text-slate-800">{{ $organization->ruc ?: 'No registrado' }}</strong></div>
                    <div><span class="text-[9px] font-black uppercase text-slate-400">Responsable</span><strong class="block text-sm text-slate-800">{{ $organization->contact_name ?: 'No registrado' }}</strong><span class="block max-w-[260px] truncate text-xs text-slate-500">{{ $organization->email }}</span><span class="mt-1 inline-flex rounded-full px-2 py-1 text-[9px] font-black {{ $organization->contact_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $organization->contact_verified_at ? '✓ Correo confirmado' : 'Correo por confirmar' }}</span></div>
                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        @if($organization->contact_verified_at)<form method="POST" action="{{ route('admin.organizations.approve',$organization) }}">@csrf @method('PATCH')<button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black text-white">Aprobar empresa</button></form>@else<form method="POST" action="{{ route('admin.organizations.resend-verification',$organization) }}">@csrf<button type="submit" class="rounded-xl bg-amber-400 px-4 py-2.5 text-xs font-black text-slate-950">Reenviar correo</button></form>@endif
                        <form method="POST" action="{{ route('admin.organizations.reject',$organization) }}" onsubmit="return confirm('¿Rechazar esta solicitud?')">@csrf @method('PATCH')<button type="submit" class="rounded-xl border border-rose-200 px-4 py-2.5 text-xs font-black text-rose-700">Rechazar</button></form>
                    </div>
                </article>
            @empty
                <div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-2xl">✓</span><strong class="mt-4 block text-lg text-slate-900">Bandeja al día</strong><p class="mt-1 text-sm text-slate-500">No hay solicitudes pendientes. Las nuevas aparecerán aquí automáticamente.</p><a href="{{ route('admin.dashboard') }}" class="mt-5 inline-flex rounded-xl bg-emerald-800 px-5 py-3 text-xs font-black text-white">Volver al dashboard</a></div>
            @endforelse
        </section>
    </div>
</x-app-layout>
