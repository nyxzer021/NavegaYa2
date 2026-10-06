@extends('layouts.admin')

@section('content')
<div class="space-y-5">
    <section class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-emerald-950 to-slate-900 p-6 text-white md:flex-row md:items-center md:justify-between">
        <div><p class="text-[10px] font-bold uppercase tracking-[.18em] text-emerald-300">Ecosistema turístico B2B</p><h1 class="mt-1 text-2xl font-black">Aliados turísticos de Loreto</h1><p class="mt-1 text-xs text-emerald-100/70">Directorio comercial de alojamientos, gastronomía y experiencias.</p></div>
        <a href="{{ route('admin.destination-listings.create', 'lodging') }}" class="rounded-xl bg-amber-400 px-4 py-2.5 text-xs font-black text-emerald-950">+ Registrar aliado</a>
    </section>
    <section class="grid gap-3 sm:grid-cols-3">
        @foreach([['🏨','Hospedajes y lodges',$lodgingsCount,'lodging'],['🍽️','Gastronomía',$gastronomyCount,'gastronomy'],['🌿','Tours y experiencias',$experiencesCount,'attraction']] as [$icon,$label,$count,$type])
            <a href="{{ route('admin.destination-listings.index', $type) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-400"><div class="flex items-center justify-between"><span class="text-xl">{{ $icon }}</span><span class="text-2xl font-black text-slate-900">{{ $count }}</span></div><p class="mt-2 text-xs font-bold text-slate-700">{{ $label }}</p></a>
        @endforeach
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4"><h2 class="text-sm font-black text-slate-900">Directorio consolidado</h2></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Aliado</th><th class="px-4 py-3">Categoría</th><th class="px-4 py-3">Localidad</th><th class="px-4 py-3">Visibilidad</th><th class="px-4 py-3 text-right">Acción</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($partners as $partner)<tr><td class="px-4 py-3"><p class="font-bold text-slate-800">{{ $partner->name }}</p><p class="text-[10px] text-slate-500">{{ $partner->category ?: 'Sin subcategoría' }}</p></td><td class="px-4 py-3">{{ match($partner->type){'lodging'=>'Hospedaje','gastronomy'=>'Gastronomía',default=>'Experiencia'} }}</td><td class="px-4 py-3">{{ $partner->city?->name ?? 'Loreto' }}</td><td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $partner->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $partner->is_active ? 'Publicado' : 'Oculto' }}</span></td><td class="px-4 py-3 text-right"><a href="{{ route('admin.destination-listings.edit', [$partner->type, $partner]) }}" class="font-bold text-emerald-700">Editar →</a></td></tr>
            @empty<tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No hay aliados registrados.</td></tr>@endforelse
        </tbody></table></div>
        @if($partners->hasPages())<div class="border-t border-slate-100 p-4">{{ $partners->links() }}</div>@endif
    </section>
</div>
@endsection
