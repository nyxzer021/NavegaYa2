@extends('layouts.company')
@section('title', 'Plano de asientos')
@section('page-title', 'Plano de asientos')
@section('content')
@include('components.vessel-deck-styles')
@php
    $sampleRow = $seats->groupBy('row_position')->first();
    $leftDefault = 2;
    $rightDefault = 2;
    if ($sampleRow) {
        $columns = $sampleRow->pluck('column_position')->map(fn ($column) => (int) $column)->sort()->values();
        $leftDefault = 1;
        for ($index = 1; $index < $columns->count(); $index++) {
            if ($columns[$index] > $columns[$index - 1] + 1) {
                break;
            }
            $leftDefault++;
        }
        $rightDefault = max(1, $sampleRow->count() - $leftDefault);
    }
    $perSide = max(1, (int) old('left_seats', $leftDefault) + (int) old('right_seats', $rightDefault));
    $remainingForDeck = max(0, (int) $vessel->seat_capacity - (int) $otherDeckSeats);
    $rowDefault = $seats->isNotEmpty() ? (int) $seats->max('row_position') : max(1, intdiv($remainingForDeck, $perSide));
    $initialRows = (int) old('rows', $rowDefault);
    $initialLeft = (int) old('left_seats', $leftDefault);
    $initialRight = (int) old('right_seats', $rightDefault);
    $initialCurrent = $initialRows * ($initialLeft + $initialRight);
    $initialTotal = $initialCurrent + (int) $otherDeckSeats;
    $initialExceeds = $initialTotal > (int) $vessel->seat_capacity;
    $initialSentence = $initialExceeds
        ? "{$initialRows} filas × ".($initialLeft + $initialRight)." asientos = {$initialCurrent}. Te pasas: {$initialTotal} de {$vessel->seat_capacity}."
        : "{$initialRows} filas × ".($initialLeft + $initialRight)." asientos = {$initialCurrent}. Te quedan ".($vessel->seat_capacity - $initialTotal)." de {$vessel->seat_capacity}.";
    $deckName = $deck === 'principal' ? 'Cubierta principal' : 'Cubierta superior';
    $onPlan = ($deckCounts['principal'] ?? 0) + ($deckCounts['superior'] ?? 0);
