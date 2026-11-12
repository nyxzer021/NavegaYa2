@extends('layouts.company')
@section('title','Programación de salidas')
@section('page-title','Programación de salidas')
@section('content')
@php
    $isCounter = auth()->user()->roles()->where('code', 'company_counter')->exists();
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 rounded-2xl border border-[#d5e6df] bg-white p-5 shadow-sm sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-black text-slate-950">{{ $filter === 'today' ? 'Salidas de hoy' : 'Itinerario' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Las tres cifras son de hoy. El embarque se abre el día del zarpe.</p>
        </div>
        @unless($isCounter)
            <a href="{{ route('company.departures.create') }}" class="inline-flex h-10 items-center justify-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Programar salida</a>
        @endunless
    </section>
    @if(session('whatsapp_links'))
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            <strong>WhatsApp listo para abrir.</strong> No se marcó como enviado.
            <span class="mt-2 flex flex-col gap-1">
                @foreach(session('whatsapp_links') as $link)
                    <a class="font-bold text-emerald-800" href="{{ $link }}" target="_blank" rel="noopener">Abrir WhatsApp</a>
                @endforeach
            </span>
        </section>
    @endif
    @if(($series ?? collect())->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h3 class="text-lg font-black text-slate-950">Series</h3>
            <div class="mt-3 space-y-3">
                @foreach($series as $item)
                    <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-3">
                        <p class="text-sm text-slate-700">{{ $item->departure_time }} · {{ $item->starts_on->format('d/m/Y') }} a {{ $item->ends_on->format('d/m/Y') }} · {{ $item->status === 'active' ? 'Activa' : 'Retirada' }}</p>
                        @if($item->status === 'active')
                            <span class="flex flex-wrap items-center gap-2">
                                <details>
                                    <summary class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-700">Editar</summary>
                                    <form class="mt-3 grid gap-2 text-sm" method="POST" action="{{ route('company.departures.series.update', $item) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="master_route_id" value="{{ $item->transportRoute?->master_route_id }}">
                                        <input type="hidden" name="vessel_id" value="{{ $item->vessel_id }}">
                                        <input type="hidden" name="departure_date" value="{{ $item->starts_on->format('Y-m-d') }}">
                                        <label>Hora de Lima<input type="time" name="departure_time" value="{{ $item->departure_time }}" required></label>
                                        <span class="flex flex-wrap gap-2">
                                            @foreach([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $day => $label)
                                                <label><input type="checkbox" name="weekdays[]" value="{{ $day }}" @checked(in_array($day, array_map('intval', $item->weekdays ?? []), true))>{{ $label }}</label>
                                            @endforeach
                                        </span>
                                        <label>Desde<input type="date" name="starts_on" value="{{ $item->starts_on->format('Y-m-d') }}" required></label>
                                        <label>Hasta<input type="date" name="ends_on" value="{{ $item->ends_on->format('Y-m-d') }}" required></label>
                                        <label>Precio<input type="number" name="fare" min="0.01" step="0.01" value="{{ $item->fare }}" required></label>
                                        <button class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-800 px-3 text-sm font-bold text-white">Guardar serie</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('company.departures.series.destroy', $item) }}">@csrf @method('DELETE')<button class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-700">Eliminar serie</button></form>
                            </span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
    <section class="grid gap-3 sm:grid-cols-3">
        <article class="rounded-2xl border border-[#6ee7b7] bg-[#ecfdf5] p-5"><span class="text-sm font-semibold text-emerald-800">Salidas de hoy</span><strong class="mt-2 block text-3xl font-black text-[#064e3b]">{{ $todayDeparturesCount }}</strong></article>
        <article class="rounded-2xl border border-[#fbbf24] bg-[#fffbeb] p-5"><span class="text-sm font-semibold text-amber-800">Ocupación</span><strong class="mt-2 block text-3xl font-black text-[#78350f]">{{ $todayOccupancyLabel }}%</strong><span class="mt-1 block text-sm text-amber-900">{{ $todayConfirmedSeats }} de {{ $todaySeatCapacity }} {{ $todaySeatCapacity === 1 ? 'asiento' : 'asientos' }}</span></article>
        <article class="rounded-2xl border border-[#6ee7b7] bg-[#ecfdf5] p-5"><span class="text-sm font-semibold text-emerald-800">Asientos confirmados</span><strong class="mt-2 block text-3xl font-black text-[#064e3b]">{{ $todayConfirmedSeats }}</strong></article>
    </section>
    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h3 class="text-lg font-black text-slate-950">{{ $filter === 'today' ? 'Zarpes de hoy' : 'Salidas programadas' }}</h3>
            <div class="flex gap-2">
                <a href="{{ route('company.departures.index') }}" class="rounded-lg px-3 py-2 text-sm font-bold {{ ! $filter ? 'bg-[#062c21] text-white' : 'bg-slate-100 text-slate-600' }}">Todas</a>
                <a href="{{ route('company.departures.index', ['filter' => 'today']) }}" class="rounded-lg px-3 py-2 text-sm font-bold {{ $filter === 'today' ? 'bg-[#062c21] text-white' : 'bg-slate-100 text-slate-600' }}">Hoy</a>
            </div>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($departures as $row)
                <article class="space-y-3 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <strong class="text-lg font-black text-slate-950">{{ $row['departure_at']->copy()->timezone('America/Lima')->format('H:i') }}</strong>
                            <span class="mt-0.5 block text-sm text-slate-800">{{ $row['origin'] }} → {{ $row['destination'] }}</span>
                            <span class="block text-sm text-slate-500">{{ $row['unit'] }} · {{ $row['booked'] }}/{{ $row['capacity'] }} · S/ {{ number_format($row['fare'], 2) }}</span>
                        </div>
                        <x-company-status :status="$row['status']" />
                    </div>
                    @include('company.departures._actions', ['row' => $row, 'isCounter' => $isCounter])
                </article>
            @empty
                <p class="px-5 py-12 text-center text-sm text-slate-500">No hay salidas para el filtro seleccionado.</p>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Fecha / hora</th>
                        <th class="px-4 py-3">Tramo oficial</th>
                        <th class="px-4 py-3">Unidad</th>
                        <th class="px-4 py-3">Ocupación</th>
                        <th class="px-4 py-3">Tarifa</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-5 py-3 text-right">Despacho</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($departures as $row)
                    <tr class="border-t border-slate-100">
                        <td class="px-5 py-4"><strong class="text-slate-900">{{ $row['departure_at']->copy()->timezone('America/Lima')->format('d/m/Y') }}</strong><span class="block text-base font-black">{{ $row['departure_at']->copy()->timezone('America/Lima')->format('H:i') }}</span></td>
                        <td class="px-4 py-4"><strong class="text-slate-900">{{ $row['origin'] }} → {{ $row['destination'] }}</strong><span class="block text-xs text-slate-500">{{ $row['is_air'] ? 'Aéreo' : 'Fluvial' }} · {{ $row['master_code'] ?? 'Tramo legado' }}</span></td>
                        <td class="px-4 py-4">{{ $row['unit'] }}<span class="block text-xs text-slate-500">{{ $row['registration'] ?? 'Sin matrícula' }}</span></td>
                        <td class="px-4 py-4"><div class="flex justify-between text-xs"><strong>{{ $row['booked'] }}/{{ $row['capacity'] }}</strong><span>{{ $row['occupancy'] }}%</span></div><div class="mt-1 h-2 w-32 overflow-hidden rounded-full bg-slate-100"><i class="block h-full rounded-full bg-emerald-600" style="width:{{ min(100, $row['occupancy']) }}%"></i></div></td>
                        <td class="px-4 py-4"><strong class="block font-black text-slate-950">S/ {{ number_format($row['fare'], 2) }}</strong></td>
                        <td class="px-4 py-4"><x-company-status :status="$row['status']" />@if($row['model']->disruption_reason)<span class="mt-1 block max-w-40 text-xs text-slate-500">{{ $row['model']->disruption_reason }}</span>@endif</td>
                        <td class="px-5 py-4">@include('company.departures._actions', ['row' => $row, 'isCounter' => $isCounter])</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No hay salidas para el filtro seleccionado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @unless($isCounter)
            @foreach($departures as $row)
                @if(! $row['is_air'])
                    <dialog id="contingency-{{ $row['model']->id }}" class="w-[min(92vw,680px)] rounded-3xl bg-white p-0 text-left shadow-2xl backdrop:bg-slate-950/55">
                        <form method="POST" action="{{ route('company.departures.contingency', $row['model']) }}">
                            @csrf @method('PATCH')
                            <header class="flex items-start justify-between bg-[#062c21] px-6 py-5 text-white">
                                <div>
                                    <h3 class="text-xl font-black">{{ $row['origin'] }} → {{ $row['destination'] }}</h3>
                                    <p class="mt-1 text-sm text-emerald-100/80">{{ $row['departure_at']->copy()->timezone('America/Lima')->format('d/m/Y · H:i') }} · {{ $row['unit'] }}</p>
                                </div>
                                <button type="button" onclick="this.closest('dialog').close()" class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-lg">×</button>
                            </header>
                            <div class="grid gap-4 p-6 sm:grid-cols-2">
                                <label class="text-sm font-bold text-slate-700">Acción<select name="action" required class="mt-1 h-10 w-full rounded-xl border-[#d5e6df] bg-white px-3 text-sm"><option value="delay">Informar retraso</option><option value="reschedule">Reprogramar fecha</option><option value="change_vessel">Cambiar embarcación</option><option value="pause_sales">Pausar ventas</option><option value="resume_sales">Reabrir ventas</option><option value="suspend">Suspender temporalmente</option><option value="cancel">Cancelar salida</option></select></label>
                                <label class="text-sm font-bold text-slate-700">Nueva fecha y hora<input type="datetime-local" name="departure_at" class="mt-1 h-10 w-full rounded-xl border-[#d5e6df] bg-white px-3 text-sm"></label>
                                <label class="text-sm font-bold text-slate-700 sm:col-span-2">Nueva embarcación<select name="vessel_id" class="mt-1 h-10 w-full rounded-xl border-[#d5e6df] bg-white px-3 text-sm"><option value="">Mantener unidad actual</option>@foreach($organization->vessels->where('status', 'ready')->filter(fn ($vessel) => \App\Support\FleetPlan::isComplete($vessel)) as $vessel)<option value="{{ $vessel->id }}">{{ $vessel->name }} · {{ $vessel->seat_capacity }} asientos</option>@endforeach</select></label>
                                <label class="text-sm font-bold text-slate-700 sm:col-span-2">Motivo<textarea name="reason" required maxlength="500" rows="3" class="mt-1.5 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm" placeholder="Describe brevemente el motivo"></textarea></label>
                            </div>
                            <footer class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                                <button type="button" onclick="this.closest('dialog').close()" class="h-10 rounded-xl px-4 text-sm font-bold text-slate-500">Cancelar</button>
                                <button class="h-10 rounded-xl bg-[#062c21] px-4 text-sm font-black text-white">Aplicar cambio</button>
                            </footer>
                        </form>
                    </dialog>
                @endif
            @endforeach
        @endunless
        @if($departures->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $departures->links() }}</div>@endif
    </section>
</div>
<style>
    .company-main summary { list-style: none; }
    .company-main summary::-webkit-details-marker { display: none; }
</style>
@endsection
