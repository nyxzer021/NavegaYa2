@extends('layouts.company')
@section('title','Mis salidas')
@section('page-title','Mis Salidas y Despacho')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
 <section class="flex flex-col justify-between gap-5 overflow-hidden rounded-3xl p-6 text-white shadow-lg sm:flex-row sm:items-center" style="background:linear-gradient(110deg,#062c21 0%,#0b4938 58%,#11634c 100%)"><div><span class="text-[10px] font-bold uppercase tracking-[.16em] text-emerald-300">Panel operativo</span><h2 class="mt-2 text-2xl font-black">Organiza las salidas de hoy</h2><p class="mt-1 text-xs text-emerald-100/80">Programa viajes, controla el embarque y consulta el estado de cada unidad.</p></div><div class="flex flex-wrap gap-2"><a href="{{ route('company.counter.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-white/20 bg-white/10 px-4 text-xs font-bold text-white transition hover:bg-white/15">📱 Abrir embarque</a>@unless(auth()->user()->roles()->where('code','company_counter')->exists())<a href="{{ route('company.departures.create') }}" class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-amber-500 px-5 text-xs font-black text-slate-950 transition hover:bg-amber-400">＋ Programar salida</a>@endunless</div></section>
 <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
 @foreach([['Salidas hoy',$todayDeparturesCount,'🧭'],['Pasajeros hoy',$todayPassengersCount,'👥'],['Venta bruta','S/ '.number_format($todayGrossRevenue,2),'💰'],['Comisión ('.$commissionRate.'%)','S/ '.number_format($todayCommissionRetained,2),'📈'],['Carga pendiente',$pendingCargoCount,'📦']] as [$label,$value,$icon])<article class="rounded-2xl border border-slate-200/90 bg-white px-4 py-3.5 shadow-sm {{ $loop->last ? 'sm:col-span-2 lg:col-span-1' : '' }}"><div class="flex items-center justify-between gap-3"><div><span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</span><strong class="mt-1 block text-lg font-black text-slate-900">{{ $value }}</strong></div><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-50 text-sm">{{ $icon }}</span></div></article>@endforeach
 </section>
 <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Agenda operativa</span><h3 class="text-lg font-black text-slate-900">Salidas programadas</h3></div><div class="flex gap-2"><a href="{{ route('company.departures.index') }}" class="rounded-lg px-3 py-2 text-xs font-bold {{ !$filter?'bg-[#062c21] text-white':'bg-slate-100 text-slate-600' }}">Todas</a><a href="{{ route('company.departures.index',['filter'=>'today']) }}" class="rounded-lg px-3 py-2 text-xs font-bold {{ $filter==='today'?'bg-[#062c21] text-white':'bg-slate-100 text-slate-600' }}">Hoy</a></div></div>
  <div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3">Fecha / hora</th><th class="px-4 py-3">Tramo oficial</th><th class="px-4 py-3">Unidad</th><th class="px-4 py-3">Ocupación</th><th class="px-4 py-3">Tarifa</th><th class="px-4 py-3">Estado</th><th class="px-5 py-3 text-right">Despacho</th></tr></thead><tbody>
  @forelse($departures as $row)
   <tr class="border-t border-slate-100">
    <td class="px-5 py-4"><strong class="text-slate-900">{{ $row['departure_at']->format('d/m/Y') }}</strong><span class="block text-base font-black">{{ $row['departure_at']->format('H:i') }}</span></td>
    <td class="px-4 py-4"><strong class="text-slate-900">{{ $row['origin'] }} ➔ {{ $row['destination'] }}</strong><span class="block text-[10px] {{ $row['is_air'] ? 'text-sky-600' : 'text-emerald-700' }}">{{ $row['is_air'] ? '✈️ Aéreo' : '🚤 Fluvial' }} · {{ $row['master_code'] ?? 'Tramo legado' }}</span></td>
    <td class="px-4 py-4">{{ $row['unit'] }}<span class="block text-[10px] text-slate-400">{{ $row['registration'] ?? 'Sin matrícula' }}</span></td>
    <td class="px-4 py-4"><div class="flex justify-between text-[10px]"><strong>{{ $row['booked'] }}/{{ $row['capacity'] }}</strong><span>{{ $row['occupancy'] }}%</span></div><div class="mt-1 h-2 w-32 overflow-hidden rounded-full bg-slate-100"><i class="block h-full rounded-full bg-emerald-600" style="width:{{ $row['occupancy'] }}%"></i></div></td>
    <td class="px-4 py-4 font-black text-slate-900">S/ {{ number_format($row['fare'], 2) }}</td>
    <td class="px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-700">{{ strtoupper($row['status']) }}</span></td>
    <td class="px-5 py-4">
     <div class="flex justify-end gap-2">
      @if(!$row['is_air'])
       <a href="{{ route('company.departures.manifest', $row['model']) }}" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-700">📄 Manifiesto</a>
       @unless(auth()->user()->roles()->where('code', 'company_counter')->exists())
        <a href="{{ route('company.departures.edit', $row['model']) }}" class="rounded-lg bg-amber-50 px-3 py-2 font-bold text-amber-800">Editar</a>
       @endunless
       <a href="{{ route('company.boarding.show', $row['model']->id) }}" class="rounded-lg bg-[#062c21] px-3 py-2 font-bold text-white">📱 Embarque</a>
      @else
       <a href="{{ route('company.counter.index', ['air_departure' => $row['model']->id]) }}" class="rounded-lg bg-[#062c21] px-3 py-2 font-bold text-white">📱 Embarque</a>
      @endif
     </div>
    </td>
   </tr>
  @empty<tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No hay salidas para el filtro seleccionado.</td></tr>@endforelse
  </tbody></table></div>@if($departures->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $departures->links() }}</div>@endif
 </section>
</div>
@endsection
