@if($errors->has('payment_request') || $errors->has('rejection_reason'))
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-800" role="alert">{{ $errors->first('payment_request') ?: $errors->first('rejection_reason') }}</div>
@endif
<section class="rounded-2xl border border-violet-200 bg-white p-5 shadow-sm" aria-labelledby="transfers-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="transfers-title" class="text-lg font-black text-slate-950">Comprobantes de pago por revisar</h2>
            <p class="text-sm text-slate-600">Pagos por transferencia, Yape o Plin que subieron los negocios. Al aprobar, el plan se activa por los meses pagados.</p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-black {{ $transferRequests->isEmpty() ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">{{ $transferRequests->count() }} por revisar</span>
    </div>

    @forelse($transferRequests as $item)
        <article class="mt-4 rounded-2xl border border-slate-200 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="text-sm text-slate-700">
                    <strong class="block text-base text-slate-950">{{ $item->organization?->commercial_name ?: $item->organization?->legal_name }}</strong>
                    Plan {{ $item->plan?->name }} · {{ $item->months }} {{ $item->months === 1 ? 'mes' : 'meses' }} · <strong>S/ {{ number_format((float) $item->amount, 2) }}</strong><br>
                    {{ $item->methodLabel() }} · operación <strong>{{ $item->operation_number }}</strong> · pagado el {{ $item->paid_on?->format('d/m/Y') }}
                    <span class="block text-xs text-slate-500">Enviado por {{ $item->requester?->name ?? 'el negocio' }} el {{ $item->created_at->timezone(\App\Support\DepartureSchedule::ZONE)->format('d/m/Y H:i') }}</span>
                </div>
                <a href="{{ route('admin.advertising-subscriptions.vouchers.show', $item) }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-violet-300 px-4 text-sm font-black text-violet-800 hover:bg-violet-50"><x-ui.icon name="receipts" class="h-4 w-4" />Ver comprobante</a>
            </div>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <form method="POST" action="{{ route('admin.advertising-subscriptions.vouchers.approve', $item) }}">
                    @csrf @method('PATCH')
                    <button class="min-h-10 rounded-xl bg-emerald-700 px-4 text-sm font-black text-white hover:bg-emerald-800">Aprobar y activar</button>
                </form>
                <form method="POST" action="{{ route('admin.advertising-subscriptions.vouchers.reject', $item) }}" class="flex flex-1 flex-wrap items-end gap-2">
                    @csrf @method('PATCH')
                    <label class="min-w-[220px] flex-1 text-sm font-bold text-slate-700">Motivo del rechazo
                        <input name="rejection_reason" required minlength="5" maxlength="500" placeholder="Ej.: el monto no coincide con el plan" class="mt-1 min-h-10 w-full rounded-xl border-slate-200 text-sm">
                    </label>
                    <button class="min-h-10 rounded-xl border border-rose-300 px-4 text-sm font-black text-rose-700 hover:bg-rose-50">Rechazar</button>
                </form>
            </div>
        </article>
    @empty
        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">No hay comprobantes pendientes.</p>
    @endforelse

    <details class="mt-4 rounded-xl border border-slate-200">
        <summary class="cursor-pointer p-3 text-sm font-black text-slate-800">Datos de pago que ven los negocios</summary>
        <form method="POST" action="{{ route('admin.advertising-subscriptions.instructions.update') }}" class="border-t p-3">
            @csrf @method('PATCH')
            <label class="block text-sm font-bold text-slate-700">Banco, cuenta, CCI y número de Yape o Plin
                <textarea name="subscription_payment_instructions" rows="4" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Banco ... · Cuenta ... · CCI ... · Yape ...">{{ $paymentInstructions }}</textarea>
            </label>
            <button class="mt-2 min-h-10 rounded-xl bg-slate-900 px-4 text-sm font-black text-white">Guardar datos de pago</button>
        </form>
    </details>
</section>
