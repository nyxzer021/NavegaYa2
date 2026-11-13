@extends('layouts.company')
@section('title', 'Embarque y manifiestos')
@section('page-title', 'Embarque y manifiestos')
@section('content')
@php
    $route = $departure->transportRoute;
    $origin = $route->originPort?->city ?? $route->originPort?->name ?? 'Origen';
    $destination = $route->destinationPort?->city ?? $route->destinationPort?->name ?? 'Destino';
    $masterCode = $route->masterRoute?->code ?? $route->code ?? 'Tramo oficial';
@endphp
<div class="mx-auto max-w-5xl space-y-5">
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    @unless($canBoard)<div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">Asigna un capitán con licencia vigente</div>@endunless
    <section class="overflow-hidden rounded-2xl bg-[#062c21] px-6 py-5 text-white shadow-sm">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-sm font-semibold text-emerald-200">{{ $masterCode }} · Despacho fluvial</p>
                <h2 class="mt-1 text-2xl font-black">{{ $origin }} → {{ $destination }}</h2>
                <p class="mt-1 text-sm text-emerald-100">{{ $departure->vessel?->name ?? 'Embarcación por asignar' }} · {{ $departure->departure_at?->copy()->timezone('America/Lima')->format('d/m/Y') }}</p>
            </div>
            <div class="text-left sm:text-right">
                <strong class="block text-3xl font-black text-[#fbbf24]">{{ $departure->departure_at?->copy()->timezone('America/Lima')->format('H:i') }}</strong>
                <span class="text-sm text-emerald-100">{{ $boardingStats['boarded'] }} de {{ $boardingStats['total'] }} a bordo · {{ $boardingStats['pending'] }} pendientes</span>
            </div>
        </div>
    </section>
    <section class="rounded-2xl border border-[#d5e6df] bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('company.boarding.show', $departure) }}" class="flex flex-col gap-2 sm:flex-row">
            <label class="relative flex-1">
                <span class="sr-only">Escanear DNI o Código QR</span>
                <input name="q" value="{{ $term }}" autofocus autocomplete="off" placeholder="Escanear DNI o Código QR" class="h-10 w-full rounded-xl border border-[#d5e6df] bg-white px-3 text-sm font-semibold text-slate-900">
            </label>
            <button class="h-10 rounded-xl border border-[#d5e6df] bg-white px-4 text-sm font-bold text-emerald-900">Buscar pasajero</button>
            @if($term !== '')<a href="{{ route('company.boarding.show', $departure) }}" class="flex h-10 items-center justify-center rounded-xl px-4 text-sm font-bold text-slate-600">Limpiar</a>@endif
        </form>
        <p class="mt-2 text-sm text-slate-500">La validación está restringida a los boletos de esta salida.</p>
    </section>
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h3 class="text-lg font-black text-slate-950">Pasajeros reservados</h3>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ \App\Support\SpanishCount::phrase($tickets->count(), 'resultado', 'resultados') }}</span>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($tickets as $ticket)
                @php($seat = $ticket->reservationSeat)
                <article class="space-y-2 px-5 py-4 {{ $ticket->status === 'boarded' ? 'bg-[#ecfdf5]' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <strong class="text-slate-950">{{ $seat->passenger_name ?? 'Pasajero sin nombre' }}</strong>
                            <p class="text-sm text-slate-600">Asiento {{ $seat->seat?->code ?? '—' }} · {{ strtoupper($seat->document_type ?? 'DNI') }} {{ $seat->document_number ?? '—' }}</p>
                            <p class="text-sm text-slate-500">Boleto {{ $ticket->code }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $ticket->status === 'boarded' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $ticket->status === 'boarded' ? 'Abordó' : 'Pendiente' }}</span>
                    </div>
                    @if($canBoard)
                    <form method="POST" action="{{ route('company.boarding.toggle', [$departure, $ticket]) }}">
                        @csrf @method('PATCH')
                        <button class="h-10 rounded-xl px-4 text-sm font-bold {{ $ticket->status === 'boarded' ? 'border border-rose-200 bg-white text-rose-700' : 'bg-amber-400 text-emerald-950' }}">{{ $ticket->status === 'boarded' ? 'Anular abordaje' : 'Marcar a bordo' }}</button>
                    </form>
                    @endif
                </article>
            @empty
                <p class="px-5 py-12 text-center text-sm text-slate-500">{{ $term !== '' ? 'No se encontró un pasajero de esta salida con ese dato.' : 'No hay boletos emitidos para esta salida.' }}</p>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                    <tr><th class="px-5 py-3">Asiento</th><th class="px-4 py-3">Pasajero</th><th class="px-4 py-3">DNI / Documento</th><th class="px-4 py-3">Estado</th><th class="px-5 py-3 text-right">Acción</th></tr>
                </thead>
                <tbody>
                @forelse($tickets as $ticket)
                    @php($seat = $ticket->reservationSeat)
                    <tr class="border-t border-slate-100 {{ $ticket->status === 'boarded' ? 'bg-[#ecfdf5]' : '' }}">
                        <td class="px-5 py-4"><span class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl bg-slate-100 px-2 font-black text-slate-950">{{ $seat->seat?->code ?? '—' }}</span></td>
                        <td class="px-4 py-4"><strong class="block text-slate-950">{{ $seat->passenger_name ?? 'Pasajero sin nombre' }}</strong><span class="text-xs text-slate-500">Boleto {{ $ticket->code }}</span></td>
                        <td class="px-4 py-4"><span class="font-bold text-slate-800">{{ strtoupper($seat->document_type ?? 'DNI') }}</span><span class="ml-2 text-slate-500">{{ $seat->document_number ?? '—' }}</span></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $ticket->status === 'boarded' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $ticket->status === 'boarded' ? 'Abordó' : 'Pendiente' }}</span></td>
                        <td class="px-5 py-4 text-right">
                            @if($canBoard)
                            <form method="POST" action="{{ route('company.boarding.toggle', [$departure, $ticket]) }}">
                                @csrf @method('PATCH')
                                <button class="h-10 rounded-xl px-4 text-sm font-bold {{ $ticket->status === 'boarded' ? 'border border-rose-200 bg-white text-rose-700' : 'bg-amber-400 text-emerald-950' }}">{{ $ticket->status === 'boarded' ? 'Anular abordaje' : 'Marcar a bordo' }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">{{ $term !== '' ? 'No se encontró un pasajero de esta salida con ese dato.' : 'No hay boletos emitidos para esta salida.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