@endphp
<div class="mx-auto max-w-6xl space-y-4">
    <a class="text-sm font-bold text-emerald-800" href="{{ $seatRoutePrefix === 'company' ? route('company.fleet.index') : route('admin.vessels.index') }}">Volver a embarcaciones</a>
    @if(session('success'))<p class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</p>@endif
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-end">
            <div>
                <h2 class="text-2xl font-black text-slate-950">{{ $vessel->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">Matrícula {{ $vessel->registration_number }} · Capacidad {{ $vessel->seat_capacity }} · En el plano {{ $onPlan }}</p>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm font-bold">
                <a class="rounded-xl px-3 py-2 text-center {{ $deck === 'principal' ? 'bg-[#062c21] text-white' : 'bg-slate-100 text-slate-700' }}" href="{{ route($seatRoutePrefix.'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'principal']) }}">Principal <span class="font-semibold">{{ $deckCounts['principal'] ?? 0 }}</span></a>
                <a class="rounded-xl px-3 py-2 text-center {{ $deck === 'superior' ? 'bg-[#062c21] text-white' : 'bg-slate-100 text-slate-700' }}" href="{{ route($seatRoutePrefix.'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'superior']) }}">Superior <span class="font-semibold">{{ $deckCounts['superior'] ?? 0 }}</span></a>
            </div>
        </div>
        <div class="mt-4 grid items-start gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
            <div>
                @if($seats->isEmpty() || $isEditing)
                    <h3 class="text-lg font-black text-slate-950">{{ $isEditing ? 'Cambiar' : 'Armar' }} {{ strtolower($deckName) }}</h3>
                    <p class="mt-1 text-sm text-slate-500">Cada fila tiene asientos a la izquierda del pasillo y a la derecha. El dibujo cambia al instante.</p>
                    @if($isEditing)<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Guardar reemplaza solo esta cubierta. La otra conserva su plano.</p>@endif
                    <form method="POST" action="{{ route($seatRoutePrefix.'.vessels.seats.generate', $vessel) }}" class="mt-4 grid gap-3">
                        @csrf
                        @if($isEditing)<input type="hidden" name="replace_existing" value="1">@endif
                        <input type="hidden" name="deck" value="{{ $deck }}">
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm font-semibold text-slate-700">Asientos por fila, lado izquierdo<input id="leftSeats" type="number" name="left_seats" value="{{ old('left_seats', $leftDefault) }}" min="1" max="6" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
                            <label class="text-sm font-semibold text-slate-700">Asientos por fila, lado derecho<input id="rightSeats" type="number" name="right_seats" value="{{ old('right_seats', $rightDefault) }}" min="1" max="6" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
                        </div>
                        <label class="text-sm font-semibold text-slate-700">Filas de esta cubierta<input id="rows" type="number" name="rows" value="{{ old('rows', $rowDefault) }}" min="1" max="100" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"></label>
                        <label class="text-sm font-semibold text-slate-700">Categoría<select name="seat_class" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold"><option value="standard" @selected(old('seat_class', 'standard') === 'standard')>Estándar</option><option value="premium" @selected(old('seat_class') === 'premium')>Premium</option><option value="business" @selected(old('seat_class') === 'business')>Business</option></select></label>
                        <div id="seatPreview" class="rounded-xl p-3 text-sm {{ $initialExceeds ? 'is-over' : '' }}" data-limit="{{ $vessel->seat_capacity }}" data-other="{{ $otherDeckSeats }}">
                            <span id="seatMath">{{ $initialSentence }}</span>
                            <span id="seatCount" class="hidden">0</span>
                            <span id="totalSeatCount" class="hidden">{{ $otherDeckSeats }}</span>
                        </div>
                        @if($errors->any())<div class="text-sm text-rose-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                        <button id="generateButton" class="h-11 rounded-xl bg-amber-400 text-sm font-black text-emerald-950 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500">{{ $isEditing ? 'Guardar esta cubierta' : 'Crear esta cubierta' }}</button>
                    </form>
                @else
                    <h3 class="text-lg font-black text-slate-950">Plano listo</h3>
                    <p class="mt-1 text-sm text-slate-500">Pulsa un asiento para bloquearlo. El viajero no podrá comprarlo: úsalo si hay un motor, una columna o un asiento dañado.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a class="inline-flex h-11 items-center rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white" href="{{ route($seatRoutePrefix.'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => $deck, 'edit' => 1]) }}">Cambiar filas</a>
                        <form method="POST" action="{{ route($seatRoutePrefix.'.vessels.seats.destroy-deck', $vessel) }}" onsubmit="return confirm('¿Eliminar el plano de esta cubierta?')">
                            @csrf @method('DELETE')
                            <input type="hidden" name="deck" value="{{ $deck }}">
                            <button class="h-11 rounded-xl border border-rose-200 px-4 text-sm font-bold text-rose-700">Eliminar</button>
                        </form>
                    </div>
                @endif
            </div>
            <div>
                <div class="mb-3 flex flex-wrap gap-4 text-sm text-slate-700">
                    <span class="inline-flex items-center gap-2"><i class="ny-seat ny-seat-sample"></i><span><strong>Libre.</strong> Se vende.</span></span>
                    <span class="inline-flex items-center gap-2"><i class="ny-seat ny-seat-sample blocked"></i><span><strong>Bloqueado.</strong> El viajero no puede elegirlo.</span></span>
                </div>
                @if($seats->isNotEmpty() && ! $isEditing)
                    @php($maxColumn = (int) $seats->max('column_position'))
                    <div class="ny-stage">
                        <div class="ny-hull" style="--ny-cols: {{ max(1, $maxColumn) }}">
                            <p class="ny-bow">Proa</p>
                            <div class="ny-cabin">
                                @foreach($seats->groupBy('row_position') as $row => $rowSeats)
                                    <div class="ny-row">
                                        <span class="ny-rowno">{{ $row }}</span>
                                        @for($column = 1; $column <= $maxColumn; $column++)
                                            @php($seat = $rowSeats->firstWhere('column_position', $column))
                                            @if($seat)
                                                <form class="ny-seat-form" method="POST" action="{{ route($seatRoutePrefix.'.vessels.seats.toggle', [$vessel, $seat]) }}">
                                                    @csrf @method('PATCH')
                                                    <button title="{{ $seat->is_available ? 'Bloquear '.$seat->code : 'Volver a vender '.$seat->code }}" class="ny-seat {{ $seat->is_available ? '' : 'blocked' }}">{{ $seat->code }}</button>
                                                </form>
                                            @endif
                                            @unless($seat)
                                                <span class="ny-aisle">{{ $loop->first ? 'Pasillo' : '' }}</span>
                                            @endunless
                                        @endfor
                                    </div>
                                @endforeach
                            </div>
                            <p class="ny-stern">Popa</p>
                        </div>
                    </div>
                @else
                    <div class="ny-stage">
                        <div class="ny-hull" id="liveHull" style="--ny-cols: 5">
                            <p class="ny-bow">Proa</p>
                            <div class="ny-cabin" id="liveCabin"></div>
                            <p class="ny-stern">Popa</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
<script>
const updateCount=()=>{const rowsInput=document.getElementById('rows'),preview=document.getElementById('seatPreview');if(!rowsInput||!preview)return;const rows=Number(rowsInput.value)||0,left=Number(document.getElementById('leftSeats').value)||0,right=Number(document.getElementById('rightSeats').value)||0,current=rows*(left+right),total=current+Number(preview.dataset.other),limit=Number(preview.dataset.limit),exceeds=total>limit,letters='ABCDEFGHIJKL';document.getElementById('seatCount').textContent=current;document.getElementById('totalSeatCount').textContent=total;document.getElementById('seatMath').textContent=exceeds?`${rows} filas × ${left+right} asientos = ${current}. Te pasas: ${total} de ${limit}.`:`${rows} filas × ${left+right} asientos = ${current}. Te quedan ${limit-total} de ${limit}.`;preview.classList.toggle('is-over',exceeds);const button=document.getElementById('generateButton');button.disabled=exceeds;button.title=exceeds?'La suma de ambas cubiertas supera la capacidad.':'';const cabin=document.getElementById('liveCabin'),hull=document.getElementById('liveHull');if(!cabin||!hull||left<1||right<1||rows<1)return;hull.style.setProperty('--ny-cols',String(left+1+right));let html='';for(let row=1;row<=Math.min(rows,100);row++){html+=`<div class="ny-row"><span class="ny-rowno">${row}</span>`;for(let column=1;column<=left;column++)html+=`<span class="ny-seat">${row}${letters[column-1]||'?'}</span>`;html+=`<span class="ny-aisle">${row===1?'Pasillo':''}</span>`;for(let column=1;column<=right;column++)html+=`<span class="ny-seat">${row}${letters[left+column-1]||'?'}</span>`;html+='</div>'}cabin.innerHTML=html};['rows','leftSeats','rightSeats'].forEach(id=>document.getElementById(id)?.addEventListener('input',updateCount));updateCount();
</script>
@endsection
