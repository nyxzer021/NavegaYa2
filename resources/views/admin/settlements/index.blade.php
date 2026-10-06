@extends('layouts.admin')

@section('title', 'Comisiones y Facturación · NavegaYA')
@section('header')
<div>
    <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">Monetización NavegaYA</p>
    <h1 class="ny-page-title">Comisiones y Facturación</h1>
</div>
@endsection

@section('content')
<div class="mx-auto max-w-[1600px] space-y-5 pb-8">
    <section class="rounded-3xl bg-[#062c21] px-6 py-6 text-white shadow-lg">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div><span class="text-[10px] font-black uppercase tracking-[.16em] text-emerald-300">Modelo marketplace</span><h2 class="mt-1 text-2xl font-black">Ingresos comerciales de la plataforma</h2><p class="mt-1 max-w-3xl text-xs leading-relaxed text-emerald-100/70">Cada operador cobra directamente sus ventas. Aquí se controla únicamente la comisión contractual que corresponde a NavegaYA.</p></div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5 text-[10px] font-black text-emerald-200">✓ Sin fondos de operadores en custodia</span>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['GMV del marketplace', $grossVolume, 'Volumen vendido por los operadores', 'border-slate-200 text-slate-950'],
            ['Comisión generada', $commissionGenerated, 'Según el acuerdo de cada empresa', 'border-emerald-200 text-emerald-700'],
            ['Comisión cobrada', $commissionCollected, 'Ingresos confirmados de NavegaYA', 'border-cyan-200 text-cyan-700'],
            ['Por cobrar', $commissionReceivable, 'Pendiente de facturación o cobro', 'border-amber-200 text-amber-700'],
        ] as [$label, $amount, $copy, $style])
            <article class="rounded-2xl border bg-white p-5 shadow-sm {{ $style }}"><span class="text-[9px] font-black uppercase tracking-wider opacity-65">{{ $label }}</span><strong class="mt-2 block text-2xl font-black">S/ {{ number_format((float) $amount, 2) }}</strong><span class="mt-1 block text-[10px] font-semibold opacity-60">{{ $copy }}</span></article>
        @endforeach
    </section>

    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4"><h2 class="font-black text-slate-900">Estado comercial por empresa</h2><p class="text-[10px] text-slate-400">La comisión se calcula con el porcentaje vigente al confirmar cada venta.</p></header>
        <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-xs">
            <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400"><tr><th class="p-4">Empresa</th><th class="p-4 text-right">GMV</th><th class="p-4 text-center">Acuerdo</th><th class="p-4 text-right">Generada</th><th class="p-4 text-right">Cobrada</th><th class="p-4 text-right">Por cobrar</th><th class="p-4">Registrar cobro</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($summaries as $summary)
                <tr class="hover:bg-slate-50/70">
                    <td class="p-4"><a href="{{ route('admin.companies.show', $summary->organization) }}" class="font-black text-slate-900 hover:text-emerald-700">{{ $summary->organization->commercial_name ?: $summary->organization->legal_name }}</a><span class="mt-0.5 block text-[9px] text-slate-400">RUC {{ $summary->organization->ruc }}</span></td>
                    <td class="p-4 text-right font-bold">S/ {{ number_format($summary->gmv, 2) }}</td>
                    <td class="p-4 text-center"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700">{{ number_format((float) $summary->organization->commission_rate, 2) }}%</span></td>
                    <td class="p-4 text-right font-black">S/ {{ number_format($summary->generated, 2) }}</td>
                    <td class="p-4 text-right font-black text-cyan-700">S/ {{ number_format($summary->collected, 2) }}</td>
                    <td class="p-4 text-right font-black text-amber-700">S/ {{ number_format($summary->pending, 2) }}</td>
                    <td class="p-4"><form method="POST" action="{{ route('admin.commissions.store', $summary->organization) }}" class="flex items-center gap-1.5">@csrf<input type="date" name="period_start" required class="h-8 w-32 rounded-lg border-slate-200 text-[10px]"><input type="date" name="period_end" required class="h-8 w-32 rounded-lg border-slate-200 text-[10px]"><input name="reference" required placeholder="Factura / referencia" class="h-8 w-36 rounded-lg border-slate-200 text-[10px]"><button @disabled($summary->pending <= 0) class="h-8 rounded-lg bg-amber-500 px-3 text-[10px] font-black text-slate-950 disabled:cursor-not-allowed disabled:opacity-40">Registrar</button></form></td>
                </tr>
            @empty<tr><td colspan="7" class="p-12 text-center text-slate-400">No existen empresas para mostrar.</td></tr>@endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
