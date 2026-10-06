@extends('layouts.company')
@section('title','Programación de salidas')
@section('page-title','Programación de salidas')
@section('content')
@php
 $editing = isset($departureRecord) && $departureRecord;
 $dateValue = fn ($name) => old($name, $editing && $departureRecord->{$name} ? $departureRecord->{$name}->format('Y-m-d\TH:i') : null);
@endphp
<div class="mx-auto max-w-5xl px-4 py-7 sm:px-6">
 <a class="text-xs font-bold text-emerald-800" href="{{ route('company.departures.index') }}">← Volver a mis salidas</a>
 <form class="mt-4 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" method="POST" action="{{ $editing ? route('company.departures.update',$departureRecord) : route('company.departures.store') }}">
  @csrf @if($editing) @method('PATCH') @endif
  <div class="bg-[#062c21] px-6 py-6 text-white sm:px-8"><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Gestión de itinerario</span><h1 class="mt-1 text-2xl font-black">{{ $editing ? 'Editar o reprogramar salida' : 'Programar nueva salida' }}</h1><p class="mt-1 text-xs text-emerald-100/70">Selecciona un tramo oficial aprobado por NavegaYA, tu unidad y los datos comerciales de la salida.</p></div>
  <div class="grid gap-5 p-6 sm:grid-cols-2 sm:p-8">
   <label class="text-xs font-bold text-slate-700">Tramo maestro oficial<select name="master_route_id" required class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-emerald-700 focus:ring-emerald-700"><option value="">Selecciona un tramo autorizado</option>@foreach($routes as $route)<option value="{{ $route->id }}" @selected(old('master_route_id',$selectedRouteId)==$route->id)>{{ $route->code }} · {{ $route->origin_city }} ({{ $route->originPort->name }}) → {{ $route->destination_city }} ({{ $route->destinationPort->name }})</option>@endforeach</select></label>
   <label class="text-xs font-bold text-slate-700">Embarcación lista<select name="vessel_id" required class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-emerald-700 focus:ring-emerald-700"><option value="">Selecciona una embarcación</option>@foreach($vessels as $vessel)<option value="{{ $vessel->id }}" @selected(old('vessel_id',$departureRecord->vessel_id ?? null)==$vessel->id)>{{ $vessel->name }} · {{ $vessel->organization->commercial_name ?: $vessel->organization->legal_name }}</option>@endforeach</select></label>
   <label class="text-xs font-bold text-slate-700">Hora de partida<input type="datetime-local" name="departure_at" required value="{{ $dateValue('departure_at') }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Inicio de embarque<input type="datetime-local" name="boarding_starts_at" required value="{{ $dateValue('boarding_starts_at') }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Llegada estimada<input type="datetime-local" name="estimated_arrival_at" value="{{ $dateValue('estimated_arrival_at') }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Tarifa por pasajero (S/)<input type="number" name="fare" required min="0.01" step="0.01" value="{{ old('fare',$departureRecord->fare ?? null) }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 sm:col-span-2" x-data="{cargo:{{ old('cargo_enabled',$departureRecord->cargo_enabled ?? false)?'true':'false' }}}">
    <label class="flex items-center gap-2 text-sm font-bold text-emerald-950"><input type="checkbox" name="cargo_enabled" value="1" x-model="cargo" class="rounded border-emerald-300 text-emerald-700 focus:ring-emerald-600"> Esta salida recibe carga y equipaje adicional</label>
    <div x-show="cargo" class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-700">Inicio de recepción<input type="datetime-local" name="cargo_reception_starts_at" value="{{ $dateValue('cargo_reception_starts_at') }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-white text-sm"></label><label class="text-xs font-bold text-slate-700">Equipaje incluido<input value="25 kg" readonly class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-100 text-sm text-slate-500"></label><label class="sm:col-span-2 text-xs font-bold text-slate-700">Indicaciones<textarea name="cargo_notes" class="mt-1.5 min-h-20 w-full rounded-xl border-slate-200 bg-white text-sm">{{ old('cargo_notes',$departureRecord->cargo_notes ?? null) }}</textarea></label></div>
   </div>
   <label class="sm:col-span-2 text-xs font-bold text-slate-700">Notas operativas<input name="notes" value="{{ old('notes',$departureRecord->notes ?? null) }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm" placeholder="Ej. presentarse 30 minutos antes"></label>
   @if($errors->any())<div class="sm:col-span-2 rounded-xl bg-rose-50 p-4 text-xs text-rose-700"><ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
   <div class="flex justify-end sm:col-span-2"><button class="h-11 rounded-xl bg-amber-500 px-6 text-xs font-black text-slate-950 shadow-sm hover:bg-amber-600">{{ $editing ? 'Guardar reprogramación' : 'Programar salida' }}</button></div>
  </div>
 </form>
</div>
@endsection
