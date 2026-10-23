<x-app-layout>
<div class="mx-auto max-w-7xl space-y-4">
    <section class="rounded-3xl bg-gradient-to-r from-emerald-950 to-slate-900 p-6 text-white">
        <p class="text-[10px] font-black uppercase tracking-[.2em] text-amber-300">Seguridad de acceso</p>
        <h1 class="mt-1 font-black">Administradores y permisos</h1>
        <p class="mt-1 text-xs text-emerald-100/70">Aquí aparecen únicamente las cuentas que administran la plataforma. Viajeros y anunciantes se gestionan en sus propios módulos.</p>
    </section>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-3 text-xs font-bold text-emerald-800">✓ {{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-800">{{ $errors->first() }}</div>@endif

    <nav class="flex gap-1 overflow-x-auto rounded-2xl border bg-white p-1.5" aria-label="Secciones de usuarios y permisos">
        @foreach([['overview','Resumen'],['create','Nuevo administrador'],['manage','Administrar accesos']] as [$key,$label])
            <a href="{{ route('admin.users.index',['tab'=>$key]) }}" class="whitespace-nowrap rounded-lg px-4 py-2 text-xs font-black {{ $activeTab===$key?'bg-emerald-800 text-white':'text-slate-500 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($activeTab==='overview')
        <section class="grid gap-3 md:grid-cols-3">
            <article class="rounded-2xl border bg-white p-5"><span class="text-[10px] font-black uppercase text-slate-400">Administradores</span><strong class="mt-1 block text-2xl">{{ $users->total() }}</strong></article>
            <article class="rounded-2xl border bg-white p-5"><span class="text-[10px] font-black uppercase text-slate-400">Acceso total</span><strong class="mt-1 block text-2xl">{{ $users->getCollection()->filter(fn($u)=>$u->roles->contains('code','super_admin'))->count() }}</strong></article>
            <article class="rounded-2xl border bg-white p-5"><span class="text-[10px] font-black uppercase text-slate-400">Módulos asignables</span><strong class="mt-1 block text-2xl">{{ $permissions->flatten()->count() }}</strong></article>
        </section>
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><h2 class="text-sm font-black text-amber-950">¿Qué se administra aquí?</h2><p class="mt-1 text-xs text-amber-800">Crea personal administrativo y marca solo los módulos que necesita. Cada tarjeta indica exactamente qué opciones del menú habilita.</p></section>
    @elseif($activeTab==='create')
        <section class="rounded-2xl border bg-white p-5">
            <h2 class="text-lg font-black">Crear administrador</h2>
            <p class="mt-1 text-xs text-slate-500">Completa la identidad de acceso y selecciona por lo menos un módulo.</p>
            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-5">@csrf
                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    @foreach([['name','Nombre completo','text'],['email','Correo institucional','email'],['password','Contraseña segura','password'],['password_confirmation','Confirmar contraseña','password']] as [$name,$label,$type])
                        <label class="text-xs font-black">{{ $label }}<input required type="{{ $type }}" name="{{ $name }}" value="{{ $type==='password'?'':old($name) }}" class="mt-1.5 w-full"></label>
                    @endforeach
                    <label class="text-xs font-black">Empresa vinculada<select name="organization_id" class="mt-1.5 w-full"><option value="">Acceso general</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->commercial_name ?: $organization->legal_name }}</option>@endforeach</select></label>
                </div>
                @include('admin.users.permission-grid',['selectedPermissionIds'=>collect(old('permissions',[]))->map(fn($id)=>(int)$id)])
                <button class="mt-5 rounded-lg bg-amber-400 px-4 py-2.5 text-xs font-black text-slate-950">Crear administrador</button>
            </form>
        </section>
    @else
        <section class="grid gap-4 lg:grid-cols-[300px_1fr]">
            <aside class="rounded-2xl border bg-white p-4">
                <div class="flex items-center justify-between"><h2 class="text-sm font-black">Administradores</h2><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold">{{ $users->total() }}</span></div>
                <div class="mt-3 max-h-[430px] space-y-1.5 overflow-y-auto">
                    @forelse($users as $user)
                        <a href="{{ route('admin.users.index',['tab'=>'manage','user'=>$user->id]) }}" class="block rounded-lg border p-2.5 {{ $selectedUser?->id===$user->id?'border-emerald-700 bg-emerald-50':'border-slate-200 hover:bg-slate-50' }}"><strong class="text-xs">{{ $user->name }}</strong><small class="mt-0.5 block text-[10px] text-slate-400">{{ $user->email }}</small><span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600">{{ $user->roles->contains('code','super_admin')?'Acceso total':'Administrador' }}</span></a>
                    @empty<p class="py-8 text-center text-xs text-slate-400">No hay administradores.</p>@endforelse
                </div>
                <div class="mt-3">{{ $users->links() }}</div>
            </aside>
            <article class="rounded-2xl border bg-white p-5">
                @if(!$selectedUser)
                    <div class="py-14 text-center text-slate-400"><span class="text-3xl">👤</span><p class="mt-2 text-xs">Selecciona un administrador para revisar sus accesos.</p></div>
                @elseif($selectedUser->roles->contains('code','super_admin'))
                    <h2 class="text-lg font-black">{{ $selectedUser->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $selectedUser->email }}</p><div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-bold text-amber-900">Cuenta principal protegida. Tiene acceso total y no puede eliminarse desde este panel.</div>
                @else
                    <h2 class="text-lg font-black">{{ $selectedUser->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $selectedUser->email }}</p>
                    <form method="POST" action="{{ route('admin.users.update',$selectedUser) }}" class="mt-4">@csrf @method('PATCH')
                        <label class="text-xs font-black">Empresa vinculada<select name="organization_id" class="mt-1.5 w-full"><option value="">Acceso general</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($selectedUser->organizations->contains('id',$organization->id))>{{ $organization->commercial_name ?: $organization->legal_name }}</option>@endforeach</select></label>
                        @include('admin.users.permission-grid',['selectedPermissionIds'=>$selectedUser->permissions->pluck('id')])
                        <button class="mt-5 rounded-lg bg-emerald-800 px-4 py-2.5 text-xs font-black text-white">Guardar permisos</button>
                    </form>
                    <form method="POST" action="{{ route('admin.users.destroy',$selectedUser) }}" class="mt-2" onsubmit="return confirm('¿Eliminar este acceso administrativo?')">@csrf @method('DELETE')<button class="rounded-lg bg-rose-50 px-4 py-2.5 text-xs font-black text-rose-700">Eliminar acceso</button></form>
                @endif
            </article>
        </section>
    @endif
</div>
</x-app-layout>