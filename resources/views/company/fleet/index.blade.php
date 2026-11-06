@extends('layouts.company')
@section('title','Flota')
@section('page-title','Flota')
@section('content')
@php
    $usesApproval = $organization->modalities()->exists();
    $hasRiver = $usesApproval ? $organization->hasModality('fluvial') : in_array($organization->modality ?: 'fluvial', ['fluvial', 'mixto'], true);
    $hasAir = $usesApproval ? ($organization->hasModality('aereo') || $organization->hasModality('aereo_fluvial')) : in_array($organization->modality ?: 'fluvial', ['aereo', 'mixto'], true);
@endphp
<div class="mx-auto max-w-7xl space-y-6">
<section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <p class="text-sm font-semibold text-emerald-800">{{ $hasRiver && $hasAir ? 'Lanchas y avionetas' : ($hasAir ? 'Avionetas' : 'Lanchas y motonaves') }}</p>
    <h2 class="mt-1 text-2xl font-black text-slate-950">Registro y configuración de flota</h2>
    <p class="mt-1 text-sm text-slate-500">Habilita lo que opera tu empresa. Después registra cada unidad y diseña su plano.</p>
</section>
<section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h3 class="text-lg font-black text-slate-950">Qué transportas</h3>
    <p class="mt-1 text-sm text-slate-500">Puedes tener las dos. Quitar una solo es posible si todavía no tiene unidades registradas.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach(['fluvial' => ['Lanchas y motonaves', 'Matrícula DICAPI y plano de asientos.', $hasRiver, $vessels->isNotEmpty()], 'aereo' => ['Avionetas', 'Matrícula DGAC y asientos de la aeronave.', $hasAir, $aircraft->isNotEmpty()]] as $transport => [$title, $copy, $enabled, $inUse])
            @if($usesApproval && ! $enabled) @continue @endif
            <article class="rounded-2xl border border-slate-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="font-black text-slate-950">{{ $title }}</h4>
                        <p class="mt-1 text-sm text-slate-500">{{ $copy }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $enabled ? 'Habilitado' : 'Sin habilitar' }}</span>
                </div>
                <div class="mt-4">
                    @if(! $enabled)
                        <form method="POST" action="{{ route('company.fleet.transport.update') }}">@csrf @method('PATCH')<input type="hidden" name="transport" value="{{ $transport }}"><input type="hidden" name="enabled" value="1"><button class="inline-flex min-h-10 items-center rounded-xl bg-amber-400 px-4 text-sm font-black text-emerald-950">Habilitar {{ $transport === 'aereo' ? 'avionetas' : 'lanchas' }}</button></form>
                    @elseif($inUse)
                        <p class="text-sm text-slate-500">En uso. Sigue disponible mientras haya unidades registradas.</p>
                    @elseif($hasRiver && $hasAir)
                        <form method="POST" action="{{ route('company.fleet.transport.update') }}">@csrf @method('PATCH')<input type="hidden" name="transport" value="{{ $transport }}"><input type="hidden" name="enabled" value="0"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700">Quitar</button></form>
                    @else
                        <p class="text-sm text-slate-500">Es tu operación actual. Puedes sumar la otra.</p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800">{{ session('success') }}</div>@endif
@if($hasRiver)
<form method="POST" action="{{ route('company.fleet.vessels.store') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    @csrf
    <h3 class="text-lg font-black text-slate-950">Nueva embarcación</h3>
    <p class="mt-1 max-w-2xl text-sm text-slate-500">Primero anota la nave y cuántos asientos puede vender. Después dibujas las filas a cada lado del pasillo, sin pasar ese número.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label class="text-sm font-semibold text-slate-700">Nombre de la nave<input required name="name" value="{{ old('name') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Matrícula DICAPI<input required name="registration_number" value="{{ old('registration_number') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Tipo<select required name="vessel_type" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"><option value="rapido">Rápida</option><option value="lancha">Lancha</option><option value="motonave">Motonave</option><option value="ferry">Ferry</option><option value="otro">Otra</option></select></label>
        <label class="text-sm font-semibold text-slate-700">Asientos que puede vender<input required type="number" min="1" max="2000" name="seat_capacity" value="{{ old('seat_capacity') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"><span class="mt-1 block text-sm font-normal text-slate-500">Es el tope de las dos cubiertas juntas.</span></label>
        <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Puerto base<select name="base_port_id" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"><option value="">Sin puerto base</option>@foreach($ports as $port)<option value="{{ $port->id }}" @selected(old('base_port_id') == $port->id)>{{ $port->optionLabel() }}</option>@endforeach</select></label>
    </div>
    <button class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Guardar y diseñar el plano</button>
</form>
@endif
@if($hasAir)
<form method="POST" action="{{ route('company.fleet.aircraft.store') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    @csrf
    <h3 class="text-lg font-black text-slate-950">Nueva aeronave</h3>
    <p class="mt-1 text-sm text-slate-500">Registra la avioneta y sus asientos. No comparte el plano de las lanchas.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-sm font-semibold text-slate-700">Nombre comercial<input required name="name" value="{{ old('name') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Matrícula DGAC<input required name="registration_number" value="{{ old('registration_number') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Modelo<input name="model" value="{{ old('model') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Asientos<input required type="number" min="1" max="900" name="seat_capacity" value="{{ old('seat_capacity') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
    </div>
    <button class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[#062c21] px-5 text-sm font-bold text-white">Registrar aeronave</button>
</form>
@endif
@if($hasRiver)@include('company.fleet._vessels')@endif
@if($hasAir)<section><div class="mb-3"><span class="text-sm font-semibold text-sky-800">DGAC</span><h3 class="text-lg font-black text-slate-900">Aeronaves</h3></div><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">@forelse($aircraft as $unit)<article class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm"><div class="flex justify-between"><span class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-50 text-sky-800"><x-nav-icon name="plane" class="h-5 w-5" /></span><span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ ['ready'=>'Lista','pending_review'=>'En revisión','changes_requested'=>'Con observaciones','rejected'=>'Rechazada','draft'=>'Borrador'][$unit->status] ?? 'En registro' }}</span></div><h4 class="mt-4 font-black text-slate-900">{{ $unit->name }}</h4><p class="text-xs text-slate-500">{{ $unit->model??'Modelo no registrado' }} · {{ $unit->registration_number }}</p><strong class="mt-4 block text-sm">{{ $unit->seat_capacity }} asientos</strong><div class="mt-4"><div class="mb-2 flex items-center justify-between text-xs font-semibold text-slate-500"><span>Plano configurado</span><span>{{ $unit->seats->count() }}/{{ $unit->seat_capacity }}</span></div><div class="grid grid-cols-8 gap-1">@foreach($unit->seats->take(24) as $seat)<i class="h-3 rounded-sm {{ $seat->is_available ? 'bg-sky-500' : 'bg-slate-200' }}" title="{{ $seat->code }}"></i>@endforeach</div></div><a href="{{ route('company.aircraft.seats',$unit) }}" class="mt-4 flex items-center justify-between rounded-xl bg-sky-50 px-3 py-2.5 text-xs font-black text-sky-900">Configurar plano de asientos <span>→</span></a></article>@empty<p class="text-sm text-slate-500">Aún no hay aeronaves.</p>@endforelse</div></section>@endif
</div>
@endsection
