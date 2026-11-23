@extends('layouts.admin')

@section('title', __('admin_portal.menu.payment_connections').' · NavegaYA')

@section('content')
<style>
    .ny-main .ny-pay { display: grid; gap: 16px; max-width: 880px; color: var(--ny-text); font-size: 14px; }
    .ny-main .ny-pay h1 { margin: 0; font-size: 20px; }
    .ny-main .ny-pay p, .ny-main .ny-pay li, .ny-main .ny-pay label, .ny-main .ny-pay span, .ny-main .ny-pay button, .ny-main .ny-pay input { font-size: 14px !important; }
    .ny-main .ny-pay .ny-pay-k { font-size: 12px !important; font-weight: 700; color: var(--ny-text-muted); }
    .ny-main .ny-pay-card { display: grid; gap: 12px; border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); padding: 16px; box-shadow: var(--ny-shadow-sm); }
    .ny-main .ny-pay-head, .ny-main .ny-pay-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-width: 0; }
    .ny-main .ny-pay-row span:last-child { text-align: right; }
    .ny-main .ny-pay-badge { border-radius: 999px; padding: 2px 8px; font-size: 12px !important; font-weight: 700; color: var(--ny-surface); background: var(--c); }
    .ny-main .ny-pay-url { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; }
    .ny-main .ny-pay input { width: 100%; min-width: 0; min-height: 40px !important; }
    .ny-main .ny-pay button { min-height: 44px; border: 0; border-radius: 8px; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); padding: 0 12px; font-weight: 700; }
    .ny-main .ny-pay-note { margin: 0; border-radius: 8px; padding: 12px; background: var(--ny-surface-muted); }
    .ny-main .ny-pay-ok { background: var(--ny-success-bg); color: var(--ny-success); }
    .ny-main .ny-pay-bad { background: var(--ny-danger-bg); color: var(--ny-danger); }
    @media (min-width: 800px) {
        .ny-main .ny-pay button { min-height: 36px; }
    }
</style>
<div class="ny-pay">
    <div>
        <h1>{{ __('admin_portal.menu.payment_connections') }}</h1>
        <p>{{ __('connections.lead') }}</p>
    </div>
    @if (session('probe_ok'))
        <p class="ny-pay-note ny-pay-ok">{{ session('probe_ok') }}</p>
    @endif
    @if (session('probe_error'))
        <p class="ny-pay-note ny-pay-bad">{{ session('probe_error') }}</p>
    @endif

    <section class="ny-pay-card">
        <div class="ny-pay-head">
            <strong>{{ __('connections.culqi') }}</strong>
            <span class="ny-pay-badge" style="--c: {{ $culqi['enabled'] ? 'var(--ny-success)' : 'var(--ny-text-muted)' }}">{{ $culqi['enabled'] ? __('connections.on') : __('connections.off') }}</span>
        </div>
        <div class="ny-pay-row"><span class="ny-pay-k">{{ __('connections.environment') }}</span><span>{{ $culqi['test'] ? __('connections.test') : __('connections.production') }}</span></div>
        @foreach ([
            ['connections.public', $culqi['public_present'], $culqi['public_tail']],
            ['connections.secret', $culqi['secret_present'], $culqi['secret_tail']],
            ['connections.webhook_secret', $culqi['webhook_present'], $culqi['webhook_tail']],
        ] as [$label, $present, $tail])
            <div class="ny-pay-row">
                <span class="ny-pay-k">{{ __($label) }}</span>
                <span>
                    @if ($present && $tail)
                        {{ __('connections.masked', ['tail' => $tail]) }}
                    @elseif ($present)
                        {{ __('connections.present') }}
                    @else
                        {{ __('connections.absent') }}
                    @endif
                </span>
            </div>
        @endforeach
        <label class="ny-pay-k" for="culqi-webhook">{{ __('connections.webhook_url') }}</label>
        <div class="ny-pay-url">
            <input id="culqi-webhook" type="text" readonly value="{{ $webhookUrl }}">
            <button type="button" data-copy="culqi-webhook">{{ __('connections.copy') }}</button>
        </div>
        <div class="ny-pay-row">
            <span class="ny-pay-k">{{ __('connections.last') }}</span>
            <span>{{ $lastWebhook ?: __('connections.never') }}</span>
        </div>
        <form method="POST" action="{{ route('admin.payment-connections.probe') }}">
            @csrf
            <input type="hidden" name="provider" value="culqi">
            <button type="submit">{{ __('connections.probe') }}</button>
        </form>
    </section>

    <section class="ny-pay-card">
        <div class="ny-pay-head">
            <strong>{{ __('connections.mercadopago') }}</strong>
            <span class="ny-pay-badge" style="--c: var(--ny-text-muted)">{{ __('connections.off') }}</span>
        </div>
        <p class="ny-pay-note">{{ __('connections.mp_detail') }}</p>
    </section>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const input = document.getElementById(button.dataset.copy);
        input.select();
        try { await navigator.clipboard.writeText(input.value); } catch (error) {}
        button.textContent = @json(__('connections.copied'));
    });
});
</script>
@endsection
