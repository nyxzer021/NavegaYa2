@extends('layouts.company')
@section('title', 'Tarifas y descuentos')
@section('page-title', 'Tarifas y descuentos')
@section('content')
@php
    $spaces = ['seat' => 'Asiento', 'hammock' => 'Hamaca', 'cabin_bed' => 'Camarote'];
    $passengers = ['adult' => 'Adulto', 'child' => 'Niño', 'student' => 'Estudiante', 'senior' => 'Adulto mayor', 'infant' => 'Infante'];
    $channels = ['web' => 'Web', 'counter' => 'Terminal', 'agency' => 'Agencia'];
    $routeLabel = function ($fare): string {
        if ($fare->transportRoute) {
            return ($fare->transportRoute->originPort?->city ?: '—').' → '.($fare->transportRoute->destinationPort?->city ?: '—');
        }
        return ($fare->airRoute?->origin_city ?: '—').' → '.($fare->airRoute?->destination_city ?: '—');
    };
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-2xl font-black text-slate-950">Tarifas y descuentos</h2>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">El precio se define por ruta, clase y tipo de pasajero. Si una hamaca o un camarote no tienen tarifa, esa clase no se vende. El asiento de adulto, si no tiene tarifa propia, usa el precio de la salida.</p>
    </section>

    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h3 class="text-lg font-black text-slate-950">Equipaje incluido</h3>
                <p class="mt-1 text-sm text-slate-500">Mano {{ $baggage['hand'] }} kg · bodega {{ $baggage['hold'] }} kg · aéreo {{ $baggage['air'] }} kg · carga desde {{ $baggage['cargo'] }} kg. Se edita en un solo lugar.</p>
            </div>
            <a href="{{ route('company.policies.index') }}" class="inline-flex h-9 items-center rounded-lg bg-emerald-800 px-3 text-sm font-bold text-white">Políticas de viaje</a>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Nueva tarifa</h3>
        <form method="POST" action="{{ route('company.fares.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Ruta
                <select name="route" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="">Selecciona una ruta</option>
                    @foreach($riverRoutes as $route)
                        <option value="river-{{ $route->id }}" @selected(old('route') === 'river-'.$route->id)>{{ $route->originPort?->city }} → {{ $route->destinationPort?->city }}</option>
                    @endforeach
                    @foreach($airRoutes as $route)
                        <option value="air-{{ $route->id }}" @selected(old('route') === 'air-'.$route->id)>{{ $route->origin_city }} → {{ $route->destination_city }} · Aéreo</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Clase
                <select name="space_type" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    @foreach($spaces as $value => $label)
                        <option value="{{ $value }}" @selected(old('space_type', 'seat') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Pasajero
                <select name="passenger_type" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    @foreach($passengers as $value => $label)
                        <option value="{{ $value }}" @selected(old('passenger_type', 'adult') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Precio (S/)
                <input required type="number" min="0" step="0.01" name="amount" value="{{ old('amount') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Desde
                <input type="date" name="starts_on" value="{{ old('starts_on') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Hasta
                <input type="date" name="ends_on" value="{{ old('ends_on') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="includes_meal" value="1" @checked(old('includes_meal')) class="h-4 w-4 rounded border-slate-300"> Incluye alimentación</label>
            <div class="sm:col-span-2"><button class="inline-flex h-10 items-center rounded-lg bg-amber-400 px-4 text-sm font-black text-emerald-950">Guardar tarifa</button></div>
        </form>
        <p class="mt-3 text-sm text-slate-500">Sin fechas, la tarifa vale todo el año. Dos temporadas de la misma ruta, clase y pasajero no pueden cruzarse.</p>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Ruta</th><th>Clase</th><th>Pasajero</th><th>Precio</th><th>Temporada</th><th></th></tr></thead>
                <tbody>
                @forelse($fares as $fare)
                    <tr class="border-t border-slate-100">
                        <td class="py-3">{{ $routeLabel($fare) }}</td>
                        <td>{{ $spaces[$fare->space_type] ?? $fare->space_type }}</td>
                        <td>{{ $passengers[$fare->passenger_type] ?? $fare->passenger_type }}</td>
                        <td>S/ {{ number_format((float) $fare->amount, 2) }}@if($fare->includes_meal) · con comida @endif</td>
                        <td>{{ $fare->starts_on ? $fare->starts_on->format('d/m/Y') : 'Todo el año' }}@if($fare->ends_on) – {{ $fare->ends_on->format('d/m/Y') }}@endif</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('company.fares.destroy', $fare) }}">@csrf @method('DELETE')<button class="text-sm font-bold text-rose-700">Eliminar</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-slate-500">Todavía no hay tarifas. El asiento de adulto sigue usando el precio de cada salida.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Nuevo descuento</h3>
        <form method="POST" action="{{ route('company.discounts.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Nombre
                <input required name="name" value="{{ old('name') }}" maxlength="120" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Tipo
                <select name="kind" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="percent" @selected(old('kind', 'percent') === 'percent')>Porcentaje</option>
                    <option value="amount" @selected(old('kind') === 'amount')>Monto fijo</option>
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Valor
                <input required type="number" min="0" step="0.01" name="value" value="{{ old('value') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <fieldset class="text-sm font-semibold text-slate-700">
                <legend>Canales</legend>
                <div class="mt-2 flex flex-wrap gap-4 font-medium">
                    @foreach($channels as $value => $label)
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="channels[]" value="{{ $value }}" @checked(in_array($value, old('channels', ['web']), true)) class="h-4 w-4 rounded border-slate-300"> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <label class="text-sm font-semibold text-slate-700">Usos máximos
                <input type="number" min="1" name="usage_limit" value="{{ old('usage_limit') }}" placeholder="Sin límite" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Desde
                <input type="date" name="starts_on" value="{{ old('starts_on') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">Hasta
                <input type="date" name="ends_on" value="{{ old('ends_on') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="requires_document" value="1" @checked(old('requires_document')) class="h-4 w-4 rounded border-slate-300"> Pide documento</label>
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Rutas
                <select name="routes[]" multiple class="mt-1 min-h-24 w-full rounded-lg border-slate-200 px-3 py-2 text-sm">
                    @foreach($riverRoutes as $route)
                        <option value="river-{{ $route->id }}">{{ $route->originPort?->city }} → {{ $route->destinationPort?->city }}</option>
                    @endforeach
                    @foreach($airRoutes as $route)
                        <option value="air-{{ $route->id }}">{{ $route->origin_city }} → {{ $route->destination_city }} · Aéreo</option>
                    @endforeach
                </select>
            </label>
            <p class="text-sm text-slate-500 sm:col-span-2">Si no eliges rutas, el descuento aplica a todas las de la empresa. El precio nunca baja de S/ 0.00 y se redondea a céntimos. Si el boleto se cancela o se reembolsa, el uso vuelve al contador.</p>
            <div class="sm:col-span-2"><button class="inline-flex h-10 items-center rounded-lg bg-amber-400 px-4 text-sm font-black text-emerald-950">Guardar descuento</button></div>
        </form>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Nombre</th><th>Valor</th><th>Canales</th><th>Usos</th><th></th></tr></thead>
                <tbody>
                @forelse($discounts as $discount)
                    <tr class="border-t border-slate-100">
                        <td class="py-3">{{ $discount->name }}</td>
                        <td>{{ $discount->kind === 'percent' ? rtrim(rtrim(number_format((float) $discount->value, 2), '0'), '.').' %' : 'S/ '.number_format((float) $discount->value, 2) }}</td>
                        <td>{{ collect($discount->channels)->map(fn ($channel) => $channels[$channel] ?? $channel)->implode(', ') }}</td>
                        <td>{{ $discount->redeemed_count }}{{ $discount->usage_limit ? ' / '.$discount->usage_limit : '' }}</td>
                        <td class="text-right"><form method="POST" action="{{ route('company.discounts.destroy', $discount) }}">@csrf @method('DELETE')<button class="text-sm font-bold text-rose-700">Eliminar</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-slate-500">Todavía no hay descuentos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
