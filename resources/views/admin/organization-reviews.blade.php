<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Solicitudes de empresas y agencias</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="mb-5 rounded-lg bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong class="block">La solicitud todavía no puede aprobarse</strong><span>{{ session('error') }} La empresa debe abrir el enlace enviado a su correo. También puedes reenviarlo desde la tabla.</span></div>@endif
        <section class="mb-6 flex flex-col gap-4 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[.14em] text-emerald-800">Afiliación autoservicio</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">Cada empresa registra su propia solicitud</h3>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">El equipo de NavegaYA revisa los datos y documentos antes de habilitar el panel administrativo de la empresa.</p>
            </div>
        </section>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-[#12372d] text-white"><tr><th class="px-5 py-3 text-left">Empresa</th><th class="px-5 py-3 text-left">RUC</th><th class="px-5 py-3 text-left">Contacto</th><th class="px-5 py-3 text-left">Correo confirmado</th><th class="px-5 py-3 text-right">Acciones</th></tr></thead>
            <tbody class="divide-y divide-gray-100">@forelse($organizations as $organization)<tr><td class="px-5 py-4"><strong>{{ $organization->legal_name }}</strong><br><span class="text-gray-500">{{ $organization->type === 'agency' ? 'Agencia' : 'Empresa de transporte' }}</span></td><td class="px-5 py-4">{{ $organization->ruc }}</td><td class="px-5 py-4">{{ $organization->contact_name }}<br><span class="text-gray-500">{{ $organization->email }}</span></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $organization->contact_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $organization->contact_verified_at ? 'Confirmado' : 'Esperando confirmación' }}</span></td><td class="px-5 py-4"><div class="flex flex-wrap items-center justify-end gap-2">@if($organization->contact_verified_at)<form method="POST" action="{{ route('admin.organizations.approve',$organization) }}">@csrf @method('PATCH')<button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Aprobar</button></form>@else<form method="POST" action="{{ route('admin.organizations.resend-verification',$organization) }}">@csrf<button type="submit" class="rounded-lg bg-amber-400 px-3 py-2 text-xs font-bold text-slate-950">Reenviar verificación</button></form>@endif<form method="POST" action="{{ route('admin.organizations.reject',$organization) }}">@csrf @method('PATCH')<button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white">Rechazar</button></form></div></td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No hay solicitudes pendientes.</td></tr>@endforelse</tbody></table>
        </div>
    </div></div>
</x-app-layout>

