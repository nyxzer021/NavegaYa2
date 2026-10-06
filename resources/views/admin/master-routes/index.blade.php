<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">Tramos Maestros</h2></x-slot>
<div class="mx-auto max-w-7xl space-y-5">
 <section class="flex flex-col justify-between gap-5 rounded-3xl bg-[#062c21] p-6 text-white shadow-xl sm:flex-row sm:items-center sm:p-8">
  <div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Catálogo regional · fuente oficial</span><h1 class="mt-2 text-2xl font-black sm:text-3xl">Tramos Maestros</h1><p class="mt-1 max-w-2xl text-xs leading-relaxed text-emerald-100/75">Define ciudades, terminales y corredores autorizados. Los operadores solo pueden programar salidas sobre estos tramos.</p></div>
  <a href="{{ route('admin.master-routes.create') }}" class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-amber-500 px-5 text-xs font-black text-slate-950 hover:bg-amber-600">＋ Nuevo tramo maestro</a>
 </section>
 @foreach(['success'=>'emerald','error'=>'rose'] as $key=>$tone)@if(session($key))<div class="rounded-xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-4 py-3 text-xs font-semibold text-{{ $tone }}-800">{{ session($key) }}</div>@endif @endforeach
 <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm">
  <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-black text-slate-900">Conexiones oficiales de Loreto</h2><p class="text-[11px] text-slate-500">Los estados hidrológicos impiden nuevas programaciones sin borrar el historial.</p></div>
  <div class="overflow-x-auto"><table class="w-full min-w-[940px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3">Código / modalidad</th><th class="px-4 py-3">Trayecto oficial</th><th class="px-4 py-3">Cuenca / corredor</th><th class="px-4 py-3">Duración</th><th class="px-4 py-3">Estado</th><th class="px-5 py-3 text-right">Gobernanza</th></tr></thead><tbody>
  @forelse($masterRoutes as $route)
   @php($linked=($route->transport_routes_count??0)+($route->air_routes_count??0))
   <tr class="border-t border-slate-100 align-top">
    <td class="px-5 py-4"><strong class="block text-slate-900">{{ $route->code }}</strong><span class="mt-1 inline-flex rounded-full px-2 py-1 text-[10px] font-bold {{ $route->modality==='aereo'?'bg-sky-50 text-sky-700':'bg-emerald-50 text-emerald-700' }}">{{ $route->modality==='aereo'?'✈️ Aéreo':'🚤 Fluvial' }}</span></td>
    <td class="px-4 py-4"><strong class="text-slate-900">{{ $route->origin_city }} ➔ {{ $route->destination_city }}</strong><span class="mt-1 block text-[10px] text-slate-500">{{ $route->originPort->name ?? 'Terminal pendiente' }} ➔ {{ $route->destinationPort->name ?? 'Terminal pendiente' }}</span></td>
    <td class="px-4 py-4 text-slate-600">{{ $route->river_basin ?: $route->corridor ?: 'No especificado' }}</td><td class="px-4 py-4 font-semibold text-slate-700">{{ $route->estimated_duration_text ?: 'Por definir' }}</td>
    <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $route->status==='active'?'bg-emerald-50 text-emerald-700':($route->status==='suspended_river_level'?'bg-amber-50 text-amber-700':'bg-rose-50 text-rose-700') }}">{{ $route->status==='active'?'● Operativo':($route->status==='suspended_river_level'?'⚠ Precaución / suspendido':'● Mantenimiento') }}</span><span class="mt-1 block text-[10px] text-slate-400">{{ $linked }} operador(es) vinculado(s)</span></td>
    <td class="px-5 py-4"><div class="flex justify-end gap-2"><a href="{{ route('admin.master-routes.edit',$route) }}" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-700 hover:border-emerald-500">Editar estado</a>@if($linked===0)<form method="POST" action="{{ route('admin.master-routes.destroy',$route) }}">@csrf @method('DELETE')<button class="rounded-lg px-3 py-2 font-bold text-rose-600">Eliminar</button></form>@endif</div></td>
   </tr>
  @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Aún no hay tramos maestros. Crea el primer corredor oficial.</td></tr>@endforelse
  </tbody></table></div>
  @if($masterRoutes->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $masterRoutes->links() }}</div>@endif
 </section>
</div>
</x-app-layout>
