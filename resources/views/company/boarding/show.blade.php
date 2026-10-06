@extends('layouts.company')
@section('title', 'Control de embarque')
@section('page-title', 'Control de Embarque')
@section('content')
@php
    $route = $departure->transportRoute;
    $origin = $route->originPort?->city ?? $route->originPort?->name ?? 'Origen';
    $destination = $route->destinationPort?->city ?? $route->destinationPort?->name ?? 'Destino';
    $masterCode = $route->masterRoute?->code ?? $route->code ?? 'Tramo oficial';
@endphp
<div class="mx-auto max-w-7xl space-y-5">
 @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>@endif
 <section class="overflow-hidden rounded-3xl bg-gradient-to-r from-[#062c21] to-emerald-800 text-white shadow-xl">
  <div class="flex flex-col justify-between gap-5 p-6 sm:flex-row sm:items-center">
   <div><div class="flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-emerald-300"><span>{{ $masterCode }}</span><span class="text-white/30">•</span><span>Despacho fluvial</span></div><h2 class="mt-2 text-2xl font-black sm:text-3xl">{{ $origin }} ➔ {{ $destination }}</h2><div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-emerald-100/80"><span>🚤 {{ $departure->vessel?->name ?? 'Embarcación por asignar' }}</span><span>🗓️ {{ $departure->departure_at?->format('d/m/Y') }}</span><span>🕐 Zarpe {{ $departure->departure_at?->format('H:i') }}</span></div></div>
   <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 text-center backdrop-blur-sm"><strong class="block text-2xl font-black">{{ $boardingStats['boarded'] }} de {{ $boardingStats['total'] }}</strong><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-200">pasajeros a bordo</span></div>
  </div>
  <div class="grid grid-cols-3 border-t border-white/10 bg-black/10 text-center text-xs"><div class="p-3"><strong class="block text-lg">{{ $boardingStats['total'] }}</strong><span class="text-emerald-100/70">Reservados</span></div><div class="border-x border-white/10 p-3"><strong class="block text-lg text-emerald-300">{{ $boardingStats['boarded'] }}</strong><span class="text-emerald-100/70">Abordaron</span></div><div class="p-3"><strong class="block text-lg text-amber-300">{{ $boardingStats['pending'] }}</strong><span class="text-emerald-100/70">Pendientes</span></div></div>
 </section>
 <section class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm">
  <form method="GET" action="{{ route('company.boarding.show', $departure) }}" class="flex flex-col gap-3 sm:flex-row"><label class="relative flex-1"><span class="sr-only">Escanear DNI o Código QR</span><span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span><input name="q" value="{{ $term }}" autofocus autocomplete="off" placeholder="Escanear DNI o Código QR" class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"></label><button class="h-12 rounded-xl bg-amber-500 px-6 text-xs font-black text-slate-950 transition hover:bg-amber-600">Buscar pasajero</button>@if($term !== '')<a href="{{ route('company.boarding.show', $departure) }}" class="flex h-12 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-bold text-slate-600">Limpiar</a>@endif</form>
  <p class="mt-2 text-[10px] text-slate-400">La validación está restringida a los boletos de esta salida.</p>
 </section>
 <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Lista oficial de pasajeros</span><h3 class="text-lg font-black text-slate-900">Pasajeros reservados</h3></div><span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-600">{{ $tickets->count() }} resultado(s)</span></div>
  <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3">Asiento</th><th class="px-4 py-3">Pasajero</th><th class="px-4 py-3">DNI / Documento</th><th class="px-4 py-3">Estado</th><th class="px-5 py-3 text-right">Acción</th></tr></thead><tbody>
  @forelse($tickets as $ticket)@php($seat = $ticket->reservationSeat)<tr class="border-t border-slate-100"><td class="px-5 py-4"><span class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl bg-slate-100 px-2 font-black text-slate-900">{{ $seat->seat?->code ?? '—' }}</span></td><td class="px-4 py-4"><strong class="block text-sm text-slate-900">{{ $seat->passenger_name ?? 'Pasajero sin nombre' }}</strong><span class="text-[10px] text-slate-400">Boleto {{ $ticket->code }}</span></td><td class="px-4 py-4"><span class="font-bold text-slate-800">{{ strtoupper($seat->document_type ?? 'DNI') }}</span><span class="ml-2 text-slate-500">{{ $seat->document_number ?? '—' }}</span></td><td class="px-4 py-4">@if($ticket->status === 'boarded')<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold text-emerald-800">✓ Abordó</span>@else<span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800">Pendiente</span>@endif</td><td class="px-5 py-4 text-right"><form method="POST" action="{{ route('company.boarding.toggle', [$departure, $ticket]) }}">@csrf @method('PATCH')<button class="rounded-xl px-4 py-2 text-[11px] font-black transition {{ $ticket->status === 'boarded' ? 'border border-rose-200 bg-white text-rose-700 hover:bg-rose-50' : 'bg-[#062c21] text-white hover:bg-emerald-900' }}">{{ $ticket->status === 'boarded' ? 'Anular abordaje' : '✓ Marcar a bordo' }}</button></form></td></tr>
  @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">{{ $term !== '' ? 'No se encontró un pasajero de esta salida con ese dato.' : 'No hay boletos emitidos para esta salida.' }}</td></tr>@endforelse
  </tbody></table></div>
 </section>
</div>
@endsection
