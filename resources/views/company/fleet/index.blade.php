@extends('layouts.company')
@section('title','Flota')
@section('page-title','Flota')
@section('content')
@php
    $usesApproval = $organization->modalities()->exists();
    $hasRiver = $usesApproval ? $organization->hasModality('fluvial') : in_array($organization->modality ?: 'fluvial', ['fluvial', 'mixto'], true);
    $hasAir = $usesApproval ? ($organization->hasModality('aereo') || $organization->hasModality('aereo_fluvial')) : in_array($organization->modality ?: 'fluvial', ['aereo', 'mixto'], true);
    $hasSeaplane = $usesApproval ? $organization->hasModality('aereo_fluvial') : $organization->modality === 'mixto';
    $firstTab = $hasRiver ? 'boats' : ($hasAir ? 'planes' : 'boats');
@endphp
<style>[x-cloak]{display:none}</style>
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
<div x-data="{ tab: '{{ $firstTab }}', step: 1 }" class="space-y-6">
<nav class="flex gap-2 overflow-x-auto" aria-label="Flota">
    @if($hasRiver)<button type="button" @click="tab='boats'" :class="tab==='boats' ? 'bg-[#062c21] text-white' : 'bg-white text-slate-700'" class="h-9 rounded-lg border border-slate-200 px-3 text-sm font-bold">{{ __('company_portal.fleet.boats', [], 'es') }}</button>@endif
    @if($hasAir)<button type="button" @click="tab='planes'" :class="tab==='planes' ? 'bg-[#062c21] text-white' : 'bg-white text-slate-700'" class="h-9 rounded-lg border border-slate-200 px-3 text-sm font-bold">{{ __('company_portal.fleet.planes', [], 'es') }}</button>@endif
    @if($hasSeaplane)<button type="button" @click="tab='seaplanes'" :class="tab==='seaplanes' ? 'bg-[#062c21] text-white' : 'bg-white text-slate-700'" class="h-9 rounded-lg border border-slate-200 px-3 text-sm font-bold">{{ __('company_portal.fleet.seaplanes', [], 'es') }}</button>@endif
