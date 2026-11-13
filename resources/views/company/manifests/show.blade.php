@extends('layouts.company')
@section('title','Manifiesto DICAPI')
@section('page-title','Manifiesto oficial')
@section('content')
@php
    $route = $departure->transportRoute;
    $vessel = $departure->vessel;
    $statusLabel = match ($departure->status) {
        'boarding' => 'Abordando',
        'departed' => 'Zarpó',
        'cancelled' => 'Cancelado',
        default => 'Programado',
    };
@endphp
<style>
    .manifest-table { width: 100%; border-collapse: collapse; }
    .manifest-table th, .manifest-table td { border: 1px solid #cbd5e1; padding: 8px 6px; font-size: 13px; }
    .manifest-table th { background: #f8fafc; color: #475569; font-size: 12px; text-align: left; }
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        aside, body > div > section > header, .manifest-actions { display: none !important; }
        body > div, body > div > section { display: block !important; margin: 0 !important; }
        .manifest-content { padding: 0 !important; }
        .manifest-sheet { border: 0 !important; box-shadow: none !important; padding: 0 !important; }
        .manifest-table th, .manifest-table td { font-size: 10px; }
    }
</style>
<div class="manifest-content mx-auto max-w-7xl space-y-5">
    <div class="manifest-actions flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
        <div>
            <a href="{{ route('company.departures.index') }}" class="text-sm font-bold text-emerald-800">Volver a mis salidas</a>
            <div class="mt-2 flex items-center gap-2">
                <h1 class="text-xl font-black text-slate-950">Manifiesto oficial de pasajeros</h1>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $statusLabel }}</span>
            </div>
            <p class="text-sm text-slate-500">Salida {{ $departure->code }} · {{ \App\Support\SpanishCount::phrase($passengers->count(), 'pasajero', 'pasajeros') }}</p>
        </div>
        <button type="button" onclick="window.print()" class="h-10 rounded-xl bg-[#062c21] px-5 text-sm font-bold text-white">Imprimir / Guardar PDF</button>
    </div>
    <article class="manifest-sheet rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <header class="border-b-2 border-slate-800 pb-4 text-center">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Autoridad Marítima Nacional · DICAPI</span>
            <h2 class="mt-1 text-2xl font-black uppercase">Manifiesto oficial de pasajeros</h2>
        </header>
        <section class="mt-6 grid grid-cols-2 overflow-hidden rounded-xl border border-slate-300 text-sm sm:grid-cols-4">
            <div class="border-b border-r border-slate-300 p-3"><b class="block text-xs text-slate-500">Operador</b>{{ $organization->commercial_name ?: $organization->legal_name }}</div>
            <div class="border-b border-r border-slate-300 p-3"><b class="block text-xs text-slate-500">RUC</b>{{ $organization->ruc ?? 'Por consignar' }}</div>
            <div class="border-b border-r border-slate-300 p-3"><b class="block text-xs text-slate-500">Embarcación</b>{{ $vessel->name }}</div>
            <div class="border-b border-slate-300 p-3"><b class="block text-xs text-slate-500">Matrícula DICAPI</b>{{ $vessel->registration_number ?: 'Por consignar' }}</div>
            <div class="border-r border-slate-300 p-3"><b class="block text-xs text-slate-500">Fecha y hora</b>{{ $departure->departure_at->format('d/m/Y · H:i') }} h</div>
            <div class="border-r border-slate-300 p-3"><b class="block text-xs text-slate-500">Ruta</b>{{ $route->originPort->city }} → {{ $route->destinationPort->city }}</div>
            <div class="border-r border-slate-300 p-3"><b class="block text-xs text-slate-500">Salida</b>{{ $departure->code }}</div>
            <div class="p-3"><b class="block text-xs text-slate-500">Patrón</b>Por consignar en muelle</div>
        </section>
        <div class="mt-6 overflow-x-auto">
            <table class="manifest-table">
                <thead><tr><th>N°</th><th>Asiento</th><th>Documento</th><th>Nombres y apellidos</th><th>Edad</th><th>Destino</th><th>Código boleto</th><th>Estado / firma</th></tr></thead>
                <tbody>
                @forelse($passengers as $index => $seat)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $seat->seat?->code ?? '—' }}</td>
                        <td>{{ $seat->document_type }} · {{ $seat->document_number }}</td>
                        <td class="font-semibold uppercase">{{ $seat->passenger_name }}@if($seat->passenger_type === 'infant') · Infante @endif</td>
                        <td>{{ $seat->passenger_age ?? '—' }}</td>
                        <td>{{ $destination }}</td>
                        <td>{{ $seat->ticket?->code ?? 'Pendiente' }}</td>
                        <td>{{ $seat->ticket?->status === 'boarded' ? 'Embarcado' : '________________' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">No hay pasajeros registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <section class="mt-16 grid grid-cols-2 gap-16 text-center text-sm">
            <div class="border-t border-slate-500 pt-2">Firma del Patrón / Conductor</div>
            <div class="border-t border-slate-500 pt-2">Sello y control de despacho</div>
        </section>
    </article>
</div>
@endsection
