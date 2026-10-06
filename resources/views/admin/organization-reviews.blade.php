<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Solicitudes de empresas y agencias</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="mb-5 rounded-lg bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="mb-5 rounded-lg bg-red-50 p-4 text-red-800">{{ session('error') }}</div>@endif
        <details class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" @if($errors->any()) open @endif>
            <summary class="cursor-pointer bg-[#062c21] px-6 py-4 text-sm font-bold text-white">＋ Provisionar empresa manualmente</summary>
            <form method="POST" action="{{ route('admin.organizations.provision') }}" class="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">@csrf
                <label class="text-xs font-bold text-slate-600">Razón social<input name="company_name" required value="{{ old('company_name') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Nombre comercial<input name="commercial_name" value="{{ old('commercial_name') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">RUC<input name="ruc" required maxlength="11" value="{{ old('ruc') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Modalidad<select name="modality" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="fluvial">Fluvial</option><option value="aereo">Aérea</option><option value="mixto">Mixta</option></select></label>
                <label class="text-xs font-bold text-slate-600">Ciudad base<input name="base_city" required value="{{ old('base_city','Iquitos') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Comisión NavegaYA (%)<input type="number" step=".01" min="0" max="100" name="commission_rate" required value="{{ old('commission_rate','8.00') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Administrador raíz<input name="admin_name" required value="{{ old('admin_name') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Correo de acceso<input type="email" name="admin_email" required value="{{ old('admin_email') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Teléfono<input name="phone" value="{{ old('phone') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Contraseña inicial<input type="password" name="admin_password" required class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Confirmar contraseña<input type="password" name="admin_password_confirmation" required class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-bold text-slate-600">Dirección / base<input name="address" value="{{ old('address') }}" class="mt-1 block h-10 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                @if($errors->any())<div class="rounded-xl bg-rose-50 p-3 text-xs text-rose-700 sm:col-span-2 lg:col-span-3">{{ $errors->first() }}</div>@endif
                <div class="sm:col-span-2 lg:col-span-3"><button class="rounded-xl bg-amber-500 px-5 py-3 text-xs font-black text-slate-950 hover:bg-amber-600">Crear empresa y administrador</button></div>
            </form>
        </details>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-[#12372d] text-white"><tr><th class="px-5 py-3 text-left">Empresa</th><th class="px-5 py-3 text-left">RUC</th><th class="px-5 py-3 text-left">Contacto</th><th class="px-5 py-3 text-left">Correo confirmado</th><th class="px-5 py-3 text-right">Acciones</th></tr></thead>
            <tbody class="divide-y divide-gray-100">@forelse($organizations as $organization)<tr><td class="px-5 py-4"><strong>{{ $organization->legal_name }}</strong><br><span class="text-gray-500">{{ $organization->type === 'agency' ? 'Agencia' : 'Empresa de transporte' }}</span></td><td class="px-5 py-4">{{ $organization->ruc }}</td><td class="px-5 py-4">{{ $organization->contact_name }}<br><span class="text-gray-500">{{ $organization->email }}</span></td><td class="px-5 py-4">{{ $organization->contact_verified_at ? 'Sí' : 'Pendiente' }}</td><td class="px-5 py-4"><div class="flex items-center justify-end gap-2 whitespace-nowrap"><form method="POST" action="{{ route('admin.organizations.approve',$organization) }}">@csrf @method('PATCH')<button type="submit" class="rounded bg-emerald-600 px-3 py-2 text-white">Aprobar</button></form><form method="POST" action="{{ route('admin.organizations.reject',$organization) }}">@csrf @method('PATCH')<button type="submit" class="rounded bg-red-600 px-3 py-2 text-white">Rechazar</button></form></div></td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No hay solicitudes pendientes.</td></tr>@endforelse</tbody></table>
        </div>
    </div></div>
</x-app-layout>

