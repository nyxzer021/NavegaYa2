<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">Centro de solicitudes</h2></x-slot>
@php
    $statusLabels=['open'=>'Nueva','in_progress'=>'En atención','waiting_user'=>'Esperando tu respuesta','resolved'=>'Resuelta','closed'=>'Cerrada'];
    $categoryLabels=['reservation'=>'Reserva o boleto','payment'=>'Pago','refund'=>'Reembolso','operation'=>'Operación','fleet'=>'Flota','advertising'=>'Publicidad','account'=>'Cuenta y acceso','technical'=>'Problema técnico','other'=>'Otro'];
@endphp
<div class="mx-auto max-w-6xl space-y-5">
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">✓ {{ session('success') }}</div>@endif
    <section class="flex flex-col gap-4 rounded-2xl bg-[#07392d] p-6 text-white sm:flex-row sm:items-center sm:justify-between">
        <div><p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-300">Comunicación centralizada</p><h1 class="mt-1 text-2xl font-black">{{ $isAdmin ? 'Solicitudes de usuarios y empresas' : 'Mis solicitudes' }}</h1><p class="mt-1 text-sm text-emerald-100/75">{{ $isAdmin ? 'Atiende consultas de transporte, anunciantes y viajeros con trazabilidad.' : 'Envía una consulta y revisa aquí la respuesta del equipo NavegaYA.' }}</p></div>
        <div class="rounded-xl bg-white/10 px-4 py-3 text-center"><strong class="block text-2xl">{{ $tickets->total() }}</strong><span class="text-[10px] font-bold uppercase text-emerald-100">registros</span></div>
    </section>

    @unless($isAdmin)
    <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm" @if($errors->any()) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between p-5"><span><strong class="block text-base">+ Nueva solicitud</strong><small class="text-slate-500">Describe el problema en un solo formulario.</small></span><span class="text-xl group-open:rotate-45">+</span></summary>
        <form method="POST" action="{{ route('support.store') }}" class="grid gap-4 border-t p-5 md:grid-cols-2">@csrf
            <label class="text-xs font-bold">Tema<select name="category" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm">@foreach($categoryLabels as $key=>$label)<option value="{{$key}}" @selected(old('category')===$key)>{{$label}}</option>@endforeach</select></label>
            <label class="text-xs font-bold">Prioridad<select name="priority" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm"><option value="normal">Normal</option><option value="high">Alta: impide continuar</option></select></label>
            <label class="text-xs font-bold md:col-span-2">Asunto<input name="subject" required maxlength="140" value="{{old('subject')}}" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm" placeholder="Ej. No puedo descargar mi boleto"></label>
            <label class="text-xs font-bold md:col-span-2">Detalle<textarea name="description" required maxlength="3000" rows="4" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm" placeholder="Indica qué intentaste, qué ocurrió y el código relacionado.">{{old('description')}}</textarea></label>
            <button class="rounded-xl bg-emerald-800 px-5 py-3 text-sm font-black text-white md:col-span-2">Enviar solicitud</button>
        </form>
    </details>
    @endunless

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b px-5 py-4"><h2 class="font-black">{{ $isAdmin ? 'Bandeja de atención' : 'Historial y respuestas' }}</h2></div>
        <div class="divide-y">
        @forelse($tickets as $ticket)
            <article class="p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><strong>{{$ticket->subject}}</strong><span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-black uppercase">{{$statusLabels[$ticket->status]??$ticket->status}}</span>@if($ticket->priority==='high')<span class="rounded-full bg-rose-50 px-2 py-1 text-[9px] font-black uppercase text-rose-700">Alta</span>@endif</div><p class="mt-1 text-xs text-slate-500">{{$ticket->code}} · {{$categoryLabels[$ticket->category]??$ticket->category}} · {{$ticket->created_at->format('d/m/Y H:i')}}</p>@if($isAdmin)<p class="mt-1 text-xs font-bold text-emerald-800">{{$ticket->organization?->commercial_name?:$ticket->user?->name}} · {{str_replace('_',' ',$ticket->requester_type)}}</p>@endif</div></div>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{$ticket->description}}</p>
                @if($ticket->admin_response)<div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-4"><strong class="text-xs text-emerald-900">Respuesta de NavegaYA</strong><p class="mt-1 whitespace-pre-line text-sm text-emerald-950">{{$ticket->admin_response}}</p></div>@endif
                @if($isAdmin && !in_array($ticket->status,['closed']))<details class="mt-4 rounded-xl bg-slate-50"><summary class="cursor-pointer p-3 text-xs font-black text-emerald-800">Responder o cambiar estado</summary><form method="POST" action="{{route('admin.support.respond',$ticket)}}" class="grid gap-3 border-t p-4">@csrf @method('PATCH')<select name="status" class="rounded-xl border-slate-200 text-sm"><option value="in_progress">En atención</option><option value="waiting_user">Esperando respuesta del solicitante</option><option value="resolved">Resuelta</option><option value="closed">Cerrada</option></select><textarea name="admin_response" required rows="3" class="rounded-xl border-slate-200 text-sm" placeholder="Respuesta clara y próximos pasos">{{$ticket->admin_response}}</textarea><button class="rounded-xl bg-emerald-800 px-4 py-2.5 text-xs font-black text-white">Guardar respuesta</button></form></details>@endif
            </article>
        @empty<div class="p-12 text-center"><span class="text-3xl">✓</span><strong class="mt-2 block">Bandeja al día</strong><p class="text-sm text-slate-500">No hay solicitudes para mostrar.</p></div>@endforelse
        </div>
    </section>
    {{$tickets->links()}}
</div>
</x-app-layout>
