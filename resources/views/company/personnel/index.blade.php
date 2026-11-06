@extends('layouts.company')
@section('title', 'Usuarios')
@section('page-title', 'Usuarios')
@section('content')
<div class="mx-auto max-w-6xl space-y-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-2xl font-black text-slate-950">Equipo de muelle</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Estos usuarios solo pueden despachar pasajeros y consultar manifiestos de {{ $organization->commercial_name ?: $organization->legal_name }}.</p>
            </div>
            <button type="button" @click="open = !open" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Nuevo counter</button>
        </div>
    </section>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form x-show="open" x-cloak method="POST" action="{{ route('company.staff.store') }}" class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
        @csrf
        <label class="text-sm font-semibold text-slate-700">Nombre completo<input required name="name" value="{{ old('name') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
        <label class="text-sm font-semibold text-slate-700">Correo<input required type="email" name="email" value="{{ old('email') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
        <label class="text-sm font-semibold text-slate-700">DNI / documento<input required name="document_number" value="{{ old('document_number') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
        <label class="text-sm font-semibold text-slate-700">Teléfono<input required name="phone" value="{{ old('phone') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
        <label class="text-sm font-semibold text-slate-700">Contraseña temporal<input required type="password" name="password" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
        <label class="text-sm font-semibold text-slate-700">Confirmar contraseña<input required type="password" name="password_confirmation" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
        <button class="h-11 rounded-xl bg-[#062c21] px-5 text-sm font-bold text-white sm:col-span-2 lg:col-span-3">Crear acceso</button>
    </form>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-5">
            <h3 class="text-lg font-black text-slate-950">Usuarios del counter</h3>
            <p class="mt-1 text-sm text-slate-500">{{ \App\Support\SpanishCount::phrase($counterStaff->count(), 'acceso', 'accesos') }}</p>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($counterStaff as $staff)
                @php($membership = $staff->organizations->first()?->pivot)
                <article class="space-y-3 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <strong class="text-slate-950">{{ $staff->name }}</strong>
                            <p class="text-sm text-slate-500">{{ $staff->email }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $membership?->status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $membership?->status === 'active' ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $staff->document_number ?: 'Sin documento' }} · {{ $staff->phone ?: 'Sin teléfono' }}</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('company.staff.toggle', $staff) }}">@csrf @method('PATCH')<button class="min-h-10 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700">{{ $membership?->status === 'active' ? 'Deshabilitar' : 'Habilitar' }}</button></form>
                        <form method="POST" action="{{ route('company.staff.destroy', $staff) }}">@csrf @method('DELETE')<button class="min-h-10 rounded-xl border border-rose-200 px-4 text-sm font-bold text-rose-700">Retirar</button></form>
                    </div>
                </article>
            @empty
                <div class="px-5 py-14 text-center">
                    <strong class="block text-slate-800">Todavía no hay usuarios counter</strong>
                    <p class="mt-1 text-sm text-slate-500">Crea un acceso para el personal que despacha en el muelle.</p>
                </div>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Usuario</th>
                        <th class="px-4 py-3">Documento</th>
                        <th class="px-4 py-3">Teléfono</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-5 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($counterStaff as $staff)
                        @php($membership = $staff->organizations->first()?->pivot)
                        <tr>
                            <td class="px-5 py-4"><strong class="text-slate-950">{{ $staff->name }}</strong><span class="block text-sm text-slate-500">{{ $staff->email }}</span></td>
                            <td class="px-4 py-4 text-slate-700">{{ $staff->document_number }}</td>
                            <td class="px-4 py-4 text-slate-700">{{ $staff->phone }}</td>
                            <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $membership?->status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $membership?->status === 'active' ? 'Activo' : 'Inactivo' }}</span></td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('company.staff.toggle', $staff) }}">@csrf @method('PATCH')<button class="min-h-10 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700">{{ $membership?->status === 'active' ? 'Deshabilitar' : 'Habilitar' }}</button></form>
                                    <form method="POST" action="{{ route('company.staff.destroy', $staff) }}">@csrf @method('DELETE')<button class="min-h-10 rounded-xl border border-rose-200 px-4 text-sm font-bold text-rose-700">Retirar</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-14 text-center text-sm text-slate-500">Todavía no hay usuarios counter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
