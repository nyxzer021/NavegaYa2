<section class="mt-5">
    <div class="flex flex-wrap items-end justify-between gap-2"><div><h3 class="text-sm font-black">Módulos y opciones habilitadas</h3><p class="mt-0.5 text-[11px] text-slate-500">Marca el cuadro del módulo. Las etiquetas muestran las opciones del menú incluidas.</p></div><span class="text-[10px] font-bold text-slate-400">Principio de mínimo privilegio</span></div>
    <div class="mt-3 grid gap-2 md:grid-cols-2">
        @foreach($permissions->flatten() as $permission)
            <label class="group flex cursor-pointer gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:border-emerald-400 hover:bg-emerald-50/40">
                <input class="permission-check mt-0.5" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($selectedPermissionIds->contains($permission->id))>
                <span class="min-w-0"><strong class="block text-xs text-slate-900">{{ $permission->name }}</strong><small class="mt-0.5 block text-[10px] leading-4 text-slate-500">{{ $permission->description }}</small><span class="mt-2 flex flex-wrap gap-1">@foreach($permissionPages[$permission->code] ?? [] as $page)<span class="rounded-md bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 group-hover:bg-white">{{ $page }}</span>@endforeach</span></span>
            </label>
        @endforeach
    </div>
</section>