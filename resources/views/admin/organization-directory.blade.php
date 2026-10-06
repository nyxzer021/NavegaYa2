<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-[#12372d]">Empresas y agencias</h2></x-slot>
    <div class="mx-auto max-w-6xl py-3">
        <div class="mb-6 rounded-2xl bg-gradient-to-r from-[#10352f] to-[#1d6457] p-7 text-white shadow-lg">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-200">Directorio administrativo</p>
            <h1 class="mt-2 text-3xl font-bold">Empresas y agencias registradas</h1>
            <p class="mt-2 text-emerald-50">Consulta los datos de contacto, el estado de validación y los usuarios asignados.</p>
        </div>
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><p class="font-semibold text-slate-800">{{ $organizations->total() }} registros</p><a href="{{ route('admin.organizations.index') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-bold text-[#12372d]">Revisar solicitudes</a></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[780px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-6 py-4">Empresa</th><th class="px-4 py-4">Tipo</th><th class="px-4 py-4">Contacto</th><th class="px-4 py-4">Estado</th><th class="px-4 py-4">Usuarios</th><th class="px-6 py-4"></th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($organizations as $organization)
                <tr class="hover:bg-slate-50"><td class="px-6 py-4"><p class="font-bold text-slate-800">{{ $organization->commercial_name ?: $organization->legal_name }}</p><p class="mt-1 text-xs text-slate-500">RUC: {{ $organization->ruc ?: 'No registrado' }}</p></td><td class="px-4 py-4"><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $organization->type === 'agency' ? 'Agencia' : 'Transporte' }}</span></td><td class="px-4 py-4"><p class="text-slate-700">{{ $organization->contact_name ?: 'Sin contacto' }}</p><p class="mt-1 text-xs text-slate-500">{{ $organization->email }}</p></td><td class="px-4 py-4">@php($status = ['active'=>['Activa','bg-emerald-50 text-emerald-700'],'pending'=>['Pendiente','bg-amber-50 text-amber-700'],'rejected'=>['Rechazada','bg-rose-50 text-rose-700']][$organization->status] ?? ['Sin estado','bg-slate-100 text-slate-600'])<span class="rounded-full px-3 py-1 text-xs font-bold {{ $status[1] }}">{{ $status[0] }}</span></td><td class="px-4 py-4 font-semibold text-slate-700">{{ $organization->users_count }}</td><td class="px-6 py-4 text-right"><a class="font-bold text-[#146e60] hover:text-[#0d493f]" href="{{ route('admin.organization-directory.show', $organization) }}">Ver detalle →</a></td></tr>
            @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Aún no hay empresas ni agencias registradas.</td></tr>
            @endforelse
            </tbody></table></div>
            @if($organizations->hasPages())<div class="border-t border-slate-100 px-6 py-4">{{ $organizations->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
