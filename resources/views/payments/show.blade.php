<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="turbo-visit-control" content="reload">
    <title>{{ __('payments.title') }} · NavegaYA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .ny-pay-test { margin: 0 0 16px; border: 1px solid var(--ny-tone-sun); border-radius: 12px; background: var(--ny-warning-bg); color: var(--ny-warning); padding: 12px 16px; font-size: 14px; font-weight: 700; }
        .ny-pay-button { min-height: 44px; }
        @media (min-width: 800px) { .ny-pay-button { min-height: 36px; height: 36px; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
<header class="bg-[#062c21] px-5 py-4 text-white shadow-md"><div class="mx-auto flex max-w-4xl items-center justify-between"><a href="{{ route('home') }}" class="text-lg font-black"> Navega<span class="text-amber-400">YA</span></a><span class="text-xs font-semibold text-emerald-100">{{ __('payments.secure') }}</span></div></header>
<main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
<section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-xl shadow-slate-200/60">
    <div class="border-b border-slate-100 bg-gradient-to-r from-[#062c21] to-emerald-800 px-6 py-6 text-white sm:px-8"><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-300">{{ $reservation->code }}</p><h1 class="mt-1 text-2xl font-black">{{ __('payments.complete') }}</h1></div>
    <div class="p-6 sm:p-8">
    @if ($reservation->status === 'expired')
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">{{ __('payments.expired') }}</div>
    @elseif ($reservation->status === 'confirmed')
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">{{ __('payments.already_paid') }}</div>
        <a href="{{ route('tickets.show', $reservation->code) }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#062c21] px-5 py-3 text-sm font-bold text-white">{{ __('payments.see_tickets') }}</a>
    @else
        @if (session('payment_error'))
            <p class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ session('payment_error') }}</p>
        @endif
        <div class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:grid-cols-3">
            <div><span class="block text-xs font-bold uppercase text-slate-400">{{ __('payments.total') }}</span><strong class="text-xl text-slate-950">S/ {{ number_format($quote['charged_amount'], 2) }}</strong>@if($quote['service_fee_amount'] > 0)<span class="mt-1 block text-xs text-slate-500">{{ __('payments.fare_plus_fee', ['fare' => number_format($quote['base_amount'], 2), 'fee' => number_format($quote['service_fee_amount'], 2)]) }}</span>@endif</div>
            <div><span class="block text-xs font-bold uppercase text-slate-400">{{ __('payments.seats') }}</span><strong class="text-sm text-slate-950">{{ __('payments.seats_count', ['count' => $reservation->seats->count()]) }}</strong></div>
            <div><span class="block text-xs font-bold uppercase text-slate-400">{{ __('payments.held_until') }}</span><strong class="text-sm text-slate-950">{{ __('payments.until_time', ['time' => $reservation->expires_at?->timezone('America/Lima')->format('H:i')]) }}</strong></div>
        </div>
        @if ($sandboxEnabled)
            <form method="POST" action="{{ route('payments.sandbox-confirm', $reservation->code) }}" class="mt-6">
                @csrf
                <h2 class="text-sm font-extrabold text-slate-900">{{ __('payments.test_methods') }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach(['yape' => 'Yape', 'plin' => 'Plin', 'card' => 'Tarjeta'] as $value => $label)
                    <label><input class="peer sr-only" type="radio" name="method" value="{{ $value }}" {{ $loop->first ? 'checked' : '' }}><span class="block cursor-pointer rounded-xl border-2 border-slate-200 p-4 text-center text-xs font-bold peer-checked:border-emerald-700 peer-checked:bg-emerald-50 peer-checked:text-emerald-900">{{ $label }}</span></label>
                @endforeach
                </div>
                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">{{ __('payments.sandbox_notice') }}</p>
                <button class="mt-5 h-11 w-full rounded-xl bg-amber-500 text-sm font-black text-slate-950 hover:bg-amber-600">{{ __('payments.sandbox_confirm') }}</button>
            </form>
        @elseif ($culqiEnabled || $mercadoPagoEnabled)
            <div class="mt-6">
                @if ($culqiTestMode || $mercadoPagoTestMode)
                    <p class="ny-pay-test">{{ __('payments.test_mode') }}</p>
                @endif
                @if ($culqiEnabled)
                <h2 class="text-sm font-extrabold text-slate-900">{{ __('payments.pay_with_culqi') }}</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ __('payments.culqi_help') }}</p>
                <div id="culqiError" class="mt-4 hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700"></div>
                <button id="culqiPayButton" type="button" class="ny-pay-button mt-5 flex h-11 w-full items-center justify-center rounded-xl bg-amber-500 text-sm font-black text-slate-950 shadow-sm hover:bg-amber-600 disabled:cursor-wait disabled:opacity-60">{{ __('payments.pay_button', ['amount' => number_format($quote['charged_amount'], 2)]) }}</button>
                <div class="my-4 flex items-center gap-3 text-xs font-bold uppercase text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>{{ __('payments.or_cash') }}<span class="h-px flex-1 bg-slate-200"></span></div>
                <button id="pagoEfectivoButton" type="button" class="h-11 w-full rounded-xl border border-emerald-700 bg-white text-sm font-bold text-emerald-800 hover:bg-emerald-50 disabled:cursor-wait disabled:opacity-60">{{ __('payments.cash_button') }}</button>
                <div id="pagoEfectivoResult" class="mt-3 hidden rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center"><span class="block text-xs font-bold uppercase text-emerald-700">{{ __('payments.cash_code') }}</span><strong id="paymentCode" class="mt-1 block text-2xl tracking-wider text-slate-950"></strong><p class="mt-1 text-xs text-slate-500">{{ __('payments.cash_help') }}</p></div>
                @endif
                @if ($mercadoPagoEnabled)
                    @if ($culqiEnabled)
                        <div class="my-4 flex items-center gap-3 text-xs font-bold uppercase text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>{{ __('payments.or_provider') }}<span class="h-px flex-1 bg-slate-200"></span></div>
                    @endif
                    <form method="POST" action="{{ route('payments.mercadopago.start', $reservation->code) }}">
                        @csrf
                        <h2 class="text-sm font-extrabold text-slate-900">{{ __('payments.pay_with_mp') }}</h2>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ __('payments.mp_help') }}</p>
                        <button id="mercadopagoPayButton" type="submit" class="ny-pay-button mt-5 flex h-11 w-full items-center justify-center rounded-xl bg-amber-500 text-sm font-black text-slate-950 shadow-sm hover:bg-amber-600">{{ __('payments.mp_button', ['amount' => number_format($quote['charged_amount'], 2)]) }}</button>
                    </form>
                @endif
            </div>
        @else
            <p class="mt-6 rounded-xl bg-sky-50 p-4 text-sm font-semibold text-sky-800">{{ __('payments.offline') }}</p>
        @endif
    @endif
    </div>
