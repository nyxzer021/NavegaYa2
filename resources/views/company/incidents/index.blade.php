@extends('layouts.company')
@section('title', 'Incidencias')
@section('page-title', 'Incidencias')
@section('content')
@php
    $kinds = ['delay' => 'Retraso', 'breakdown' => 'Avería', 'weather' => 'Clima', 'river_level' => 'Nivel del río', 'other' => 'Otro'];
    $severities = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'];
    $actions = ['record' => 'Solo registrar', 'reschedule' => 'Reprogramar', 'cancel' => 'Cancelar'];
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <p class="max-w-3xl text-sm text-slate-500">Reprogramar o cancelar avisa a los pasajeros y usa el mismo control de cruces de la embarcación y de la tripulación. Las fotos quedan en un archivo privado.</p>
    </section>
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Nueva incidencia</h3>
        <form method="POST" action="{{ route('company.incidents.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Salida
                <select name="departure_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="">Selecciona una salida</option>
                    @foreach($departures as $departure)
                        <option value="{{ $departure->id }}">{{ $departure->departure_at->copy()->timezone('America/Lima')->format('d/m/Y H:i') }} · {{ $departure->transportRoute->originPort?->city }} → {{ $departure->transportRoute->destinationPort?->city }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Tipo
                <select name="kind" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    @foreach($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Gravedad
                <select name="severity" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    @foreach($severities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Descripción<textarea name="description" required minlength="5" rows="3" class="mt-1 w-full rounded-lg border-slate-200 px-3 py-2 text-sm">{{ old('description') }}</textarea></label>
            <label class="text-sm font-semibold text-slate-700">Acción
                <select name="action" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    @foreach($actions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Nueva hora, si reprogramas<input type="datetime-local" name="departure_at" value="{{ old('departure_at') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Fotos<input type="file" name="photos[]" accept="image/*" multiple class="mt-1 block w-full text-sm"></label>
            <div class="sm:col-span-2"><button class="inline-flex h-10 items-center rounded-lg bg-amber-400 px-4 text-sm font-black text-emerald-950">Registrar incidencia</button></div>
        </form>
        <div class="mt-6 space-y-3">
            @forelse($incidents as $incident)
                <article class="rounded-xl border border-slate-100 px-4 py-3 text-sm">
                    <strong>{{ $kinds[$incident->kind] ?? $incident->kind }}</strong>
                    <span class="text-slate-500"> · {{ $severities[$incident->severity] ?? $incident->severity }} · {{ $actions[$incident->action] ?? $incident->action }}</span>
                    <p class="mt-1 text-slate-700">{{ $incident->description }}</p>
                    @if($incident->photos->isNotEmpty())
                        <p class="mt-2 flex flex-wrap gap-3">
                            @foreach($incident->photos as $photo)
                                <a href="{{ route('company.incidents.photos.show', $photo) }}" class="font-bold text-emerald-800">{{ $photo->original_name ?: 'Foto' }}</a>
                            @endforeach
                        </p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-500">Todavía no hay incidencias.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
