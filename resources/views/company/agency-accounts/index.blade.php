@extends('layouts.company')
@section('title', __('company_portal.menu.agency_commissions'))
@section('page-title', __('company_portal.menu.agency_commissions'))
@section('content')
@php
    $totals = $statement['totals'];
    $balanceTone = $totals['balance_cents'] > 0 ? 'var(--ny-tone-sun)' : 'var(--ny-tone-leaf)';
@endphp
<style>
    .ny-age { display: grid; gap: 16px; }
    .ny-age-lead { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-age-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .ny-age-kpi, .ny-age-card, .ny-age-row, .ny-age-empty { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-age-kpi { display: flex; align-items: center; gap: 12px; padding: 16px; }
    .ny-age-kpi b { display: block; font-size: 28px; line-height: 1; text-align: right; }
    .ny-age-kpi span, .ny-age label { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-age-pill { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-age-pill svg { width: 16px; height: 16px; }
    .ny-age-card { padding: 16px; }
    .ny-age h2 { margin: 0 0 12px; font-size: 16px; }
    .ny-age-filters { display: grid; grid-template-columns: minmax(0, 280px) auto auto; gap: 12px; align-items: end; }
    .ny-age label { display: grid; gap: 4px; }
    .ny-age input:not([type=checkbox]):not([type=radio]):not([type=file]), .ny-age select { height: 36px; min-height: 36px; width: 100%; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 0 12px; color: var(--ny-text); font-size: 14px; }
    .ny-age-btn, .ny-age-quiet { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-age-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-age-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-age-error { margin: 0; color: var(--ny-tone-coral); font-size: 14px; font-weight: 700; }
    .ny-age-row { display: grid; gap: 12px; padding: 16px; }
    .ny-age-row.is-overdue { border-color: var(--ny-tone-coral); }
    .ny-age-top { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: start; }
    .ny-age-top p { margin: 0; font-size: 14px; }
    .ny-age-top small, .ny-age-meta { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; }
    .ny-age-money { text-align: right; font-size: 14px; font-weight: 700; white-space: nowrap; }
    .ny-age-money span { display: block; color: var(--ny-text-muted); font-size: 12px; font-weight: 600; }
    .ny-age-badge { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; border-radius: 999px; background: var(--c); color: var(--ny-surface); padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .ny-age-pay, .ny-age-void { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; }
    .ny-age-pay label, .ny-age-void label { flex: 1 1 140px; }
    .ny-age-line { display: flex; justify-content: space-between; gap: 12px; margin-top: 8px; font-size: 14px; }
    .ny-age-line.is-void { color: var(--ny-text-muted); text-decoration: line-through; }
    .ny-age-empty { padding: 24px; }
    .ny-age-empty h2 { margin: 8px 0 4px; }
    .ny-age-empty p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    @media (max-width: 800px) {
        .ny-age-kpis, .ny-age-filters, .ny-age-top { grid-template-columns: 1fr; }
        .ny-age-btn, .ny-age-quiet, .ny-age input:not([type=checkbox]):not([type=radio]):not([type=file]), .ny-age select { min-height: 44px; height: 44px; }
        .ny-age-money { text-align: left; }
    }
</style>
<div class="ny-age">
    <p class="ny-age-lead">{{ __('agency_accounts.lead') }}</p>
    @if($errors->any())
        <p class="ny-age-error">{{ $errors->first() }}</p>
    @endif
    <section class="ny-age-kpis">
        <article class="ny-age-kpi"><span class="ny-age-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="agencies" /></span><div><span>{{ __('agency_accounts.amount') }}</span><b data-count="amount">{{ $money->soles($totals['amount_cents']) }}</b></div></article>
        <article class="ny-age-kpi"><span class="ny-age-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="check" /></span><div><span>{{ __('agency_accounts.received') }}</span><b data-count="paid">{{ $money->soles($totals['paid_cents']) }}</b></div></article>
        <article class="ny-age-kpi"><span class="ny-age-pill" style="--c: {{ $balanceTone }}"><x-ui.icon name="wallet" /></span><div><span>{{ __('agency_accounts.balance') }}</span><b data-count="balance">{{ $money->soles($totals['balance_cents']) }}</b></div></article>
        <article class="ny-age-kpi"><span class="ny-age-pill" style="--c: var(--ny-tone-coral)"><x-ui.icon name="departures" /></span><div><span>{{ __('agency_accounts.overdue') }}</span><b data-count="overdue">{{ $money->soles($totals['overdue_cents']) }}</b></div></article>
    </section>
    <form class="ny-age-card ny-age-filters" method="GET" action="{{ route('company.agency-commissions.index') }}">
        <label>{{ __('agency_accounts.agency') }}
            <select name="agencia">
                <option value="">{{ __('agency_accounts.all_agencies') }}</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected($agencyId === $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="ny-age-btn" type="submit">{{ __('agency_accounts.apply') }}</button>
        <a class="ny-age-quiet" href="{{ route('company.agency-commissions.export') }}">{{ __('collections.export') }}</a>
    </form>
    @forelse($statement['agencies'] as $group)
        <section class="ny-age-card">
            <h2><span class="ny-age-badge" style="--c: var(--ny-tone-violet)">{{ $group['agency']->name }}</span></h2>
            @foreach($group['lots'] as $row)
                @php
                    $lot = $row['lot'];
                    $quote = $row['quote'];
                    $tone = match ($quote['status']) { 'paid' => 'var(--ny-tone-leaf)', 'partial' => 'var(--ny-tone-sun)', default => 'var(--ny-tone-sun)' };
                    $origin = $lot->departure?->transportRoute?->originPort?->name;
                    $destination = $lot->departure?->transportRoute?->destinationPort?->name;
                @endphp
                <article class="ny-age-row {{ $quote['overdue'] ? 'is-overdue' : '' }}">
                    <div class="ny-age-top">
                        <div>
                            <p>{{ $origin ?: __('audit.unknown_place') }} → {{ $destination ?: __('audit.unknown_place') }}</p>
                            <small>{{ \App\Support\DepartureSchedule::show($lot->departure?->departure_at) }}</small>
                            <small>{{ __('agency_accounts.assigned') }} {{ $quote['assigned'] }} · {{ __('agency_accounts.sold') }} {{ $quote['sold'] }} · {{ __('agency_accounts.released') }} {{ $quote['released'] }} · {{ __('agency_accounts.unit') }} {{ $money->soles($money->cents((string) $lot->unit_price)) }}</small>
                            @if($quote['legacy'])<small>{{ __('agency_accounts.legacy') }}</small>@endif
                        </div>
                        <div class="ny-age-money">
                            {{ $money->soles($quote['amount_cents']) }}
                            <span>{{ __('agency_accounts.lot_paid') }} {{ $money->soles($quote['paid_cents']) }}</span>
                            <span>{{ __('agency_accounts.lot_balance') }} {{ $money->soles($quote['balance_cents']) }}</span>
                            <span class="ny-age-badge" style="--c: {{ $tone }}">{{ __('agency_accounts.status_'.$quote['status']) }}</span>
                            @if($quote['overdue'])<span class="ny-age-badge" style="--c: var(--ny-tone-coral)">{{ __('agency_accounts.overdue_lot') }}</span>@endif
                        </div>
                    </div>
                    @foreach($lot->payments as $payment)
                        <div class="ny-age-line {{ $payment->status === 'voided' ? 'is-void' : '' }}">
                            <span>{{ $payment->paid_on?->format('d/m/Y') }} · {{ $payment->method }} · {{ $payment->operation_number }}@if($payment->note) · {{ $payment->note }}@endif @if($payment->status === 'voided')· {{ $payment->void_reason }}@endif</span>
                            <strong>{{ $money->soles($money->cents((string) $payment->amount)) }}</strong>
                        </div>
                        @if($payment->status === 'recorded')
                            <form class="ny-age-void" method="POST" action="{{ route('company.agency-commissions.payments.void', $payment) }}">
                                @csrf
                                <label>{{ __('agency_accounts.void_reason') }}<input name="reason" required minlength="5" maxlength="500"></label>
                                <button class="ny-age-quiet" type="submit">{{ __('agency_accounts.void') }}</button>
                            </form>
                        @elseif($payment->status === 'voided')
                            <span class="ny-age-badge" style="--c: var(--ny-tone-coral)">{{ __('agency_accounts.voided') }}</span>
                        @endif
                    @endforeach
                    @if($quote['balance_cents'] > 0)
                        <form class="ny-age-pay" method="POST" action="{{ route('company.agency-commissions.payments.store', $lot) }}">
                            @csrf
                            <label>{{ __('agency_accounts.pay_amount') }}<input name="amount" type="number" min="0.01" step="0.01" required inputmode="decimal"></label>
                            <label>{{ __('agency_accounts.date') }}<input name="paid_on" type="date" required value="{{ $today }}"></label>
                            <label>{{ __('agency_accounts.method') }}
                                <select name="method" required>
                                    @foreach(__('agency_accounts.methods') as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>{{ __('agency_accounts.operation') }}<input name="operation_number" required maxlength="80"></label>
                            <label>{{ __('agency_accounts.note') }}<input name="note" maxlength="255"></label>
                            <button class="ny-age-btn" type="submit">{{ __('agency_accounts.register') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </section>
    @empty
        <section class="ny-age-empty">
            <span class="ny-age-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="agencies" /></span>
            <h2>{{ __('agency_accounts.empty_title') }}</h2>
            <p>{{ __('agency_accounts.empty') }}</p>
        </section>
    @endforelse
</div>
@endsection
