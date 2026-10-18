@extends('layouts.admin')
@section('header')
    <h1 class="ny-page-title">Categorías de negocios</h1>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-950 via-emerald-900 to-slate-900 px-6 py-7 text-white shadow-lg sm:px-8">
        <p class="text-xs font-black uppercase tracking-[.18em] text-amber-300">Datos maestros del marketplace</p>
        <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-black sm:text-3xl">Categorías y subcategorías comerciales</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-emerald-50/80">Administra las opciones que podrán seleccionar los hoteles, restaurantes, tours y servicios al crear su ficha comercial.</p>
            </div>
            <div class="flex gap-3 text-center">
                <div class="min-w-24 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><strong class="block text-2xl">{{$categories->count()}}</strong><span class="text-xs text-emerald-100/75">Categorías</span></div>
                <div class="min-w-24 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><strong class="block text-2xl">{{$categories->where('is_active', true)->count()}}</strong><span class="text-xs text-emerald-100/75">Activas</span></div>
            </div>
        </div>
    </section>

    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">✓ {{session('success')}}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800">{{$errors->first()}}</div>@endif

    <section class="grid items-start gap-6 xl:grid-cols-[390px_minmax(0,1fr)]">
        <form method="POST" action="{{route('admin.business-categories.store')}}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:sticky xl:top-6">
            @csrf
            <div class="border-b border-slate-100 pb-4">
                <p class="text-xs font-black uppercase tracking-wider text-emerald-700">Nuevo registro</p>
                <h3 class="mt-1 text-xl font-black text-slate-950">Crear categoría</h3>
                <p class="mt-1 text-sm leading-5 text-slate-500">Aparecerá como opción en el panel de los negocios.</p>
            </div>
            <div class="mt-5 space-y-4">
                <label class="block text-sm font-bold text-slate-700">Grupo comercial<select name="listing_type" required class="mt-2 w-full rounded-xl border-slate-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">@foreach($types as $key=>$label)<option value="{{$key}}">{{$label}}</option>@endforeach</select></label>
                <label class="block text-sm font-bold text-slate-700">Nombre<input name="name" required maxlength="100" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="Ej. Lodge ecológico"></label>
                <div class="grid grid-cols-[100px_1fr] gap-3">
                    <label class="block text-sm font-bold text-slate-700">Ícono<input name="icon" maxlength="12" class="mt-2 w-full rounded-xl border-slate-300 text-center text-lg" placeholder="🛖"></label>
                    <label class="block text-sm font-bold text-slate-700">Código<input name="code" required maxlength="60" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="lodge_ecologico"></label>
                </div>
                <label class="block text-sm font-bold text-slate-700">Descripción<textarea name="description" rows="3" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="Explica qué negocios pertenecen a esta categoría."></textarea></label>
                <label class="block text-sm font-bold text-slate-700">Orden de aparición<input type="number" name="sort_order" value="99" min="1" max="999" class="mt-2 w-full rounded-xl border-slate-300 text-sm"></label>
                <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">Disponible en formularios</label>
            </div>
            <button class="mt-5 w-full rounded-xl bg-emerald-800 px-4 py-3.5 text-sm font-black text-white shadow-sm transition hover:bg-emerald-900">Crear categoría</button>
        </form>

        <div class="space-y-4">
            <div class="flex items-end justify-between px-1"><div><h3 class="text-lg font-black text-slate-950">Categorías registradas</h3><p class="text-sm text-slate-500">Listado maestro disponible para los negocios.</p></div><span class="hidden text-xs font-bold uppercase tracking-wider text-slate-400 sm:block">{{$categories->count()}} registros</span></div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden grid-cols-[64px_minmax(180px,1.2fr)_minmax(190px,1fr)_90px_190px] gap-4 border-b bg-slate-50 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-500 lg:grid"><span>Ícono</span><span>Categoría</span><span>Grupo comercial</span><span>Estado</span><span class="text-right">Acciones</span></div>
            <div class="divide-y divide-slate-100">
            @forelse($categories as $category)
                <article>
                    <div class="grid gap-4 px-5 py-4 lg:grid-cols-[64px_minmax(180px,1.2fr)_minmax(190px,1fr)_90px_190px] lg:items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-2xl ring-1 ring-emerald-100">{{$category->icon ?: '🏪'}}</div>
                        <div class="min-w-0"><h4 class="truncate text-base font-black text-slate-950">{{$category->name}}</h4><p class="mt-1 truncate font-mono text-xs text-slate-500">{{$category->code}} · orden {{$category->sort_order}}</p></div>
                        <div><p class="text-sm font-bold text-slate-700">{{$types[$category->listing_type] ?? $category->listing_type}}</p><p class="mt-1 text-xs text-slate-500">{{$category->listings_count}} ficha(s) vinculada(s)</p></div>
                        <div><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black {{$category->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'}}">{{$category->is_active ? 'Activa' : 'Inactiva'}}</span></div>
                        <div class="flex items-center gap-2 lg:justify-end">
                            <button type="button" onclick="document.getElementById('edit-category-{{$category->id}}').classList.toggle('hidden')" class="rounded-lg border border-emerald-700 px-4 py-2.5 text-sm font-black text-emerald-800 hover:bg-emerald-50">Editar</button>
                            <form method="POST" action="{{route('admin.business-categories.destroy',$category)}}" onsubmit="return confirm('¿Eliminar la categoría {{$category->name}}? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button @disabled($category->listings_count > 0) title="{{$category->listings_count > 0 ? 'No se puede eliminar porque tiene fichas vinculadas' : 'Eliminar categoría'}}" class="rounded-lg border border-rose-200 px-3 py-2.5 text-sm font-black text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-300">Eliminar</button></form>
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
        </div>
    </section>
</div>
@endsection
