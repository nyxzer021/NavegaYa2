<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Solicitudes de empresas y agencias</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="mb-5 rounded-lg bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="mb-5 rounded-lg bg-red-50 p-4 text-red-800">{{ session('error') }}</div>@endif
        <section class="mb-6 flex flex-col gap-4 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[.14em] text-emerald-800">Afiliación autoservicio</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">Cada empresa registra su propia solicitud</h3>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">El equipo de NavegaYA revisa los datos y documentos antes de habilitar el panel administrativo de la empresa.</p>
            </div>
            <a href="{{ route('company.registration') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-800 px-5 text-sm font-black text-white transition hover:bg-emerald-900">Abrir afiliación pública ↗</a>
        </section>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-[#12372d] text-white"><tr><th class="px-5 py-3 text-left">Empresa</th><th class="px-5 py-3 text-left">RUC</th><th class="px-5 py-3 text-left">Contacto</th><th class="px-5 py-3 text-left">Correo confirmado</th><th class="px-5 py-3 text-right">Acciones</th></tr></thead>
            <tbody class="divide-y divide-gray-100">@forelse($organizations as $organization)<tr><td class="px-5 py-4"><strong>{{ $organization->legal_name }}</strong><br><span class="text-gray-500">{{ $organization->type === 'agency' ? 'Agencia' : 'Empresa de transporte' }}</span></td><td class="px-5 py-4">{{ $organization->ruc }}</td><td class="px-5 py-4">{{ $organization->contact_name }}<br><span class="text-gray-500">{{ $organization->email }}</span></td><td class="px-5 py-4">{{ $organization->contact_verified_at ? 'Sí' : 'Pendiente' }}</td><td class="px-5 py-4"><div class="flex items-center justify-end gap-2 whitespace-nowrap"><form method="POST" action="{{ route('admin.organizations.approve',$organization) }}">@csrf @method('PATCH')<button type="submit" class="rounded bg-emerald-600 px-3 py-2 text-white">Aprobar</button></form><form method="POST" action="{{ route('admin.organizations.reject',$organization) }}">@csrf @method('PATCH')<button type="submit" class="rounded bg-red-600 px-3 py-2 text-white">Rechazar</button></form></div></td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No hay solicitudes pendientes.</td></tr>@endforelse</tbody></table>
        </div>
    </div></div>
</x-app-layout>

