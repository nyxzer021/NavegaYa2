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
            <p class="text-[10px] text-slate-400">El porcentaje vigente se aplica solo a las ventas nuevas. Estas ventas no generan una deuda de la empresa.</p>
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
            <h2 class="font-black text-slate-900">{{ __('collections.admin_title') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('collections.admin_text') }}</p>
        </header>
        <form method="POST" action="{{ route('admin.commissions.payouts.store', $organizations->first() ?? 0) }}" class="grid gap-3 border-b border-slate-100 p-5 md:grid-cols-6" id="payout-form">
            @csrf
            <label class="text-xs font-bold text-slate-600 md:col-span-2">Empresa
                <select name="organization_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm" onchange="this.form.action='{{ url('/admin/comisiones-facturacion') }}/'+this.value+'/pagos'">
                    @foreach($organizations as $company)
                        <option value="{{ $company->id }}">{{ $company->commercial_name ?: $company->legal_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">{{ __('collections.paid_on') }}<input type="date" name="paid_on" required value="{{ now()->timezone('America/Lima')->toDateString() }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">{{ __('collections.amount') }}<input type="number" name="amount" required min="0.01" step="0.01" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">{{ __('collections.method') }}<input name="method" required maxlength="40" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">{{ __('collections.operation') }}<input name="operation_number" required maxlength="80" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
            <label class="text-xs font-bold text-slate-600 md:col-span-4">{{ __('collections.note') }}<input name="note" maxlength="500" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
            <button class="h-10 self-end rounded-lg bg-[#062c21] px-4 text-xs font-black text-white">{{ __('collections.record') }}</button>
        </form>
        <div class="divide-y divide-slate-100">
            @forelse($payouts as $payout)
                <article class="flex flex-wrap items-start justify-between gap-3 px-5 py-4 text-sm">
                    <div>
                        <p class="font-bold text-slate-900">{{ $payout->organization?->commercial_name ?: $payout->organization?->legal_name }} · {{ $payout->method }} · {{ $payout->operation_number }}</p>
                        <p class="text-xs text-slate-500">{{ $payout->paid_on?->format('d/m/Y') }} · S/ {{ number_format((float) $payout->amount, 2) }} · {{ $payout->status === 'voided' ? __('collections.state_voided') : __('collections.state_paid') }}</p>
                        @foreach($payout->items as $item)
                            <p class="text-xs text-slate-600">{{ $item->payment?->reservation?->code }} · S/ {{ number_format((float) $item->amount, 2) }}</p>
                        @endforeach
                        @if($payout->void_reason)<p class="text-xs text-rose-700">{{ $payout->void_reason }}</p>@endif
                    </div>
                    @if($payout->status === 'paid')
                        <form method="POST" action="{{ route('admin.commissions.payouts.void', $payout) }}" class="flex items-center gap-2">
                            @csrf
                            <input name="reason" required minlength="5" maxlength="500" placeholder="{{ __('collections.void_reason') }}" class="h-10 w-56 rounded-lg border-slate-200 text-sm">
                            <button class="h-10 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700">{{ __('collections.void') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">{{ __('collections.empty_payouts') }}</p>
            @endforelse
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-black text-slate-900">Comisiones anteriores al cambio</h2>
            <p class="text-[10px] text-slate-400">Estos registros descontaban el porcentaje a la empresa. El importe no se borra. Una anulada deja de sumar.</p>
        </header>
        @if($errors->has('payment_id') || $errors->has('reason') || $errors->has('reference'))
            <p class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-xs font-semibold text-rose-800">{{ $errors->first('payment_id') ?: $errors->first('reason') ?: $errors->first('reference') }}</p>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-xs">
                <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="p-4">Empresa</th>
                        <th class="p-4">Fecha</th>
                        <th class="p-4 text-right">Importe guardado</th>
                        <th class="p-4">Estado</th>
                        <th class="p-4">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($legacyPayments as $payment)
                    @php
                        $company = $payment->reservation?->departure?->transportRoute?->organization
                            ?? $payment->reservation?->airDeparture?->airRoute?->organization;
                        $open = in_array($payment->commission_status, ['pending', 'invoiced'], true);
                        $statusLabel = match ($payment->commission_status) {
                            'paid' => 'Cobrada',
                            'voided' => 'Anulada',
                            default => 'Pendiente',
                        };
                    @endphp
                    <tr>
                        <td class="p-4 font-black text-slate-900">{{ $company?->commercial_name ?: $company?->legal_name }}</td>
                        <td class="p-4 text-slate-500">{{ $payment->paid_at?->format('d/m/Y') }}</td>
                        <td class="p-4 text-right font-black">S/ {{ number_format((float) $payment->commission_amount, 2) }}</td>
                        <td class="p-4">{{ $statusLabel }}</td>
                        <td class="p-4">
                            @if($open && $company)
                                <form method="POST" action="{{ route('admin.commissions.store', $company) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                    <input type="hidden" name="period_start" value="{{ $payment->paid_at?->toDateString() }}">
                                    <input type="hidden" name="period_end" value="{{ $payment->paid_at?->toDateString() }}">
                                    <input name="reference" required maxlength="120" placeholder="Referencia" class="h-9 w-36 rounded-lg border-slate-200 text-xs">
                                    <button class="h-9 rounded-lg bg-[#062c21] px-3 text-[10px] font-black text-white">Registrar cobro</button>
                                </form>
                                <form method="POST" action="{{ route('admin.commissions.void', $company) }}" class="mt-2 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                    <input name="reason" required minlength="5" maxlength="500" placeholder="Motivo de la anulación" class="h-9 w-56 rounded-lg border-slate-200 text-xs">
                                    <button class="h-9 rounded-lg border border-slate-300 px-3 text-[10px] font-black text-slate-700">Anular</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-400">No hay comisiones anteriores en este periodo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
