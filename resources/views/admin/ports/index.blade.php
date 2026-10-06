<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-[#12372d]">Puertos de embarque</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl py-3">
        @if (session('success'))
            <div class="mb-5 rounded-xl bg-emerald-50 px-5 py-4 font-semibold text-emerald-800">✓ {{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="mb-5 rounded-xl bg-rose-50 px-5 py-4 font-semibold text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-gradient-to-r from-[#10352f] to-[#1d6457] p-7 text-white">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-200">Catálogo operativo</p>
                <h1 class="mt-2 text-3xl font-bold">Puertos de embarque</h1>
                <p class="mt-2 text-emerald-50">Cada puerto puede ser salida o llegada según la ruta. Se registra una sola vez para ida y regreso.</p>
            </div>
            <a href="{{ route('admin.ports.create') }}" class="rounded-xl bg-amber-400 px-5 py-3 font-bold text-[#12372d]">+ Registrar puerto</a>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar terminal o ciudad" class="h-10 min-w-64 rounded-xl border-slate-200 bg-slate-50 text-xs">
            <select name="modality" class="h-10 rounded-xl border-slate-200 bg-slate-50 text-xs"><option value="">Todas las modalidades</option><option value="fluvial" @selected(request('modality')==='fluvial')>Fluvial</option><option value="aereo" @selected(request('modality')==='aereo')>Aérea</option></select>
            <button class="rounded-xl bg-[#062c21] px-5 text-xs font-bold text-white">Filtrar catálogo</button>
        </form>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Terminal</th><th class="px-4 py-4">Tipo</th><th class="px-4 py-4">Ciudad / provincia</th><th class="px-4 py-4">Río o pista</th><th class="px-6 py-4">Estado</th><th class="px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($ports as $port)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-bold text-slate-800">{{ $port->name }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $port->modality==='aereo'?'bg-violet-50 text-violet-700':'bg-sky-50 text-sky-700' }}">{{ $port->modality==='aereo'?'✈ Aéreo':'⚓ Fluvial' }}</span></td>
                                <td class="px-4 py-4 text-slate-700">{{ $port->city }}<br><span class="text-[10px] text-slate-400">{{ $port->province->name ?? 'Loreto' }} · {{ $port->region }}</span></td>
                                <td class="px-4 py-4 text-slate-700">{{ $port->river ?: 'No registrado' }}</td>
                                <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $port->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $port->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('admin.ports.edit', $port) }}" class="font-bold text-[#146e60]">Editar</a>
                                    <form method="POST" action="{{ route('admin.ports.destroy', $port) }}" class="ml-3 inline" onsubmit="return confirm('¿Eliminar este puerto? Esta acción no se puede deshacer.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-bold text-rose-700">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Aún no hay puertos registrados. Crea el primero para comenzar a construir rutas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($ports->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">{{ $ports->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
