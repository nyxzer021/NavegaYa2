@extends('layouts.company')
@section('title', 'Cargo de servicio NavegaYA')
@section('page-title', 'Cargo de servicio NavegaYA')
@section('content')
<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-black text-slate-950">Cargo de servicio NavegaYA: lo paga el viajero en la web. Tu tarifa se recibe completa.</h2>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">El porcentaje de tu convenio se suma al pasaje cuando la venta es por la web. En terminal o agencia ese cargo es cero y recibes el 100% de la tarifa.</p>
            </div>
            <a href="{{ route('company.sales.index') }}" class="inline-flex min-h-10 w-fit items-center rounded-xl border border-emerald-800 px-4 text-sm font-bold text-emerald-900">Ver boletos vendidos</a>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Convenio', number_format($commissionRate, 2).'%', 'Porcentaje vigente de tu empresa'],
            ['Tarifa de tu empresa', 'S/ '.number_format($companyFare, 2), 'Lo que recibes en las ventas nuevas'],
            ['Cargos pagados por los viajeros', 'S/ '.number_format($travelerFees, 2), 'Cargo de servicio de la web'],
            ['Ventas', (string) $salesCount, 'Pasajes confirmados con este modelo'],
        ] as [$label, $amount, $copy])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-xs font-bold text-slate-500">{{ $label }}</span>
                <strong class="mt-2 block text-2xl font-black text-slate-900">{{ $amount }}</strong>
                <span class="mt-1 block text-xs text-slate-500">{{ $copy }}</span>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4">
            <h3 class="font-black text-slate-900">Ventas nuevas</h3>
            <p class="text-xs text-slate-500">La tarifa es tuya. El cargo lo pagó el viajero y no se descuenta de tu empresa.</p>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Reserva</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Canal</th>
                        <th class="px-4 py-3 text-right">Tu tarifa</th>
                        <th class="px-5 py-3 text-right">Cargo del viajero</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($recentPayments as $payment)
                    <tr>
                        <td class="px-5 py-4 font-black">{{ $payment['code'] }}</td>
                        <td class="px-4 py-4 text-slate-500">{{ $payment['paid_at']?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-4">{{ $payment['channel'] }}</td>
                        <td class="px-4 py-4 text-right font-black">S/ {{ number_format($payment['fare'], 2) }}</td>
                        <td class="px-5 py-4 text-right font-black text-emerald-800">S/ {{ number_format($payment['fee'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-14 text-center text-sm text-slate-500">Todavía no hay ventas con este modelo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4">
            <h3 class="font-black text-slate-900">Comisiones anteriores al cambio</h3>
            <p class="text-xs text-slate-500">Estos boletos descontaron el porcentaje a tu empresa. El importe se muestra como se guardó.</p>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Reserva</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3 text-right">Tarifa</th>
                        <th class="px-5 py-3 text-right">Comisión anterior</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($legacyPayments as $payment)
                    <tr>
                        <td class="px-5 py-4 font-black">{{ $payment['code'] }}</td>
                        <td class="px-4 py-4 text-slate-500">{{ $payment['paid_at']?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-4 text-right font-black">S/ {{ number_format($payment['fare'], 2) }}</td>
                        <td class="px-5 py-4 text-right font-black">S/ {{ number_format($payment['commission'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">No hay comisiones anteriores.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
