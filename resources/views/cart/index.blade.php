<x-public-layout current="travels" title="Confirmar y pagar pasajes · NavegaYA">
<div class="min-h-screen bg-slate-50">
<main class="mx-auto max-w-6xl px-4 pb-20 pt-4 sm:px-6 lg:px-8" x-data="{ paymentMethod: 'yape' }">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 text-xs">
<a href="{{url()->previous()}}" class="inline-flex items-center gap-1.5 font-bold text-emerald-800 transition hover:text-emerald-950">← Modificar asientos o volver</a>
<div class="hidden items-center gap-2 font-semibold text-slate-400 sm:flex"><span>1. Asientos</span><span>›</span><span class="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-900">2. Confirmar y pagar</span><span>›</span><span>3. Boleto emitido</span></div>
</div>

<section class="mb-8 flex flex-col justify-between gap-4 rounded-3xl border border-emerald-900 bg-gradient-to-r from-[#062c21] via-emerald-950 to-slate-900 p-6 text-white shadow-xl sm:flex-row sm:items-center sm:p-8">
<div><span class="mb-2 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/20 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">🔒 Pasarela de pago seguro</span><h1 class="text-2xl font-black tracking-tight sm:text-3xl">Revisa tu viaje antes de pagar</h1><p class="mt-1 max-w-2xl text-xs text-emerald-100/80 sm:text-sm">Verifica los pasajeros y el itinerario antes de procesar el pago y emitir tus boletos digitales.</p></div>
<div class="hidden shrink-0 items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3.5 py-2 text-xs font-bold text-emerald-300 sm:flex">🛡️ Conexión protegida</div>
</section>

<div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
<div class="space-y-6 lg:col-span-7">
<section class="space-y-4 rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
<div class="flex items-center justify-between border-b border-slate-100 pb-3"><h2 class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-800">🎫 Pasajes registrados</h2><span class="rounded-full border border-emerald-200/80 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-800">✓ Datos verificados</span></div>
@forelse($reservations as $reservation)
@php
$isAir=$reservation->isAir();
$trip=$isAir?$reservation->airDeparture:$reservation->departure;
$route=$isAir?$trip->airRoute:$trip->transportRoute;
$origin=$isAir?$route->origin_city:$route->originPort->city;
$destination=$isAir?$route->destination_city:$route->destinationPort->city;
$vehicle=$isAir?$trip->aircraft->name:$trip->vessel->name;
$port=$isAir?($trip->airport_name ?? 'Aeropuerto regional'):($route->originPort->name ?? 'Puerto de salida');
@endphp
<article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
<div class="min-w-0">
<div class="flex flex-wrap items-center gap-2"><span class="text-lg">{{$isAir?'✈️':'🚤'}}</span><h3 class="text-lg font-black text-slate-900">{{$origin}} ➔ {{$destination}}</h3><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{$isAir?'bg-sky-100 text-sky-800':'bg-emerald-100 text-emerald-800'}}">{{$isAir?'VIAJE AÉREO':'VIAJE FLUVIAL'}}</span></div>
<p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500"><span>📅 {{$trip->departure_at->format('d/m/Y · H:i')}} h</span><span>•</span><span><strong>{{$vehicle}}</strong></span></p>
<p class="mt-2 text-xs text-slate-600">👤 <strong>{{$reservation->seats->count()}} pasajero(s):</strong> {{$reservation->seats->pluck('passenger_name')->join(', ')}}</p>
</div>
<div class="shrink-0 sm:text-right"><span class="block text-[10px] font-bold uppercase text-slate-400">Tarifa del viaje</span><strong class="text-2xl font-black text-slate-900">S/ {{number_format((float)$reservation->total_amount,2)}}</strong><form method="POST" action="{{route('cart.remove',$reservation)}}" class="mt-1">@csrf @method('DELETE')<button class="text-[11px] font-bold text-rose-600 transition hover:text-rose-800">Quitar del carrito</button></form></div>
</div>
<div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-[11px] font-medium text-slate-500"><span>📍 Salida: <strong>{{$port}}</strong></span><span>🧳 Equipaje sujeto a condiciones del operador</span></div>
</article>
@empty
<div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center"><h3 class="text-sm font-bold text-slate-800">Tu carrito está vacío</h3><p class="mt-1 text-xs text-slate-500">Selecciona una salida y sus asientos para continuar.</p></div>
@endforelse
<a href="{{route('routes.index')}}" class="inline-flex items-center gap-1.5 pt-1 text-xs font-bold text-emerald-800 transition hover:text-emerald-950">＋ Agregar otro viaje o retorno</a>
</section>