</nav>
<div x-show="tab==='boats'" x-cloak class="space-y-6">
@if($hasRiver)
<form method="POST" action="{{ route('company.fleet.vessels.store') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    @csrf
    <h3 class="text-lg font-black text-slate-950">Nueva embarcación</h3>
    <p class="mt-1 max-w-2xl text-sm text-slate-500">Tres pasos: datos, capacidad y documentos. El plano se dibuja al guardar.</p>
    <p class="mt-3 text-sm font-bold text-emerald-800"><span x-text="step"></span>/3</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-show="step===1">
        <label class="text-sm font-semibold text-slate-700">Nombre de la nave<input required name="name" value="{{ old('name') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Matrícula DICAPI<input required name="registration_number" value="{{ old('registration_number') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Tipo<select required name="vessel_type" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"><option value="rapido">Rápida</option><option value="lancha">Lancha</option><option value="motonave">Motonave</option><option value="ferry">Ferry</option><option value="otro">Otra</option></select></label>
        <label class="text-sm font-semibold text-slate-700">Asientos que puede vender<input required type="number" min="1" max="2000" name="seat_capacity" value="{{ old('seat_capacity') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"><span class="mt-1 block text-sm font-normal text-slate-500">Es el tope de las dos cubiertas juntas.</span></label>
        <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Puerto base<select name="base_port_id" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"><option value="">Sin puerto base</option>@foreach($ports as $port)<option value="{{ $port->id }}" @selected(old('base_port_id') == $port->id)>{{ $port->optionLabel() }}</option>@endforeach</select></label>
        <label class="text-sm font-semibold text-slate-700">Eslora (m)<input type="number" step="0.01" min="0" name="length_m" value="{{ old('length_m') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Motor<input name="engine_description" value="{{ old('engine_description') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
    </div>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-show="step===2" x-cloak>
        <label class="text-sm font-semibold text-slate-700">Chalecos<input type="number" min="0" name="life_vest_count" value="{{ old('life_vest_count') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Carga máxima (t)<input type="number" step="0.01" min="0" name="cargo_capacity_tons" value="{{ old('cargo_capacity_tons') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
    </div>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-show="step===3" x-cloak>
        <label class="text-sm font-semibold text-slate-700">Certificado DICAPI<input name="dicapi_certificate_number" value="{{ old('dicapi_certificate_number') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Seguro<input name="insurance_policy" value="{{ old('insurance_policy') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Vence el seguro<input type="date" name="insurance_expires_at" value="{{ old('insurance_expires_at') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Vence la inspección<input type="date" name="inspection_expires_at" value="{{ old('inspection_expires_at') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
    </div>
    <div class="mt-4 flex gap-2">
        <button type="button" x-show="step>1" @click="step--" class="inline-flex h-10 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold">Atrás</button>
        <button type="button" x-show="step<3" @click="step++" class="inline-flex h-10 items-center rounded-xl bg-emerald-700 px-4 text-sm font-bold text-white">Siguiente</button>
        <button x-show="step===3" class="inline-flex h-10 items-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Guardar y diseñar el plano</button>
    </div>
</form>
@include('company.fleet._vessels')
@endif
</div>
@if($hasAir)
<div x-show="tab==='planes' || tab==='seaplanes'" x-cloak class="space-y-6">
<form method="POST" action="{{ route('company.fleet.aircraft.store') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    @csrf
    <h3 class="text-lg font-black text-slate-950">Nueva aeronave</h3>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-sm font-semibold text-slate-700">Nombre comercial<input required name="name" value="{{ old('name') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Matrícula<input required name="registration_number" value="{{ old('registration_number') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Modelo<input name="model" value="{{ old('model') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Tipo<select name="aircraft_kind" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"><option value="light_aircraft">{{ __('company_portal.fleet.light_aircraft', [], 'es') }}</option>@if($hasSeaplane)<option value="seaplane">{{ __('company_portal.fleet.seaplane', [], 'es') }}</option>@endif</select></label>
        <label class="text-sm font-semibold text-slate-700">Asientos<input required type="number" min="1" max="900" name="seat_capacity" value="{{ old('seat_capacity') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Peso máximo de despegue (kg)<input type="number" step="0.01" min="0" name="max_takeoff_weight_kg" value="{{ old('max_takeoff_weight_kg') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Carga útil (kg)<input type="number" step="0.01" min="0" name="useful_load_kg" value="{{ old('useful_load_kg') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        <label class="text-sm font-semibold text-slate-700">Equipaje máximo por pasajero (kg)<input type="number" step="0.01" min="0" name="max_baggage_kg_per_passenger" value="{{ old('max_baggage_kg_per_passenger') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
    </div>
    <button class="mt-4 inline-flex h-10 items-center rounded-xl bg-[#062c21] px-5 text-sm font-bold text-white">Registrar aeronave</button>
</form>
<section class="space-y-4">
    @forelse($aircraft as $unit)
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" x-show="(tab==='seaplanes' && '{{ $unit->aircraft_kind }}'==='seaplane') || (tab==='planes' && '{{ $unit->aircraft_kind }}'!=='seaplane')">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h4 class="text-lg font-black text-slate-950">{{ $unit->name }}</h4>
                    <p class="text-sm text-slate-500">{{ $unit->registration_number }} · {{ __('company_portal.fleet.'.($unit->aircraft_kind ?: 'light_aircraft'), [], 'es') }} · {{ $unit->seat_capacity }} asientos</p>
                </div>
                <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ ['ready'=>'Lista','pending_review'=>'En revisión','changes_requested'=>'Con observaciones','rejected'=>'Rechazada','draft'=>'Borrador','suspended'=>'Suspendida'][$unit->status] ?? 'En registro' }}</span>
            </div>
            @if(\App\Support\FleetPlan::missingText($unit))
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm font-bold text-amber-950">{{ \App\Support\FleetPlan::missingText($unit) }}</p>
            @elseif(\App\Support\FleetPlan::drawn($unit) > $unit->seat_capacity)
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-950">{{ __('company_portal.fleet.compliance_warning', [], 'es') }}</p>
            @endif
            <a href="{{ route('company.aircraft.seats',$unit) }}" class="mt-3 inline-flex text-sm font-bold text-sky-800">Configurar plano de asientos</a>
            @include('company.fleet._maintenance', ['unit' => $unit, 'action' => route('company.fleet.aircraft.maintenance.store', $unit)])
        </article>
    @empty
        <p class="text-sm text-slate-500">Aún no hay aeronaves.</p>
    @endforelse
</section>
</div>
@endif
</div>
</div>
@endsection
