<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">Auditoría y seguridad</h2></x-slot>
@php
    $eventLabels = ['created' => 'Registro creado', 'updated' => 'Registro modificado', 'deleted' => 'Registro eliminado'];
    $eventTones = ['created' => 'bg-emerald-50 text-emerald-700', 'updated' => 'bg-sky-50 text-sky-700', 'deleted' => 'bg-rose-50 text-rose-700'];
@endphp
<div class="mx-auto max-w-7xl space-y-5">
    <section class="rounded-3xl bg-gradient-to-r from-emerald-950 to-emerald-800 p-7 text-white shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-[.18em] text-amber-300">Trazabilidad administrativa</p>
        <h1 class="mt-2 text-2xl font-black">Historial completo de operaciones</h1>
        <p class="mt-2 max-w-3xl text-xs leading-6 text-emerald-100">Registra altas, modificaciones y eliminaciones, junto con el responsable, la fecha, la ruta del sistema y los valores anteriores y nuevos. Las credenciales y tokens nunca se almacenan en este historial.</p>
    </section>
    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_220px_auto]">
        <input name="search" value="{{$search}}" placeholder="Buscar responsable por nombre o correo" class="rounded-xl border-slate-200 bg-slate-50 text-xs">
        <select name="event" class="rounded-xl border-slate-200 bg-slate-50 text-xs"><option value="">Todas las acciones</option>@foreach($eventLabels as $value=>$label)<option value="{{$value}}" @selected($event===$value)>{{$label}}</option>@endforeach</select>
        <select name="model" class="rounded-xl border-slate-200 bg-slate-50 text-xs"><option value="">Todos los módulos</option>@foreach($models as $availableModel)<option value="{{$availableModel}}" @selected($model===$availableModel)>{{$availableModel}}</option>@endforeach</select>
        <button class="rounded-xl bg-emerald-950 px-5 py-2.5 text-xs font-black text-white">Aplicar filtros</button>
    </form>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Acción</th><th class="px-4 py-3">Registro</th><th class="px-4 py-3">Responsable</th><th class="px-4 py-3">Origen</th><th class="px-4 py-3">Cambios</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse($logs as $log)
            @php($old = $log->changes['old'] ?? []) @php($new = $log->changes['new'] ?? []) @php($fields = array_unique(array_merge(array_keys($old), array_keys($new))))
            <tr class="align-top">
                <td class="whitespace-nowrap px-4 py-4"><strong>{{$log->created_at->format('d/m/Y')}}</strong><br><span class="text-slate-400">{{$log->created_at->format('H:i:s')}}</span></td>
                <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-black {{$eventTones[$log->event] ?? 'bg-slate-100 text-slate-600'}}">{{$eventLabels[$log->event] ?? $log->event}}</span></td>
                <td class="px-4 py-4"><strong>{{class_basename($log->subject_type)}} #{{$log->subject_id}}</strong><br><span class="text-[10px] text-slate-400">{{$log->route_name ?: 'Proceso interno'}} · {{$log->http_method ?: 'CLI'}}</span></td>
                <td class="px-4 py-4"><strong>{{$log->user?->name ?? 'Sistema o visitante'}}</strong>@if($log->user)<br><span class="text-[10px] text-slate-400">{{$log->user->email}}</span>@endif</td>
                <td class="px-4 py-4 text-[10px] text-slate-500">IP {{$log->ip_address ?: 'no registrada'}}<br>{{Str::limit($log->user_agent, 42)}}</td>
                <td class="min-w-72 px-4 py-4">@forelse($fields as $field)<div class="mb-1 rounded-lg bg-slate-50 px-2 py-1"><b>{{$field}}:</b> @if(array_key_exists($field,$old))<span class="text-rose-600">{{Str::limit(is_scalar($old[$field])?(string)$old[$field]:json_encode($old[$field]),45)}}</span> → @endif<span class="text-emerald-700">{{Str::limit(is_scalar($new[$field]??null)?(string)($new[$field]??'—'):json_encode($new[$field]??null),45)}}</span></div>@empty<span class="text-slate-400">Sin campos disponibles</span>@endforelse</td>
            </tr>
        @empty<tr><td colspan="6" class="p-12 text-center text-slate-400">Todavía no existen operaciones para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div>
        <div class="border-t border-slate-100 p-4">{{$logs->links()}}</div>
    </section>
</div>
</x-app-layout>