@if($reservations->isNotEmpty())
<section class="space-y-4 rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
<h2 class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-800">💳 Métodos disponibles</h2>
<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
<button type="button" @click="paymentMethod='yape'" :class="paymentMethod==='yape'?'border-emerald-700 bg-emerald-50/70 ring-1 ring-emerald-700':'border-slate-200 hover:bg-slate-50'" class="relative rounded-2xl border p-3.5 text-left transition"><span class="mb-1.5 block text-xl">📱</span><strong class="block text-xs text-slate-900">Yape / Plin</strong><span class="text-[10px] text-slate-500">Pago mediante QR</span><i x-show="paymentMethod==='yape'" class="absolute right-3 top-3 h-2 w-2 rounded-full bg-emerald-600"></i></button>
<button type="button" @click="paymentMethod='card'" :class="paymentMethod==='card'?'border-emerald-700 bg-emerald-50/70 ring-1 ring-emerald-700':'border-slate-200 hover:bg-slate-50'" class="relative rounded-2xl border p-3.5 text-left transition"><span class="mb-1.5 block text-xl">💳</span><strong class="block text-xs text-slate-900">Débito o crédito</strong><span class="text-[10px] text-slate-500">Visa y Mastercard</span><i x-show="paymentMethod==='card'" class="absolute right-3 top-3 h-2 w-2 rounded-full bg-emerald-600"></i></button>
<button type="button" @click="paymentMethod='transfer'" :class="paymentMethod==='transfer'?'border-emerald-700 bg-emerald-50/70 ring-1 ring-emerald-700':'border-slate-200 hover:bg-slate-50'" class="relative rounded-2xl border p-3.5 text-left transition"><span class="mb-1.5 block text-xl">🏦</span><strong class="block text-xs text-slate-900">Transferencia</strong><span class="text-[10px] text-slate-500">Banca nacional</span><i x-show="paymentMethod==='transfer'" class="absolute right-3 top-3 h-2 w-2 rounded-full bg-emerald-600"></i></button>
</div>
<p class="text-[10px] text-slate-400">Podrás completar el método elegido en el siguiente paso seguro.</p>
</section>
@endif
</div>

<aside class="space-y-4 lg:sticky lg:top-24 lg:col-span-5">
<section class="space-y-5 rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xl sm:p-7">
<h2 class="border-b border-slate-100 pb-3 text-xs font-black uppercase tracking-wider text-slate-800">Resumen del pago</h2>
<div class="space-y-2.5 text-xs">
<div class="flex justify-between text-slate-600"><span>Subtotal de pasajes</span><strong class="text-slate-900">S/ {{number_format($subtotal,2)}}</strong></div>
<div class="flex justify-between gap-4 text-slate-600"><span>Gestión de reserva ({{number_format($percent,0)}}%)</span><strong class="shrink-0 text-slate-900">S/ {{number_format($fee,2)}}</strong></div>
<div class="flex justify-between gap-4 text-slate-600"><span>Emisión digital y soporte</span><strong class="shrink-0 text-emerald-700">Incluido</strong></div>
<div class="flex items-baseline justify-between border-t border-slate-200 pt-4"><div><strong class="block text-sm text-slate-900">Total a pagar</strong><span class="text-[10px] font-medium text-slate-400">Importe final en PEN</span></div><strong class="text-3xl font-black text-slate-900">S/ {{number_format($total,2)}}</strong></div>
</div>
@if($reservations->isNotEmpty())<form method="POST" action="{{route('cart.checkout')}}" data-turbo="false">@csrf<input type="hidden" name="preferred_method" x-bind:value="paymentMethod"><button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-amber-500 text-sm font-black text-slate-950 shadow-lg transition hover:bg-amber-600 hover:shadow-amber-500/20 active:scale-[.99]">Pagar S/ {{number_format($total,2)}} y continuar <span>➔</span></button></form>@endif
<div class="space-y-2 border-t border-slate-100 pt-3 text-[11px] text-slate-500"><p class="flex gap-2"><span class="font-bold text-emerald-700">✓</span><span>Boleto enviado a tu WhatsApp y correo después del pago.</span></p><p class="flex gap-2"><span class="font-bold text-emerald-700">✓</span><span>Registro en el manifiesto oficial del operador.</span></p></div>
</section>
<div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 text-center text-xs text-slate-500">¿Necesitas ayuda? <a href="https://wa.me/51900000000" target="_blank" rel="noopener" class="ml-1 font-bold text-emerald-800 underline hover:text-emerald-950">WhatsApp de soporte</a></div>
</aside>
</div>
</main>
</div>
</x-public-layout>