</section>
</main>
@if ($reservation->status === 'pending_payment' && $culqiEnabled && ! $sandboxEnabled)
<script src="https://js.culqi.com/checkout-js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('culqiPayButton');
    const errorBox = document.getElementById('culqiError');
    const cashButton = document.getElementById('pagoEfectivoButton');
    const cashResult = document.getElementById('pagoEfectivoResult');
    const showError = message => {
        errorBox.textContent = message || @json(__('payments.browser_error'));
        errorBox.classList.remove('hidden');
        button.disabled = false;
    };
    const Culqi = new CulqiCheckout(@json(config('services.culqi.public_key')), {
        settings: { title: 'NavegaYA', currency: 'PEN', amount: {{ (int) round($quote['charged_amount'] * 100) }} },
        client: { email: @json($reservation->contact_email) },
        options: {
            lang: 'es', installments: true, modal: true,
            paymentMethods: { tarjeta: true, yape: true, billetera: false, bancaMovil: false, agente: false, cuotealo: false },
            paymentMethodsSort: ['tarjeta', 'yape'],
        },
        appearance: { theme: 'default', menuType: 'sidebar', defaultStyle: { bannerColor: '#062c21', buttonBackground: '#f59e0b', menuColor: '#062c21', linksColor: '#047857', buttonTextColor: '#0f172a' } },
    });
    Culqi.culqi = async () => {
        if (! Culqi.token) return showError(Culqi.error?.user_message);
        Culqi.close();
        button.disabled = true;
        try {
            const response = await fetch(@json(route('payments.culqi.charge', $reservation->code)), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ source_id: Culqi.token.id }),
            });
            const result = await response.json().catch(() => ({}));
            if (! response.ok) throw new Error(result.message || @json(__('payments.rejected')));
            window.location.assign(result.redirect);
        } catch (error) { showError(error.message); }
    };
    button.addEventListener('click', () => { errorBox.classList.add('hidden'); Culqi.open(); });
    cashButton.addEventListener('click', async () => {
        errorBox.classList.add('hidden');
        cashButton.disabled = true;
        try {
            const response = await fetch(@json(route('payments.culqi.order', $reservation->code)), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: '{}',
            });
            const result = await response.json().catch(() => ({}));
            if (! response.ok) throw new Error(result.message || @json(__('payments.cash_failed')));
            document.getElementById('paymentCode').textContent = result.payment_code || result.order_id;
            cashResult.classList.remove('hidden');
        } catch (error) {
            showError(error.message);
            cashButton.disabled = false;
        }
    });
});
</script>
@endif
</body>
</html>

