@extends('layouts.company')
@section('title', 'Ventas y facturación')
@section('page-title', 'Ventas y facturación')
@section('content')
<div class="space-y-5">
    <section class="rounded-3xl bg-[#062c21] p-6 text-white shadow-lg">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Resultados comerciales</span><h2 class="mt-1 text-2xl font-black">Ingresos de tu operación</h2><p class="mt-1 text-xs text-emerald-100/70">Consulta los pagos confirmados por pasajes vendidos en línea y en el muelle.</p></div>
            <a href="{{ route('company.sales.export') }}" class="w-fit rounded-xl bg-amber-400 px-4 py-2.5 text-xs font-black text-slate-950">Descargar reporte CSV</a>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Cobrado hoy', $grossToday, 'Pagos confirmados'],
            ['Cobrado este mes', $grossMonth, 'Ingresos por pasajes'],
            ['Transacciones del mes', $transactionsMonth, 'Operaciones pagadas'],
            ['Venta promedio', $averageTicketMonth, 'Importe promedio por operación'],
        ] as [$label, $amount, $copy])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ $label }}</span><strong class="mt-2 block text-2xl font-black text-slate-900">{{ $label === 'Transacciones del mes' ? $amount : 'S/ '.number_format((float) $amount, 2) }}</strong><span class="mt-1 block text-[10px] text-slate-400">{{ $copy }}</span></article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4"><h3 class="font-black text-slate-900">Pagos recientes</h3><p class="text-[10px] text-slate-400">Importes efectivamente confirmados para tu empresa.</p></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-xs">
            <thead class="bg-slate-50 text-[9px] font-black uppercase text-slate-400"><tr><th class="px-5 py-3">Reserva</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Canal</th><th class="px-4 py-3">Método</th><th class="px-4 py-3 text-right">Importe cobrado</th><th class="px-5 py-3">Estado</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($recentPayments as $payment)
                <tr><td class="px-5 py-4 font-black">{{ $payment->reservation?->code ?? $payment->provider_reference }}</td><td class="px-4 py-4 text-slate-500">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</td><td class="px-4 py-4">{{ $payment->reservation?->sales_channel === 'counter' ? 'Muelle' : 'En línea' }}</td><td class="px-4 py-4">{{ $payment->method === 'cash_dock' ? 'Efectivo' : ucfirst($payment->method) }}</td><td class="px-4 py-4 text-right font-black text-emerald-700">S/ {{ number_format((float) $payment->amount, 2) }}</td><td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black text-emerald-700">Confirmado</span></td></tr>
            @empty<tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400">Todavía no existen transacciones confirmadas.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
