<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">Manifiesto oficial DICAPI</h2></x-slot>
@php
    $vessel = $departure->vessel;
    $org = $vessel->organization;
    $route = $departure->transportRoute;
    $statusLabel = match ($departure->status) {
        'boarding' => 'Abordando',
        'departed' => 'Zarpó',
        'cancelled' => 'Cancelado',
        default => 'Programado',
    };
@endphp
<style>
.manifest-table{width:100%;border-collapse:collapse}.manifest-table th,.manifest-table td{border:1px solid #cbd5e1;padding:7px 6px;font-size:10px;vertical-align:middle}.manifest-table th{background:#f1f5f9;color:#475569;text-transform:uppercase;font-size:8px;letter-spacing:.05em}.manifest-table tbody tr:nth-child(even){background:#f8fafc}
@media print{@page{size:A4 landscape;margin:10mm}.ny-side,.ny-main>header,.manifest-actions,.manifest-breadcrumb{display:none!important}.ny-shell,.ny-main{display:block!important;min-height:0!important;width:100%!important}.ny-main>main{padding:0!important}.manifest-wrap{max-width:none!important;margin:0!important;padding:0!important}.manifest-sheet{border:0!important;border-radius:0!important;box-shadow:none!important;padding:0!important}.manifest-table th,.manifest-table td{padding:5px 4px;font-size:8px}body{background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
<div class="manifest-wrap mx-auto max-w-7xl space-y-5">
 <div class="manifest-actions flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:p-5">
  <div><div class="manifest-breadcrumb flex items-center gap-2"><a href="{{route('admin.transport-routes.index')}}" class="text-xs font-bold text-emerald-800 hover:text-emerald-950">← Volver a salidas</a><span class="text-slate-300">/</span><span class="text-xs font-semibold text-slate-500">Control de zarpe</span></div><div class="mt-1 flex flex-wrap items-center gap-2"><h1 class="text-xl font-black text-slate-900">Manifiesto oficial de pasajeros</h1><span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-800">{{$statusLabel}}</span></div><p class="mt-1 text-xs text-slate-500">Salida {{$departure->code}} · {{$passengers->count()}} pasajero(s)</p></div>
  <div class="flex flex-wrap gap-2.5"><a href="{{route('admin.manifests.csv',$departure)}}" class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-slate-100 px-4 text-xs font-bold text-slate-700 hover:bg-slate-200">📥 Descargar CSV</a><button type="button" onclick="window.print()" class="inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-xl bg-[#062c21] px-5 text-xs font-bold text-white shadow-sm hover:bg-emerald-950">🖨️ Imprimir / Guardar PDF</button></div>
 </div>
 <article class="manifest-sheet rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
  <header class="border-b-2 border-slate-800 pb-4 text-center"><span class="block text-[10px] font-black uppercase tracking-[.18em] text-slate-500">Autoridad Marítima Nacional · DICAPI</span><h2 class="mt-1 text-2xl font-black uppercase tracking-tight text-slate-950">Manifiesto oficial de pasajeros</h2><p class="mt-0.5 text-xs font-medium text-slate-600">Capitanía de Puerto · Control de transporte fluvial comercial</p></header>
  <section class="mt-6 grid grid-cols-2 overflow-hidden rounded-xl border border-slate-300 text-xs sm:grid-cols-4">
   <div class="border-b border-r border-slate-300 bg-slate-50/50 p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">Operador fluvial</b><span class="mt-0.5 block font-bold text-slate-900">{{$org->commercial_name?:$org->legal_name}}</span></div>
   <div class="border-b border-r border-slate-300 bg-slate-50/50 p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">RUC</b><span class="mt-0.5 block font-bold text-slate-900">{{$org->ruc}}</span></div>
   <div class="border-b border-r border-slate-300 bg-slate-50/50 p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">Embarcación</b><span class="mt-0.5 block font-bold text-slate-900">{{$vessel->name}}</span></div>
   <div class="border-b border-slate-300 bg-slate-50/50 p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">Matrícula DICAPI</b><span class="mt-0.5 block font-bold text-slate-900">{{$vessel->registration_number?:'Por consignar'}}</span></div>
   <div class="border-r border-slate-300 p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">Patrón / conductor</b><span class="mt-0.5 block font-medium text-slate-700">Por consignar en muelle</span></div>
   <div class="border-r border-slate-300 p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">Fecha y hora de zarpe</b><span class="mt-0.5 block font-bold text-slate-900">{{$departure->departure_at->format('d/m/Y · H:i')}} h</span></div>
   <div class="border-r border-slate-300 p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">Ruta autorizada</b><span class="mt-0.5 block font-bold text-slate-900">{{$route->originPort->city}} ➔ {{$route->destinationPort->city}}</span></div>
   <div class="p-3"><b class="block text-[9px] uppercase tracking-wider text-slate-400">Código de salida</b><span class="mt-0.5 block font-mono font-bold text-slate-900">{{$departure->code}}</span></div>
  </section>
  <div class="mt-6 overflow-x-auto rounded-xl border border-slate-300"><table class="manifest-table"><thead><tr><th>N°</th><th>Asiento</th><th>DNI / Pasaporte</th><th>Nombres y apellidos</th><th>Edad</th><th>Destino</th><th>Código boleto</th><th>Estado / firma</th></tr></thead><tbody>
   @forelse($passengers as $index=>$seat)<tr><td class="text-center">{{$index+1}}</td><td class="text-center font-bold">{{$seat->seat?->code??'—'}}</td><td>{{$seat->document_type}} · {{$seat->document_number}}</td><td class="font-semibold uppercase">{{$seat->passenger_name}}</td><td class="text-center">{{$seat->passenger_age??'—'}}</td><td>{{$destination}}</td><td class="font-mono">{{$seat->ticket?->code??'Pendiente'}}</td><td class="text-center">{{$seat->ticket?->status==='boarded'?'✓ EMBARCADO':'________________'}}</td></tr>
   @empty<tr><td colspan="8" class="py-8 text-center italic text-slate-400">No hay pasajeros registrados para esta salida.</td></tr>@endforelse
  </tbody></table></div>
  <section class="mt-16 grid grid-cols-2 gap-16 text-center text-xs text-slate-600"><div class="mx-auto w-64 border-t border-slate-500 pt-2"><strong class="block text-slate-900">Firma del Patrón / Conductor</strong><span class="text-[9px] text-slate-400">Nombre, DNI y firma</span></div><div class="mx-auto w-64 border-t border-slate-500 pt-2"><strong class="block text-slate-900">Sello y control de despacho en muelle</strong><span class="text-[9px] text-slate-400">Capitanía / Administrador</span></div></section>
  <footer class="mt-8 flex justify-between border-t border-slate-200 pt-3 text-[9px] text-slate-400"><span>Generado por NavegaYA el {{now()->format('d/m/Y H:i')}}</span><span>{{$passengers->count()}} pasajero(s) registrados</span></footer>
 </article>
</div>
</x-app-layout>
