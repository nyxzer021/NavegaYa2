@extends('layouts.company')
@section('title', 'Cargo de servicio NavegaYA')
@section('page-title', 'Cargo de servicio NavegaYA')
@section('content')
<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-black text-slate-950">Comisión del convenio</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Tu empresa cobra la tarifa del pasaje. NavegaYA cobra el porcentaje pactado. Aquí ves esa tarifa y la comisión, sin la cuenta bancaria de la plataforma.</p>
            </div>
            <a href="{{ route('company.sales.index') }}" class="inline-flex min-h-10 w-fit items-center rounded-xl border border-emerald-800 px-4 text-sm font-bold text-emerald-900">Ver boletos vendidos</a>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['Tarifa de tu empresa', 'S/ '.number_format($companyFare, 2), 'Pasajes sin el cargo que paga el viajero'],
            ['Convenio', number_format($commissionRate, 2).'%', 'Porcentaje vigente de tu empresa'],
            ['Comisión generada', 'S/ '.number_format($commissionGenerated, 2), 'Lo que corresponde a NavegaYA'],
            ['Comisión cobrada', 'S/ '.number_format($commissionCollected, 2), 'Ya registrada por NavegaYA'],
            ['Por cobrar', 'S/ '.number_format($commissionReceivable, 2), 'Pendiente de registro'],
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
            <h3 class="font-black text-slate-900">Pasajes confirmados</h3>
            <p class="text-xs text-slate-500">Cada fila guarda la tarifa y la comisión del momento en que se confirmó el boleto.</p>
        </header>
        @php($commissionLabels = ['pending' => 'Por cobrar', 'invoiced' => 'Facturada', 'paid' => 'Cobrada'])
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($recentPayments as $payment)
                <article class="space-y-1 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <strong class="text-slate-950">{{ $payment['code'] }}</strong>
                        <span class="text-sm font-semibold text-slate-700">{{ $commissionLabels[$payment['commission_status']] ?? 'Por confirmar' }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $payment['channel'] }} · {{ $payment['paid_at']?->format('d/m/Y H:i') ?? '—' }}</p>
                    <p class="text-sm text-slate-800">Tarifa S/ {{ number_format($payment['fare'], 2) }} · Comisión S/ {{ number_format($payment['commission'], 2) }}</p>
                </article>
            @empty
                <p class="px-5 py-14 text-center text-sm text-slate-500">Todavía no hay pasajes confirmados.</p>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Reserva</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Canal</th>
                        <th class="px-4 py-3 text-right">Tarifa</th>
                        <th class="px-4 py-3 text-right">Comisión</th>
                        <th class="px-5 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($recentPayments as $payment)
                    <tr>
                        <td class="px-5 py-4 font-black">{{ $payment['code'] }}</td>
                        <td class="px-4 py-4 text-slate-500">{{ $payment['paid_at']?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-4">{{ $payment['channel'] }}</td>
                        <td class="px-4 py-4 text-right font-black">S/ {{ number_format($payment['fare'], 2) }}</td>
                        <td class="px-4 py-4 text-right font-black text-emerald-800">S/ {{ number_format($payment['commission'], 2) }}</td>
                        <td class="px-5 py-4">{{ $commissionLabels[$payment['commission_status']] ?? 'Por confirmar' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-500">Todavía no hay pasajes confirmados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
