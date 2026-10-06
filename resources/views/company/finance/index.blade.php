@extends('layouts.company')
@section('title', 'Comisiones')
@section('page-title', 'Comisiones NavegaYA')
@section('content')
<div class="space-y-5">
    <section class="rounded-3xl bg-[#062c21] p-6 text-white shadow-lg">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Acuerdo comercial</span><h2 class="mt-1 text-2xl font-black">Ventas y comisión de plataforma</h2><p class="mt-1 text-xs text-emerald-100/70">Tu empresa cobra directamente sus ventas. NavegaYA registra la comisión contractual por el uso del marketplace.</p></div>
            <span class="w-fit rounded-full border border-amber-300/25 bg-amber-400/10 px-3 py-1.5 text-xs font-black text-amber-300">Comisión vigente: {{ number_format($commissionRate, 2) }}%</span>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['GMV del mes', $grossMonth, 'Ventas de tu empresa'],
            ['Comisión del mes', $commissionMonth, 'Generada para NavegaYA'],
            ['Comisión confirmada', $commissionCollected, 'Cobros registrados'],
            ['Comisión pendiente', $commissionPending, 'Por facturar o cobrar'],
        ] as [$label, $amount, $copy])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ $label }}</span><strong class="mt-2 block text-2xl font-black text-slate-900">S/ {{ number_format((float) $amount, 2) }}</strong><span class="mt-1 block text-[10px] text-slate-400">{{ $copy }}</span></article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4"><div><h3 class="font-black text-slate-900">Detalle de transacciones</h3><p class="text-[10px] text-slate-400">El porcentaje queda registrado al momento de confirmar cada venta.</p></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black text-emerald-700">Sin saldos por transferir</span></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-xs">
            <thead class="bg-slate-50 text-[9px] font-black uppercase text-slate-400"><tr><th class="px-5 py-3">Reserva</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3 text-right">Venta</th><th class="px-4 py-3 text-center">Tasa</th><th class="px-4 py-3 text-right">Comisión</th><th class="px-5 py-3">Estado</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($recentPayments as $payment)
                <tr><td class="px-5 py-4 font-black">{{ $payment->reservation?->code ?? $payment->provider_reference }}</td><td class="px-4 py-4 text-slate-500">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</td><td class="px-4 py-4 text-right font-bold">S/ {{ number_format((float) $payment->amount, 2) }}</td><td class="px-4 py-4 text-center">{{ number_format((float) $payment->commission_rate, 2) }}%</td><td class="px-4 py-4 text-right font-black text-emerald-700">S/ {{ number_format((float) $payment->commission_amount, 2) }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[9px] font-black {{ $payment->commission_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $payment->commission_status === 'paid' ? 'Cobrada' : 'Pendiente' }}</span></td></tr>
            @empty<tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400">Todavía no existen transacciones confirmadas.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
