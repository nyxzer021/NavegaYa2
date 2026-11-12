@extends('layouts.company')
@section('title', 'Programación de salidas')
@section('page-title', isset($departureRecord) && $departureRecord ? 'Editar salida' : 'Programar salida')
@section('content')
@php
    $editing = isset($departureRecord) && $departureRecord;
    $moment = function (string $dateName, string $timeName, ?string $column) use ($editing, $departureRecord): array {
        $date = old($dateName);
        $time = old($timeName);
        if (! $date && ! $time && $editing && $departureRecord->{$column}) {
            $local = $departureRecord->{$column}->copy()->timezone('America/Lima');
            $date = $local->format('Y-m-d');
            $time = $local->format('H:i');
        }

        return [$date, $time];
    };
    [$departureDate, $departureTime] = $moment('departure_date', 'departure_time', 'departure_at');
    [$boardingDate, $boardingTime] = $moment('boarding_date', 'boarding_time', 'boarding_starts_at');
    [$arrivalDate, $arrivalTime] = $moment('arrival_date', 'arrival_time', 'estimated_arrival_at');
    [$cargoDate, $cargoTime] = $moment('cargo_date', 'cargo_time', 'cargo_reception_starts_at');
@endphp
<div class="ny-schedule mx-auto max-w-3xl">
    <a class="text-sm font-bold text-emerald-800" href="{{ route('company.departures.index') }}">← Volver a salidas</a>
    <form class="mt-3 overflow-hidden rounded-2xl border border-[#d5e6df] bg-white shadow-sm" method="POST" action="{{ $editing ? route('company.departures.update', $departureRecord) : route('company.departures.store') }}" data-fee="{{ $organization->commission_rate ?? 0 }}">
        @csrf
        @if($editing) @method('PATCH') @endif
        <div class="border-t-4 border-[#fbbf24] px-5 py-5 sm:px-6">
            <h2 class="text-xl font-black text-slate-950">{{ $editing ? 'Editar salida y tarifa' : 'Programar nueva salida' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Tramo, embarcación, horario y precio.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-800 sm:col-span-2">Tramo
                    <select name="master_route_id" required>
                        <option value="">Selecciona una ruta</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}" @selected(old('master_route_id', $selectedRouteId) == $route->id)>{{ $route->code }} · {{ $route->origin_city }} → {{ $route->destination_city }}</option>
                        @endforeach
                    </select>
                    @error('master_route_id')<span class="mt-1 block text-xs font-semibold text-rose-700">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm font-semibold text-slate-800 sm:col-span-2">Embarcación
                    <select name="vessel_id" required>
                        <option value="">Selecciona una unidad</option>
                        @foreach($vessels as $vessel)
                            <option value="{{ $vessel->id }}" @selected(old('vessel_id', $departureRecord->vessel_id ?? null) == $vessel->id)>{{ $vessel->name }} · {{ $vessel->seat_capacity }} asientos</option>
                        @endforeach
                    </select>
                    @error('vessel_id')<span class="mt-1 block text-xs font-semibold text-rose-700">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm font-semibold text-slate-800">Fecha de salida
                    <input type="date" name="departure_date" required value="{{ $departureDate }}">
                </label>
                <label class="text-sm font-semibold text-slate-800">Hora de zarpe
                    <input type="time" name="departure_time" required value="{{ $departureTime }}">
                    @error('departure_at')<span class="mt-1 block text-xs font-semibold text-rose-700">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm font-semibold text-slate-800">Embarque
                    <span class="mt-1 grid grid-cols-2 gap-2">
                        <input type="date" name="boarding_date" required value="{{ $boardingDate }}">
                        <input type="time" name="boarding_time" required value="{{ $boardingTime }}">
                    </span>
                    <span class="mt-1 block text-xs font-medium text-slate-500">Se completa 45 minutos antes del zarpe.</span>
                    @error('boarding_starts_at')<span class="mt-1 block text-xs font-semibold text-rose-700">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm font-semibold text-slate-800">Llegada estimada
                    <span class="mt-1 grid grid-cols-2 gap-2">
                        <input type="date" name="arrival_date" value="{{ $arrivalDate }}">
                        <input type="time" name="arrival_time" value="{{ $arrivalTime }}">
                    </span>
                    <span class="mt-1 block text-xs font-medium text-slate-500">Se completa 3 horas después. Fecha y hora.</span>
                    @error('estimated_arrival_at')<span class="mt-1 block text-xs font-semibold text-rose-700">{{ $message }}</span>@enderror
                </label>
                @unless($editing)
                    <label class="text-sm font-semibold text-slate-800">Programación
                        <select name="schedule_kind">
                            <option value="single" @selected(old('schedule_kind', 'single') === 'single')>Salida única</option>
                            <option value="series" @selected(old('schedule_kind') === 'series')>Recurrente</option>
                        </select>
                    </label>
                    <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2" data-series-fields>
                        <div class="sm:col-span-2 text-sm font-semibold text-slate-800">Días
                            <span class="mt-1 flex flex-wrap gap-2 font-medium">
                                @foreach([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $day => $label)
                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="weekdays[]" value="{{ $day }}" @checked(in_array($day, array_map('intval', (array) old('weekdays', [])), true))>{{ $label }}</label>
                                @endforeach
                            </span>
                        </div>
                        <label class="text-sm font-semibold text-slate-800">Desde<input type="date" name="starts_on" value="{{ old('starts_on') }}"></label>
                        <label class="text-sm font-semibold text-slate-800">Hasta<input type="date" name="ends_on" value="{{ old('ends_on') }}"></label>
                        <p class="sm:col-span-2 text-xs text-slate-500">Máximo 90 días. La hora es la de Lima. Si una fecha se cruza con otra salida de la misma unidad, no se crea.</p>
                    </div>
                @endunless
                <label class="text-sm font-semibold text-slate-800">Precio del pasaje
                    <span class="ny-money"><span>S/</span><input type="number" name="fare" required min="0.01" step="0.01" value="{{ old('fare', $departureRecord->fare ?? null) }}" placeholder="0.00"></span>
                    @error('fare')<span class="mt-1 block text-xs font-semibold text-rose-700">{{ $message }}</span>@enderror
                    <span class="mt-1 block text-xs font-semibold text-emerald-800">El viajero web pagará S/ <span data-web-total>—</span>.</span>
                </label>
                <label class="text-sm font-semibold text-slate-800">Nota para pasajeros
                    <input name="notes" value="{{ old('notes', $departureRecord->notes ?? null) }}" placeholder="Presentarse 30 minutos antes">
                </label>
            </div>
            <details class="mt-4 rounded-xl border border-emerald-100 bg-[#ecfdf5]" {{ old('cargo_enabled', $departureRecord->cargo_enabled ?? false) ? 'open' : '' }}>
                <summary class="cursor-pointer list-none px-4 py-3 text-sm font-bold text-emerald-950">Encomienda en esta salida <span class="float-right text-xs font-semibold text-emerald-700">Opcional</span></summary>
                <div class="grid gap-3 border-t border-emerald-100 px-4 py-3 sm:grid-cols-2">
                    <p class="text-xs text-emerald-800 sm:col-span-2">Los precios Pequeño, Mediano y Grande se editan en Ficha pública.</p>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="checkbox" name="cargo_enabled" value="1" @checked(old('cargo_enabled', $departureRecord->cargo_enabled ?? false))> Recibir carga</label>
                    <label class="text-sm font-semibold text-slate-800">Inicio de recepción
                        <span class="mt-1 grid grid-cols-2 gap-2"><input type="date" name="cargo_date" value="{{ $cargoDate }}"><input type="time" name="cargo_time" value="{{ $cargoTime }}"></span>
                    </label>
                    <label class="text-sm font-semibold text-slate-800 sm:col-span-2">Indicaciones
                        <input name="cargo_notes" value="{{ old('cargo_notes', $departureRecord->cargo_notes ?? null) }}" placeholder="Condiciones o restricciones">
                    </label>
                </div>
            </details>
            @if($errors->any())
                <div class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif
        </div>
        <footer class="flex items-center justify-end gap-3 border-t border-slate-100 px-5 py-3 sm:px-6">
            <a href="{{ route('company.departures.index') }}" class="inline-flex h-10 items-center px-3 text-sm font-bold text-slate-500">Cancelar</a>
            <button class="inline-flex h-10 items-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">{{ $editing ? 'Guardar datos y tarifa' : 'Publicar salida' }}</button>
        </footer>
    </form>
</div>
<style>
    .ny-schedule label { display: grid; gap: 6px; }
    .ny-schedule input, .ny-schedule select {
        height: 40px !important; min-height: 40px !important; width: 100%;
        padding: 0 12px !important; border: 1px solid #d5e6df !important; border-radius: 12px !important;
        background: #fff !important; color: #173b33; font-size: 14px; font-weight: 600; box-shadow: none !important;
    }
    .ny-schedule input:focus, .ny-schedule select:focus { border-color: #087669 !important; box-shadow: 0 0 0 4px rgba(242,182,36,.28) !important; outline: 0 !important; }
    .ny-schedule input[type="date"], .ny-schedule input[type="time"] { cursor: pointer; }
    .ny-money { display: flex; height: 40px; align-items: center; gap: 8px; border-radius: 12px; background: #fffbeb; padding: 0 12px; border: 1px solid #fbbf24; }
    .ny-money span { font-weight: 800; color: #92400e; }
    .ny-money input { height: 24px !important; min-height: 0 !important; border: 0 !important; background: transparent !important; padding: 0 !important; box-shadow: none !important; }
    .ny-schedule summary::-webkit-details-marker { display: none; }
</style>
<script>
    (function () {
        var form = document.querySelector('.ny-schedule');
        if (!form) return;
        form.querySelectorAll('input[type="date"], input[type="time"]').forEach(function (input) {
            input.addEventListener('click', function () {
                if (typeof input.showPicker === 'function') {
                    try { input.showPicker(); } catch (error) {}
                }
            });
        });
        var departureDate = form.querySelector('[name="departure_date"]');
        var departureTime = form.querySelector('[name="departure_time"]');
        var boardingDate = form.querySelector('[name="boarding_date"]');
        var boardingTime = form.querySelector('[name="boarding_time"]');
        var arrivalDate = form.querySelector('[name="arrival_date"]');
        var arrivalTime = form.querySelector('[name="arrival_time"]');
        function pad(part) { return String(part).padStart(2, '0'); }
        function stamp(date, time) { return date && time ? new Date(date + 'T' + time) : null; }
        function write(dateInput, timeInput, date) {
            dateInput.value = date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
            timeInput.value = pad(date.getHours()) + ':' + pad(date.getMinutes());
        }
        function fill() {
            var departure = stamp(departureDate.value, departureTime.value);
            if (!departure || Number.isNaN(departure.getTime())) return;
            var boarding = stamp(boardingDate.value, boardingTime.value);
            var arrival = stamp(arrivalDate.value, arrivalTime.value);
            if (!boarding || boarding.getTime() > departure.getTime()) write(boardingDate, boardingTime, new Date(departure.getTime() - 45 * 60000));
            if (!arrival || arrival.getTime() <= departure.getTime()) write(arrivalDate, arrivalTime, new Date(departure.getTime() + 180 * 60000));
        }
        departureDate.addEventListener('change', fill);
        departureTime.addEventListener('change', fill);
        var fare = form.querySelector('[name="fare"]');
        var total = form.querySelector('[data-web-total]');
        var percent = Number((form.querySelector('form') || {}).dataset?.fee || 0);
        function preview() {
            if (!fare || !total) return;
            var base = Math.round(Number(fare.value || 0) * 100) / 100;
            var fee = Math.round(base * percent) / 100;
            total.textContent = (Math.round((base + fee) * 100) / 100).toFixed(2);
        }
        fare?.addEventListener('input', preview);
        preview();
        var kind = form.querySelector('[name="schedule_kind"]');
        var series = form.querySelector('[data-series-fields]');
        function toggleSeries() {
            if (!kind || !series) return;
            series.hidden = kind.value !== 'series';
        }
        kind?.addEventListener('change', toggleSeries);
        toggleSeries();
    }());
</script>
@endsection
