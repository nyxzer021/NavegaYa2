<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">Control de embarque</h2></x-slot>
<div class="mx-auto max-w-7xl space-y-5">
 <section class="rounded-3xl bg-[#062c21] p-6 text-white shadow-xl sm:p-7">
  <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"><div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Operación en terminal</span><h1 class="mt-1 text-2xl font-black">Control de embarque fluvial y aéreo</h1><p class="mt-1 text-xs text-emerald-100/75">Escanea el QR o busca por DNI, reserva o boleto{{$selectedDeparture?' dentro de esta salida.':'.'}}</p></div>
   <form method="GET" action="{{route('admin.boarding.index')}}" class="grid w-full gap-2 sm:grid-cols-[1fr_190px_auto] lg:max-w-2xl">@if($selectedDeparture)<input type="hidden" name="{{$selectedIsAir?'air_departure':'departure'}}" value="{{$selectedDeparture->id}}">@endif<input name="q" value="{{$term}}" autofocus autocomplete="off" placeholder="QR, DNI, RES-... o BOL-..." class="h-11 rounded-xl border-0 bg-white px-3 text-xs text-slate-900 focus:ring-2 focus:ring-amber-400"><select name="port_id" class="h-11 rounded-xl border-0 bg-white px-3 text-xs text-slate-700 focus:ring-2 focus:ring-amber-400"><option value="">Puerto / terminal</option>@foreach($ports as $port)<option value="{{$port->id}}" @selected((string)request('port_id')===(string)$port->id)>{{$port->name}} · {{$port->city}}</option>@endforeach</select><button class="h-11 rounded-xl bg-amber-500 px-5 text-xs font-black text-slate-950 hover:bg-amber-600">Buscar boleto</button></form>
  </div>
 </section>
 @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">✓ {{session('success')}}</div>@endif
 @if(session('error'))<div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">{{session('error')}}</div>@endif

 @if($selectedDeparture)
  @php($route=$selectedIsAir?$selectedDeparture->airRoute:$selectedDeparture->transportRoute) @php($unit=$selectedIsAir?$selectedDeparture->aircraft:$selectedDeparture->vessel)
  <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
   <div class="flex flex-col justify-between gap-5 bg-gradient-to-r from-emerald-950 to-emerald-800 p-5 text-white sm:flex-row sm:items-center">
    <div><div class="flex flex-wrap items-center gap-2"><span class="rounded-full border border-emerald-400/30 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-200">{{$selectedIsAir?'Vuelo regional':'Salida '.($selectedDeparture->code??'')}}</span><span class="text-[10px] font-semibold text-emerald-200">{{$selectedDeparture->departure_at->format('d/m/Y · H:i')}} h</span></div><h2 class="mt-2 text-2xl font-black">{{$selectedIsAir?$route->origin_city:$route->originPort->city}} ➔ {{$selectedIsAir?$route->destination_city:$route->destinationPort->city}}</h2><p class="mt-1 text-xs text-emerald-100/75">{{$selectedIsAir?'✈️':'🚤'}} {{$unit->name}} @if(!$selectedIsAir) · 📍 {{$route->originPort->name}} @endif</p></div>
    <div class="min-w-[260px] rounded-2xl border border-white/15 bg-white/10 p-4"><div class="flex items-end justify-between"><div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-200">Avance de abordaje</span><p class="mt-1 text-lg font-black">{{$boardingStats['boarded']}} de {{$boardingStats['total']}} pasajeros a bordo</p></div><span class="text-2xl font-black text-amber-300">{{$boardingStats['total']?round($boardingStats['boarded']/$boardingStats['total']*100):0}}%</span></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-black/20"><div class="h-full rounded-full bg-amber-400" style="width: {{$boardingStats['total']?round($boardingStats['boarded']/$boardingStats['total']*100):0}}%"></div></div><p class="mt-2 text-[10px] text-emerald-100/70">{{$boardingStats['pending']}} pendiente(s) de abordar</p></div>
   </div>
   @include('admin.boarding.partials.ticket-table',['heading'=>'Lista de pasajeros de esta salida','tickets'=>$current,'selectedDeparture'=>$selectedDeparture])
  </section>
 @else
  <section class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl">▣</div><h2 class="mt-3 text-base font-black text-slate-900">Busca un pasajero para validar su embarque</h2><p class="mx-auto mt-1 max-w-lg text-xs leading-relaxed text-slate-500">Ingresa su DNI, código de reserva, boleto o escanea el QR. Desde “Rutas y salidas” también puedes abrir directamente la lista completa de pasajeros.</p></section>
  @if($term!=='')
   <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">@include('admin.boarding.partials.ticket-table',['heading'=>'Viajes vigentes o futuros','tickets'=>$current,'selectedDeparture'=>null])</section>
   @if($previous->isNotEmpty())<section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">@include('admin.boarding.partials.ticket-table',['heading'=>'Viajes anteriores','tickets'=>$previous,'selectedDeparture'=>null])</section>@endif
  @endif
 @endif
</div>
</x-app-layout>
