@extends('layouts.company')
@section('title', 'Punto de venta')
@section('page-title', 'Punto de venta')
@section('content')
<div class="mx-auto max-w-6xl space-y-5">
 <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-semibold text-emerald-800">Agencia física · muelle</p><h2 class="mt-1 text-2xl font-black text-slate-950">Vender pasaje en el muelle</h2><p class="mt-1 max-w-2xl text-sm text-slate-500">Cobra la tarifa en efectivo y entrega el boleto. NavegaYA no cobra comisión por esta venta. Solo aparecen los zarpes de hoy.</p></div></div></section>
 @if($departures->isEmpty())<div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"><strong class="block text-slate-800">Hoy no hay una salida para vender en el muelle</strong><p class="mt-1 text-sm text-slate-500">Programa el zarpe del día. El pasajero paga solo la tarifa de la empresa.</p><a href="{{ route('company.departures.create') }}" class="mt-4 inline-flex h-10 items-center text-sm font-bold text-emerald-800">Programar salida</a>@if($upcoming->isNotEmpty())<div class="mt-6 text-left"><p class="text-sm font-bold text-slate-700">Ya programadas para otro día</p><ul class="mt-2 space-y-2">@foreach($upcoming as $departure)<li class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $departure->departure_at->translatedFormat('D d M · H:i') }} · {{ $departure->transportRoute->originPort->city }} → {{ $departure->transportRoute->destinationPort->city }} · {{ $departure->vessel->name }}</li>@endforeach</ul></div>@endif</div>@else
 <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><label class="text-sm font-semibold text-slate-600">Salida de hoy</label><div class="mt-1 flex gap-2"><select name="departure" class="h-11 flex-1 rounded-xl border-slate-200 bg-slate-50 text-xs font-bold" onchange="this.form.submit()">@foreach($departures as $departure)<option value="{{ $departure->id }}" @selected($selectedDeparture?->id === $departure->id)>{{ $departure->departure_at->format('H:i') }} · {{ $departure->transportRoute->originPort->city }} ➔ {{ $departure->transportRoute->destinationPort->city }} · {{ $departure->vessel->name }}</option>@endforeach</select></div></form>
 <form method="POST" action="{{ route('company.pos.store') }}" class="grid gap-5 lg:grid-cols-[1fr_380px]">@csrf<input type="hidden" name="departure_id" value="{{ $selectedDeparture->id }}">
  @include('components.vessel-deck-styles')
  <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
      <div><h3 class="font-black text-slate-950">Selecciona un asiento</h3><p class="text-sm text-slate-500">El plano es el de la embarcación. Los grises ya están ocupados o bloqueados.</p></div>
      <div class="flex gap-4 text-sm text-slate-600"><span class="inline-flex items-center gap-2"><i class="ny-seat ny-seat-sample"></i>Libre</span><span class="inline-flex items-center gap-2"><i class="ny-seat ny-seat-sample blocked"></i>Ocupado</span></div>
    </div>
    @foreach($selectedDeparture->vessel->seats->sortBy(['deck','row_position','column_position'])->groupBy('deck') as $deck => $deckSeats)
      @php($maxColumn = (int) $deckSeats->max('column_position'))
      <h4 class="mt-4 text-sm font-bold text-slate-700">{{ $deck === 'principal' ? 'Cubierta principal' : ($deck === 'superior' ? 'Cubierta superior' : 'Cubierta '.$deck) }}</h4>
      <x-vessel-hull :columns="$maxColumn">
        @foreach($deckSeats->groupBy('row_position') as $row => $rowSeats)
          <div class="ny-row"><span class="ny-rowno">{{ $row }}</span>
            @for($column = 1; $column <= max(1, $maxColumn); $column++)
              @php($seat = $rowSeats->firstWhere('column_position', $column))
              @if($seat)
                <label class="ny-seat-form"><input type="radio" name="seat_id" value="{{ $seat->id }}" class="peer sr-only" @disabled(in_array($seat->id, $occupiedSeatIds, true) || ! $seat->is_available) required><span class="ny-seat peer-checked:border-amber-400 peer-checked:bg-[#062c21] peer-checked:text-amber-300 {{ in_array($seat->id, $occupiedSeatIds, true) || ! $seat->is_available ? 'blocked' : '' }}">{{ $seat->code }}</span></label>
              @else
                <span class="ny-aisle"></span>
              @endif
            @endfor
          </div>
        @endforeach
      </x-vessel-hull>
    @endforeach
  </section>
  <aside class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-black text-slate-900">Datos del pasajero</h3><div class="mt-4 space-y-3">@foreach([['passenger_name','Nombres y apellidos','text'],['document_number','DNI / documento','text'],['passenger_age','Edad','number'],['phone','Teléfono / WhatsApp','text'],['email','Correo (opcional)','email']] as [$name,$label,$type])<label class="block"><span class="text-sm font-semibold text-slate-600">{{ $label }}</span><input type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" @required($name!=='email') class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm font-semibold"></label>@endforeach<label class="block"><span class="text-sm font-semibold text-slate-600">Tipo de documento</span><select name="document_type" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm font-semibold"><option>DNI</option><option>CE</option><option>Pasaporte</option></select></label></div>@if($errors->any())<p class="mt-3 rounded-xl bg-rose-50 p-3 text-xs text-rose-700">{{ $errors->first() }}</p>@endif<div class="mt-4 rounded-xl bg-emerald-50 p-3 text-xs"><span class="text-slate-500">Cobro en efectivo</span><strong class="float-right text-lg text-slate-900">S/ {{ number_format((float)$selectedDeparture->fare,2) }}</strong></div><button class="mt-4 h-10 w-full rounded-xl bg-amber-400 text-sm font-black text-emerald-950">Cobrar y emitir boleto</button></aside>
 </form>
 @endif
</div>
@endsection
