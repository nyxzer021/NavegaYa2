@extends('layouts.admin')

@section('header')
    <h1 class="ny-page-title">Gestión de Aeronaves</h1>
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex items-center gap-2 text-xs">
        <a href="{{ route('admin.companies.show', ['organization' => $organization->id, 'tab' => 'flota']) }}"
           class="font-bold text-emerald-700 hover:underline">
            ← Volver al expediente de {{ $organization->commercial_name ?: $organization->legal_name }}
        </a>
    </div>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-[#062c21] p-6 text-white">
            <span class="inline-block rounded border border-cyan-400/30 bg-cyan-500/20 px-2 py-0.5 text-[10px] font-bold uppercase text-cyan-300">
                Transporte Aéreo Regional
            </span>
            <h1 class="mt-2 text-xl font-black text-white">
                {{ $creating ? 'Registrar Nueva Aeronave' : 'Editar Aeronave' }}
            </h1>
            <p class="mt-1 text-xs text-emerald-100/70">
                Operador asignado:
                <strong>{{ $organization->commercial_name ?: $organization->legal_name }}</strong>
                · RUC {{ $organization->ruc }}
            </p>
        </div>

        @if ($errors->any())
            <div class="m-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
                <p class="font-black">Revisa los datos ingresados:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ $creating ? route('admin.aircraft.store') : route('admin.aircraft.update', $aircraft) }}"
              class="space-y-6 p-6">
            @csrf
            @unless($creating)
                @method('PATCH')
            @endunless

            <input type="hidden" name="organization_id" value="{{ $organization->id }}">

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-xs font-bold text-slate-700">Nombre de la unidad *</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $aircraft->name) }}" placeholder="Ej. Selva Caravan I" required
                           class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-900 outline-none focus:border-emerald-600 focus:bg-white focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label for="registration_number" class="mb-1 block text-xs font-bold text-slate-700">Matrícula oficial DGAC *</label>
                    <input id="registration_number" type="text" name="registration_number" value="{{ old('registration_number', $aircraft->registration_number) }}" placeholder="Ej. OB-2140" required
                           class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-xs font-mono font-bold uppercase text-slate-900 outline-none focus:border-emerald-600 focus:bg-white focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label for="model" class="mb-1 block text-xs font-bold text-slate-700">Modelo / fabricante</label>
                    <input id="model" type="text" name="model" value="{{ old('model', $aircraft->model) }}" placeholder="Ej. Cessna 208 Grand Caravan"
                           class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-900 outline-none focus:border-emerald-600 focus:bg-white focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label for="seat_capacity" class="mb-1 block text-xs font-bold text-slate-700">Capacidad de asientos *</label>
                    <input id="seat_capacity" type="number" name="seat_capacity" value="{{ old('seat_capacity', $aircraft->seat_capacity ?: 9) }}" min="1" max="900" required
                           class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-xs font-bold text-slate-900 outline-none focus:border-emerald-600 focus:bg-white focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label for="status" class="mb-1 block text-xs font-bold text-slate-700">Estado operativo</label>
                    <select id="status" name="status"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-900 outline-none focus:border-emerald-600 focus:bg-white">
                        <option value="ready" @selected(old('status', $aircraft->status ?: 'ready') === 'ready')>🟢 Lista para operar</option>
                        <option value="maintenance" @selected(old('status', $aircraft->status) === 'maintenance')>🟡 En mantenimiento</option>
                        <option value="inactive" @selected(old('status', $aircraft->status) === 'inactive')>🔴 Fuera de servicio</option>
                    </select>
                </div>

                <div>
                    <label for="cover_image_path" class="mb-1 block text-xs font-bold text-slate-700">URL de imagen</label>
                    <input id="cover_image_path" type="url" name="cover_image_path" value="{{ old('cover_image_path', $aircraft->cover_image_path) }}" placeholder="https://..."
                           class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-900 outline-none focus:border-emerald-600 focus:bg-white">
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-1 block text-xs font-bold text-slate-700">Descripción</label>
                    <textarea id="description" name="description" rows="3" placeholder="Características, servicios o información técnica relevante."
                              class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-900 outline-none focus:border-emerald-600 focus:bg-white">{{ old('description', $aircraft->description) }}</textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <a href="{{ route('admin.companies.show', ['organization' => $organization->id, 'tab' => 'flota']) }}"
                   class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50">
                    Cancelar
                </a>
                <button type="submit" class="rounded-xl bg-amber-500 px-6 py-2.5 text-xs font-black text-slate-950 shadow-sm transition hover:bg-amber-600">
                    {{ $creating ? 'Guardar Aeronave' : 'Actualizar Aeronave' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
