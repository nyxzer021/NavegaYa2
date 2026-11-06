@extends('layouts.admin')

@section('title', 'Ingresos por cargo de servicio · NavegaYA')
@section('header')
<div>
    <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">Monetización NavegaYA</p>
    <h1 class="ny-page-title">Ingresos por cargo de servicio</h1>
</div>
@endsection

@section('content')
<div class="mx-auto max-w-[1600px] space-y-5 pb-8">
    <section class="rounded-3xl bg-[#062c21] px-6 py-6 text-white shadow-lg">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-[.16em] text-emerald-300">Modelo marketplace</span>
                <h2 class="mt-1 text-2xl font-black">Cargo de servicio pagado por el viajero</h2>
                <p class="mt-1 max-w-3xl text-xs leading-relaxed text-emerald-100/70">En la web el pasajero paga la tarifa más el porcentaje del convenio de esa empresa. La empresa recibe el 100% de su tarifa. NavegaYA solo registra ese cargo. La venta en terminal no lo suma.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5 text-[10px] font-black text-emerald-200">Sin fondos de operadores en custodia</span>
        </div>
    </section>

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <label class="text-xs font-bold text-slate-600">Desde<input type="date" name="from" value="{{ $from }}" class="mt-1 block h-10 rounded-lg border-slate-200 text-sm"></label>
        <label class="text-xs font-bold text-slate-600">Hasta<input type="date" name="to" value="{{ $to }}" class="mt-1 block h-10 rounded-lg border-slate-200 text-sm"></label>
        <button class="h-10 rounded-lg bg-[#062c21] px-4 text-xs font-black text-white">Ver periodo</button>
    </form>

    <section class="grid gap-3 sm:grid-cols-3">
        @foreach([
            ['Ventas web', $webSales, 'Total cobrado al pasajero en la web'],
            ['Cargos de servicio', $serviceFees, 'Lo que recibió NavegaYA en esas ventas'],
            ['Comisiones anteriores', $legacyTotal, 'Descuento a la empresa antes de este cambio'],
        ] as [$label, $amount, $copy])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $label }}</span>
                <strong class="mt-2 block text-2xl font-black text-slate-950">S/ {{ number_format((float) $amount, 2) }}</strong>
                <span class="mt-1 block text-[10px] font-semibold text-slate-500">{{ $copy }}</span>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-black text-slate-900">Cargos de servicio por empresa</h2>
            <p class="text-[10px] text-slate-400">El porcentaje vigente se aplica solo a las ventas nuevas.</p>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="p-4">Empresa</th>
                        <th class="p-4 text-center">Convenio</th>
                        <th class="p-4 text-right">Ventas web</th>
                        <th class="p-4 text-right">Cargo de servicio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($summaries as $summary)
                    <tr>
                        <td class="p-4">
                            <a href="{{ route('admin.companies.show', $summary->organization) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $summary->organization->commercial_name ?: $summary->organization->legal_name }}</a>
                            <span class="mt-0.5 block text-[9px] text-slate-400">RUC {{ $summary->organization->ruc }}</span>
                        </td>
                        <td class="p-4 text-center"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700">{{ number_format((float) $summary->organization->commission_rate, 2) }}%</span></td>
                        <td class="p-4 text-right font-bold">S/ {{ number_format($summary->sales, 2) }}</td>
                        <td class="p-4 text-right font-black text-emerald-800">S/ {{ number_format($summary->fees, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-12 text-center text-slate-400">No hay empresas de transporte en este periodo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-black text-slate-900">Comisiones anteriores al cambio</h2>
            <p class="text-[10px] text-slate-400">Estos registros descontaban el porcentaje a la empresa. No se modifican.</p>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-xs">
                <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400">
                    <tr><th class="p-4">Empresa</th><th class="p-4 text-right">Importe guardado</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($legacySummaries as $summary)
                    <tr>
                        <td class="p-4 font-black text-slate-900">{{ $summary->organization->commercial_name ?: $summary->organization->legal_name }}</td>
                        <td class="p-4 text-right font-black">S/ {{ number_format($summary->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="p-8 text-center text-slate-400">No hay comisiones anteriores en este periodo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
