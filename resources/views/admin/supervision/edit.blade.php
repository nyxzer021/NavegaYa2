@extends('layouts.admin')

@section('header')
    <div>
        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Inventario comercial</p>
        <h1 class="ny-page-title">Editar salida {{ $type === 'aereo' ? 'aérea' : 'fluvial' }}</h1>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="overflow-hidden rounded-3xl bg-[#062c21] p-6 text-white shadow-lg sm:p-8">
            <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                <div>
                    <span class="inline-flex rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-200">
                        {{ $type === 'aereo' ? '✈️ Operación aérea' : '🚤 Operación fluvial' }}
                    </span>
                    <h2 class="mt-3 text-2xl font-black">{{ $organization->commercial_name ?? $organization->legal_name ?? 'Empresa transportista' }}</h2>
                    <p class="mt-1 text-sm text-emerald-100/70">RUC: {{ $organization->ruc ?? 'No registrado' }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-sm">
                    <span class="block text-[10px] font-bold uppercase text-emerald-200/70">Código de salida</span>
                    <strong>{{ $departure->code ?? '#'.$departure->id }}</strong>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <strong>Revisa los datos ingresados.</strong>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.itineraries.update', $departure->id) }}" class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @method('PUT')
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="grid gap-6 md:grid-cols-2">
                <label class="space-y-2 md:col-span-2">
                    <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Tramo maestro</span>
                    <select name="master_route_id" required class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 text-sm font-semibold text-slate-800 focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="">Selecciona un tramo oficial</option>
                        @foreach ($masterRoutes as $masterRoute)
                            <option value="{{ $masterRoute->id }}" @selected(old('master_route_id', $selectedMasterRouteId) == $masterRoute->id)>
                                {{ $masterRoute->code }} · {{ $masterRoute->origin_city }} ➔ {{ $masterRoute->destination_city }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">{{ $type === 'aereo' ? 'Aeronave asignada' : 'Nave asignada' }}</span>
                    <select name="vessel_id" required class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 text-sm font-semibold text-slate-800 focus:border-emerald-600 focus:ring-emerald-600">
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected(old('vessel_id', $selectedVehicleId) == $vehicle->id)>{{ $vehicle->name }} · {{ $vehicle->registration_number }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400">Solo se muestran unidades registradas por esta empresa.</p>
                </label>

                <label class="space-y-2">
                    <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Fecha y hora de salida</span>
                    <input type="datetime-local" name="departure_time" required value="{{ old('departure_time', $departure->departure_at?->format('Y-m-d\TH:i')) }}" class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 text-sm font-semibold focus:border-emerald-600 focus:ring-emerald-600">
                </label>

                <label class="space-y-2">
                    <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Tarifa base (PEN)</span>
                    <div class="relative"><span class="absolute left-4 top-3.5 text-sm font-black text-slate-500">S/</span><input type="number" name="price" required min="0" step="0.01" value="{{ old('price', $departure->fare) }}" class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 pl-11 text-sm font-bold focus:border-emerald-600 focus:ring-emerald-600"></div>
                </label>

                <label class="space-y-2">
                    <span class="text-[11px] font-black uppercase tracking-wide text-slate-500">Estado del viaje</span>
                    <select name="status" required class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 text-sm font-semibold focus:border-emerald-600 focus:ring-emerald-600">
                        @php($currentStatus = $departure->status === 'departed' ? 'in_transit' : $departure->status)
                        @foreach ($statusOptions as $value => $label)<option value="{{ $value }}" @selected(old('status', $currentStatus) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>

            <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.itineraries.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</a>
                <button class="inline-flex h-11 items-center justify-center rounded-xl bg-amber-500 px-6 text-xs font-black text-slate-950 shadow-sm hover:bg-amber-600">Actualizar salida</button>
            </div>
        </form>
    </div>
@endsection
