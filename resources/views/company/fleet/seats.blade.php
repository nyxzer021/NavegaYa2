@extends('layouts.company')
@section('title','Plano de asientos')
@section('page-title','Plano de asientos')
@section('content')
@include('components.vessel-deck-styles')
<div class="mx-auto max-w-6xl space-y-5">
    <a class="text-sm font-bold text-emerald-800" href="{{ $seatRoutePrefix === 'company' ? route('company.fleet.index') : route('admin.vessels.index') }}">Volver a embarcaciones</a>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-2xl font-black text-slate-950">{{ $vessel->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">Matrícula {{ $vessel->registration_number }}. Este plano es el que verá el viajero al elegir asiento.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <article class="rounded-2xl bg-slate-50 px-4 py-3"><strong class="block text-2xl font-black text-slate-950">{{ $vessel->seat_capacity }}</strong><span class="text-sm text-slate-500">Capacidad</span></article>
                <article class="rounded-2xl bg-slate-50 px-4 py-3"><strong class="block text-2xl font-black text-slate-950">{{ ($deckCounts['principal'] ?? 0) + ($deckCounts['superior'] ?? 0) }}</strong><span class="text-sm text-slate-500">En el plano</span></article>
            </div>
        </div>
    </section>
    <nav class="grid gap-2 sm:grid-cols-2">
        <a class="flex items-center justify-between rounded-2xl border px-4 py-3 text-sm font-bold {{ $deck === 'principal' ? 'border-emerald-800 bg-[#062c21] text-white' : 'border-slate-200 bg-white text-slate-700' }}" href="{{ route($seatRoutePrefix.'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'principal']) }}">Cubierta principal <span>{{ $deckCounts['principal'] ?? 0 }}</span></a>
        <a class="flex items-center justify-between rounded-2xl border px-4 py-3 text-sm font-bold {{ $deck === 'superior' ? 'border-emerald-800 bg-[#062c21] text-white' : 'border-slate-200 bg-white text-slate-700' }}" href="{{ route($seatRoutePrefix.'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'superior']) }}">Cubierta superior <span>{{ $deckCounts['superior'] ?? 0 }}</span></a>
    </nav>
    @if(session('success'))<p class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</p>@endif
    <div class="grid items-start gap-5 lg:grid-cols-[340px_minmax(0,1fr)]">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-4">
            @if($seats->isEmpty() || $isEditing)
                <h2 class="text-lg font-black text-slate-950">{{ $isEditing ? 'Editar' : 'Crear' }} {{ $deck === 'principal' ? 'cubierta principal' : 'cubierta superior' }}</h2>
                <p class="mt-1 text-sm text-slate-500">Indica filas y asientos a cada lado. El pasillo central queda en el medio.</p>
                @if($isEditing)<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Guardar reemplaza solo esta cubierta. La otra conserva su plano.</p>@endif
                <form method="POST" action="{{ route($seatRoutePrefix.'.vessels.seats.generate', $vessel) }}" class="mt-4 grid grid-cols-2 gap-3">
                    @csrf
                    @if($isEditing)<input type="hidden" name="replace_existing" value="1">@endif
                    <label class="text-sm font-semibold text-slate-700">Filas<input id="rows" type="number" name="rows" value="{{ old('rows', max(1, $seats->max('row_position') ?: 10)) }}" min="1" max="100" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
                    <label class="text-sm font-semibold text-slate-700">Categoría<select name="seat_class" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"><option value="standard">Estándar</option><option value="premium">Premium</option><option value="business">Business</option></select></label>
                    <label class="text-sm font-semibold text-slate-700">Asientos izquierda<input id="leftSeats" type="number" name="left_seats" value="{{ old('left_seats', 2) }}" min="1" max="6" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
                    <label class="text-sm font-semibold text-slate-700">Asientos derecha<input id="rightSeats" type="number" name="right_seats" value="{{ old('right_seats', 2) }}" min="1" max="6" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
                    <label class="col-span-2 text-sm font-semibold text-slate-700">Nivel<input value="{{ $deck === 'principal' ? 'Cubierta principal' : 'Cubierta superior' }}" disabled class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><input type="hidden" name="deck" value="{{ $deck }}"></label>
                    <div id="seatPreview" class="col-span-2 rounded-xl bg-emerald-50 p-3 text-sm text-slate-700" data-limit="{{ $vessel->seat_capacity }}" data-other="{{ $otherDeckSeats }}">Esta cubierta: <strong id="seatCount">0</strong> asientos. Otras cubiertas: <strong>{{ $otherDeckSeats }}</strong>. Total: <strong id="totalSeatCount">{{ $otherDeckSeats }}</strong> de {{ $vessel->seat_capacity }}.</div>
                    @if($errors->any())<div class="col-span-2 text-sm text-rose-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                    <button id="generateButton" class="col-span-2 h-11 rounded-xl bg-amber-400 text-sm font-black text-emerald-950">{{ $isEditing ? 'Guardar nuevo plano' : 'Generar plano de cubierta' }}</button>
                </form>
            @else
                <h2 class="text-lg font-black text-slate-950">Plano configurado</h2>
                <p class="mt-1 text-sm text-slate-500">La {{ $deck === 'principal' ? 'cubierta principal' : 'cubierta superior' }} tiene {{ $seats->count() }} asientos. Pulsa uno para bloquearlo o habilitarlo.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a class="inline-flex h-11 items-center rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white" href="{{ route($seatRoutePrefix.'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => $deck, 'edit' => 1]) }}">Editar distribución</a>
                    <form method="POST" action="{{ route($seatRoutePrefix.'.vessels.seats.destroy-deck', $vessel) }}" onsubmit="return confirm('¿Eliminar el plano de esta cubierta?')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="deck" value="{{ $deck }}">
                        <button class="h-11 rounded-xl border border-rose-200 px-4 text-sm font-bold text-rose-700">Eliminar</button>
                    </form>
                </div>
            @endif
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-lg font-black text-slate-950">{{ $deck === 'principal' ? 'Cubierta principal' : 'Cubierta superior' }}</h2>
                    <p class="text-sm text-slate-500">Vista desde arriba. Un toque cambia la disponibilidad.</p>
                </div>
                <div class="flex gap-4 text-sm text-slate-600">
                    <span class="inline-flex items-center gap-2"><i class="ny-seat ny-seat-sample"></i>Libre</span>
                    <span class="inline-flex items-center gap-2"><i class="ny-seat ny-seat-sample blocked"></i>Bloqueado</span>
                </div>
            </div>
            @if($seats->isNotEmpty())
                @php($maxColumn = (int) $seats->max('column_position'))
                <x-vessel-hull :columns="$maxColumn">
                    @foreach($seats->groupBy('row_position') as $row => $rowSeats)
                        <div class="ny-row">
                            <span class="ny-rowno">{{ $row }}</span>
                            @for($column = 1; $column <= $maxColumn; $column++)
                                @php($seat = $rowSeats->firstWhere('column_position', $column))
                                @if($seat)
                                    <form class="ny-seat-form" method="POST" action="{{ route($seatRoutePrefix.'.vessels.seats.toggle', [$vessel, $seat]) }}">
                                        @csrf @method('PATCH')
                                        <button title="{{ $seat->is_available ? 'Asiento libre' : 'Asiento bloqueado' }}" class="ny-seat {{ $seat->is_available ? '' : 'blocked' }}">{{ $seat->code }}</button>
                                    </form>
                                @else
                                    <span class="ny-aisle"></span>
                                @endif
                            @endfor
                        </div>
                    @endforeach
                </x-vessel-hull>
            @else
                <div class="px-5 py-14 text-center">
                    <strong class="block text-slate-800">Esta cubierta todavía no tiene distribución</strong>
                    <p class="mt-1 text-sm text-slate-500">Indica filas y asientos para ver la embarcación.</p>
                </div>
            @endif
        </section>
    </div>
</div>
<script>
const updateCount=()=>{const rows=Number(document.getElementById('rows')?.value)||0,left=Number(document.getElementById('leftSeats')?.value)||0,right=Number(document.getElementById('rightSeats')?.value)||0,preview=document.getElementById('seatPreview');if(!preview)return;const current=rows*(left+right),total=current+Number(preview.dataset.other),limit=Number(preview.dataset.limit),button=document.getElementById('generateButton'),exceeds=total>limit;document.getElementById('seatCount').textContent=current;document.getElementById('totalSeatCount').textContent=total;preview.style.background=exceeds?'#fff1f2':'#ecfdf5';preview.style.color=exceeds?'#be123c':'#334155';button.disabled=exceeds;button.style.opacity=exceeds?'.5':'1';button.title=exceeds?'La suma de ambas cubiertas supera la capacidad máxima.':''};['rows','leftSeats','rightSeats'].forEach(id=>document.getElementById(id)?.addEventListener('input',updateCount));updateCount();
</script>
@endsection
