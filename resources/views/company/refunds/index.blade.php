@extends('layouts.company')
@section('title', __('company_portal.menu.refunds'))
@section('page-title', __('company_portal.menu.refunds'))
@section('content')
<style>
    .ny-ref { display: grid; gap: 16px; }
    .ny-ref-lead, .ny-ref-rule { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-ref-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .ny-ref-kpi, .ny-ref-card, .ny-ref-row, .ny-ref-empty { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-ref-kpi { display: flex; align-items: center; gap: 12px; padding: 16px; }
    .ny-ref-kpi b { display: block; font-size: 28px; line-height: 1; text-align: right; }
    .ny-ref-kpi span, .ny-ref label { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-ref-pill { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-ref-pill svg { width: 16px; height: 16px; }
    .ny-ref-card { padding: 16px; }
    .ny-ref h2 { margin: 0 0 12px; font-size: 16px; }
    .ny-ref-form, .ny-ref-filters { display: grid; grid-template-columns: 1fr 160px 1.4fr auto; gap: 12px; align-items: end; }
    .ny-ref-filters { grid-template-columns: 220px auto; }
    .ny-ref label { display: grid; gap: 4px; }
    .ny-ref input, .ny-ref select { height: 36px; width: 100%; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 0 12px; color: var(--ny-text); font-size: 14px; }
    .ny-ref-btn, .ny-ref-quiet { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; }
    .ny-ref-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-ref-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-ref-hint, .ny-ref-error { margin: 8px 0 0; font-size: 12px; }
    .ny-ref-hint { color: var(--ny-text-muted); }
    .ny-ref-error { color: var(--ny-tone-coral); font-weight: 700; }
    .ny-ref-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: start; padding: 16px; }
    .ny-ref-row p { margin: 0; font-size: 14px; }
    .ny-ref-row small, .ny-ref-calc { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; }
    .ny-ref-money { text-align: right; font-size: 14px; font-weight: 700; white-space: nowrap; }
    .ny-ref-money span { display: block; color: var(--ny-text-muted); font-size: 12px; font-weight: 600; }
    .ny-ref-badge, .ny-ref-channel { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; background: var(--c); color: var(--ny-surface); padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .ny-ref-channel { margin-right: 4px; }
    .ny-ref-actions { display: grid; gap: 8px; margin-top: 8px; }
    .ny-ref-actions form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .ny-ref-empty { padding: 24px; }
    .ny-ref-empty h2 { margin: 8px 0 4px; }
    .ny-ref-empty p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    @media (max-width: 800px) {
        .ny-ref-kpis, .ny-ref-form, .ny-ref-filters, .ny-ref-row { grid-template-columns: 1fr; }
        .ny-ref-btn, .ny-ref-quiet, .ny-ref input, .ny-ref select { min-height: 44px; height: 44px; }
        .ny-ref-money { text-align: left; }
    }
</style>
<div class="ny-ref">
    <p class="ny-ref-lead">{{ __('refunds.lead') }}</p>
    <p class="ny-ref-rule">{{ $cancelRule }} {{ $regretRule }}</p>
    @if($errors->any())
        <p class="ny-ref-error">{{ $errors->first() }}</p>
    @endif
    <section class="ny-ref-kpis">
        <article class="ny-ref-kpi"><span class="ny-ref-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="refunds" /></span><div><span>{{ __('refunds.requested_count') }}</span><b data-count="requested">{{ $requested }}</b></div></article>
        <article class="ny-ref-kpi"><span class="ny-ref-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="wallet" /></span><div><span>{{ __('refunds.to_pay') }}</span><b data-count="approved">{{ $money->soles($approvedCents) }}</b></div></article>
        <article class="ny-ref-kpi"><span class="ny-ref-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="check" /></span><div><span>{{ __('refunds.paid') }}</span><b data-count="paid">{{ $money->soles($paidCents) }}</b></div></article>
        <article class="ny-ref-kpi"><span class="ny-ref-pill" style="--c: var(--ny-tone-coral)"><x-ui.icon name="sales" /></span><div><span>{{ __('refunds.rejected') }}</span><b data-count="rejected">{{ $rejected }}</b></div></article>
    </section>
    <form class="ny-ref-card ny-ref-filters" method="GET" action="{{ route('company.refunds.index') }}">
        <label>{{ __('refunds.filter') }}
            <select name="estado">
                <option value="">{{ __('refunds.all') }}</option>
                @foreach(['requested', 'approved', 'rejected', 'paid'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ __('refunds.status_'.$option) }}</option>
                @endforeach
            </select>
        </label>
        <button class="ny-ref-btn" type="submit">{{ __('refunds.apply') }}</button>
    </form>
    <section class="ny-ref-card">
        <h2>{{ __('refunds.register') }}</h2>
        <form class="ny-ref-form" method="POST" action="{{ route('company.refunds.store') }}">
            @csrf
            <label>{{ __('refunds.code') }}<input name="code" value="{{ old('code') }}" required maxlength="40"></label>
            <label>{{ __('refunds.amount') }}<input name="amount" type="number" min="0" step="0.01" value="{{ old('amount') }}" inputmode="decimal"></label>
            <label>{{ __('refunds.reason') }}<input name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="500"></label>
            <button class="ny-ref-btn" type="submit">{{ __('refunds.submit') }}</button>
        </form>
        <p class="ny-ref-hint">{{ __('refunds.amount_hint') }}</p>
    </section>
    @forelse($refunds as $refund)
        @php
            $channel = $refund->reservation?->sales_channel;
            $channelTone = match ($channel) { 'web' => 'var(--ny-tone-river)', 'agency' => 'var(--ny-tone-violet)', default => 'var(--ny-tone-leaf)' };
            $webWaiting = $refund->status === 'approved' && $channel === 'web';
            $tone = match (true) {
                $refund->status === 'paid' => 'var(--ny-tone-leaf)',
                $refund->status === 'rejected' => 'var(--ny-tone-coral)',
                $webWaiting => 'var(--ny-tone-violet)',
                default => 'var(--ny-tone-sun)',
            };
            $label = $webWaiting ? __('refunds.status_approved_web') : __('refunds.status_'.$refund->status);
            $needsAmount = $refund->status === 'requested' && $refund->cause === 'passenger' && $refund->policy_percent === null && (float) $refund->amount === 0.0;
        @endphp
        <article class="ny-ref-row">
            <div>
                <p><span class="ny-ref-channel" style="--c: {{ $channelTone }}">{{ __('refunds.channel_'.($channel ?: 'counter')) }}</span>{{ $refund->reservation?->code }} · {{ $refund->reservation?->contact_name }}</p>
                <small>{{ $refund->explanation }}</small>
                @if($refund->reason)<small>{{ $refund->reason }}</small>@endif
                @if($refund->rejection_reason)<small>{{ $refund->rejection_reason }}</small>@endif
                <div class="ny-ref-actions">
                    @if($refund->status === 'requested')
                        <form method="POST" action="{{ route('company.refunds.approve', $refund) }}">
                            @csrf
                            @if($needsAmount)
                                <input name="amount" type="number" min="0.01" step="0.01" required inputmode="decimal" placeholder="{{ __('refunds.amount') }}" aria-label="{{ __('refunds.amount') }}">
                            @endif
                            <button class="ny-ref-btn" type="submit">{{ __('refunds.approve') }}</button>
                        </form>
                        <form method="POST" action="{{ route('company.refunds.reject', $refund) }}">
                            @csrf
                            <input name="reason" required minlength="5" maxlength="500" placeholder="{{ __('refunds.reject_reason') }}" aria-label="{{ __('refunds.reject_reason') }}">
                            <button class="ny-ref-quiet" type="submit">{{ __('refunds.reject') }}</button>
                        </form>
                    @elseif($refund->status === 'approved' && $channel !== 'web')
                        @if($refund->payment?->method === 'cash')
                            <form method="POST" action="{{ route('company.refunds.pay', $refund) }}">
                                @csrf
                                <button class="ny-ref-btn" type="submit">{{ __('refunds.pay_cash') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('company.refunds.pay', $refund) }}">
                                @csrf
                                <input name="operation_number" required maxlength="80" placeholder="{{ __('refunds.operation') }}" aria-label="{{ __('refunds.operation') }}">
                                <button class="ny-ref-btn" type="submit">{{ __('refunds.pay_operation') }}</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
            <div class="ny-ref-money">
                {{ $money->soles($money->cents((string) $refund->amount)) }}
                <span>{{ __('refunds.fare') }} {{ $money->soles($money->cents((string) $refund->fare_amount)) }}</span>
                <span>{{ __('refunds.fee') }} {{ $money->soles($money->cents((string) $refund->fee_amount)) }}</span>
                <span class="ny-ref-badge" style="--c: {{ $tone }}">{{ $label }}</span>
            </div>
        </article>
    @empty
        <section class="ny-ref-empty">
            <span class="ny-ref-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="refunds" /></span>
            <h2>{{ __('refunds.empty_title') }}</h2>
            <p>{{ __('refunds.empty') }}</p>
        </section>
    @endforelse
</div>
@endsection
