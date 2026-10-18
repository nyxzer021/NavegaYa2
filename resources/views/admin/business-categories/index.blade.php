@extends('layouts.admin')
@section('header')
    <h1 class="ny-page-title">Categorías de negocios</h1>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-4">
    <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-950 via-emerald-900 to-slate-900 px-5 py-4 text-white shadow-md sm:px-6">
        <p class="text-[10px] font-black uppercase tracking-[.18em] text-amber-300">Datos maestros del marketplace</p>
        <div class="mt-1 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-black sm:text-2xl">Categorías y subcategorías comerciales</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-emerald-50/80">Administra las opciones que podrán seleccionar los hoteles, restaurantes, tours y servicios al crear su ficha comercial.</p>
            </div>
            <div class="flex gap-3 text-center">
                <div class="min-w-20 rounded-xl bg-white/10 px-3 py-2 ring-1 ring-white/15"><strong class="block text-lg">{{$categoriesCount}}</strong><span class="text-[10px] text-emerald-100/75">Categorías</span></div>
                <div class="min-w-20 rounded-xl bg-white/10 px-3 py-2 ring-1 ring-white/15"><strong class="block text-lg">{{$activeCategoriesCount}}</strong><span class="text-[10px] text-emerald-100/75">Activas</span></div>
            </div>
        </div>
    </section>

    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">✓ {{session('success')}}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800">{{$errors->first()}}</div>@endif

    <section class="space-y-4">
        <form method="POST" action="{{route('admin.business-categories.store')}}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            @csrf
            <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Nuevo registro</p><h3 class="text-base font-black text-slate-950">Crear categoría</h3></div>
                <p class="text-xs text-slate-500">Completa los datos y agrégala al listado maestro.</p>
            </div>
            <div class="mt-3 grid gap-2.5 lg:grid-cols-12 lg:items-end">
                <label class="block text-xs font-bold text-slate-700 lg:col-span-3">Grupo comercial<select name="listing_type" required class="mt-1 w-full rounded-lg border-slate-300 py-2 text-xs">@foreach($types as $key=>$label)<option value="{{$key}}">{{$label}}</option>@endforeach</select></label>
                <label class="block text-xs font-bold text-slate-700 lg:col-span-3">Nombre<input name="name" required maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-xs" placeholder="Ej. Lodge ecológico"></label>
                <label class="block text-xs font-bold text-slate-700 lg:col-span-1">Ícono<input name="icon" maxlength="12" class="mt-1 w-full rounded-lg border-slate-300 py-1.5 text-center text-base" placeholder="🛖"></label>
                <label class="block text-xs font-bold text-slate-700 lg:col-span-3">Código<input name="code" required maxlength="60" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-xs" placeholder="lodge_ecologico"></label>
                <label class="block text-xs font-bold text-slate-700 lg:col-span-2">Orden<input type="number" name="sort_order" value="99" min="1" max="999" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-xs"></label>
                <label class="block text-xs font-bold text-slate-700 lg:col-span-7">Descripción<input name="description" maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-xs" placeholder="Descripción breve de la categoría"></label>
                <label class="flex h-9 items-center gap-2 rounded-lg bg-slate-50 px-3 text-xs font-bold text-slate-700 lg:col-span-3"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">Disponible</label>
                <button class="h-9 rounded-lg bg-emerald-800 px-3 text-xs font-black text-white shadow-sm hover:bg-emerald-900 lg:col-span-2">Crear categoría</button>
            </div>
        </form>

        <div class="space-y-4">
            <div class="flex items-end justify-between px-1"><div><h3 class="text-base font-black text-slate-950">Categorías registradas</h3><p class="text-xs text-slate-500">Listado maestro disponible para los negocios.</p></div><span class="hidden text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:block">{{$categories->total()}} registros</span></div>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden grid-cols-[52px_minmax(180px,1.2fr)_minmax(190px,1fr)_80px_170px] gap-3 border-b bg-slate-50 px-4 py-2.5 text-[10px] font-black uppercase tracking-wider text-slate-500 lg:grid"><span>Ícono</span><span>Categoría</span><span>Grupo comercial</span><span>Estado</span><span class="text-right">Acciones</span></div>
            <div class="divide-y divide-slate-100">
            @forelse($categories as $category)
                <article>
                    <div class="grid gap-3 px-4 py-3 lg:grid-cols-[52px_minmax(180px,1.2fr)_minmax(190px,1fr)_80px_170px] lg:items-center">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-xl ring-1 ring-emerald-100">{{$category->icon ?: '🏪'}}</div>
                        <div class="min-w-0"><h4 class="truncate text-sm font-black text-slate-950">{{$category->name}}</h4><p class="mt-0.5 truncate font-mono text-[11px] text-slate-500">{{$category->code}} · orden {{$category->sort_order}}</p></div>
                        <div><p class="text-xs font-bold text-slate-700">{{$types[$category->listing_type] ?? $category->listing_type}}</p><p class="mt-0.5 text-[11px] text-slate-500">{{$category->listings_count}} ficha(s) vinculada(s)</p></div>
                        <div><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-black {{$category->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'}}">{{$category->is_active ? 'Activa' : 'Inactiva'}}</span></div>
                        <div class="flex items-center gap-2 lg:justify-end">
                            <button type="button" onclick="document.getElementById('edit-category-{{$category->id}}').classList.toggle('hidden')" class="rounded-lg border border-emerald-700 px-3 py-2 text-xs font-black text-emerald-800 hover:bg-emerald-50">Editar</button>
                            <form method="POST" action="{{route('admin.business-categories.destroy',$category)}}" onsubmit="return confirm('¿Eliminar la categoría {{$category->name}}? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button @disabled($category->listings_count > 0) title="{{$category->listings_count > 0 ? 'No se puede eliminar porque tiene fichas vinculadas' : 'Eliminar categoría'}}" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-black text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-300">Eliminar</button></form>
                        </div>
                    </div>
                    <div id="edit-category-{{$category->id}}" class="hidden border-t border-emerald-100 bg-emerald-50/40 px-5 py-5">
                    <form method="POST" action="{{route('admin.business-categories.update',$category)}}">
                        @csrf @method('PATCH')
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block text-sm font-bold text-slate-700">Nombre<input name="name" value="{{$category->name}}" required class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm"></label>
                                <label class="block text-sm font-bold text-slate-700">Grupo comercial<select name="listing_type" class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm">@foreach($types as $key=>$label)<option value="{{$key}}" @selected($category->listing_type===$key)>{{$label}}</option>@endforeach</select></label>
                                <label class="block text-sm font-bold text-slate-700">Ícono<input name="icon" value="{{$category->icon}}" maxlength="12" class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm"></label>
                                <label class="block text-sm font-bold text-slate-700">Orden<input type="number" name="sort_order" value="{{$category->sort_order}}" min="1" max="999" class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm"></label>
                            </div>
                            <label class="mt-4 block text-sm font-bold text-slate-700">Descripción<textarea name="description" rows="2" class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm">{{$category->description}}</textarea></label>
                            <input type="hidden" name="code" value="{{$category->code}}">
                            <div class="mt-4 flex flex-col gap-3 border-t border-emerald-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <label class="flex items-center gap-3 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">Disponible en formularios</label>
                                <div class="flex gap-2"><button type="button" onclick="document.getElementById('edit-category-{{$category->id}}').classList.add('hidden')" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-700">Cancelar</button><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-black text-white hover:bg-emerald-800">Guardar cambios</button></div>
                            </div>
                    </form>
                    </div>
                </article>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">Todavía no hay categorías registradas.</div>
            @endforelse
            </div>
            </div>
            @if($categories->hasPages())<div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">{{$categories->links()}}</div>@endif
        </div>
    </section>
</div>
@endsection
