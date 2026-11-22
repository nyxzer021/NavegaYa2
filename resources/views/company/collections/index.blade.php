@extends('layouts.company')
@section('title', __('company_portal.menu.collections'))
@section('page-title', __('company_portal.menu.collections'))
@section('content')
@php
    $pending = $statement['pending_cents'];
    $pendingLabel = $pending < 0 ? __('collections.credit') : ($pending === 0 ? __('collections.settled') : __('collections.pending'));
    $pendingTone = $pending < 0 ? 'var(--ny-tone-coral)' : ($pending === 0 ? 'var(--ny-tone-leaf)' : 'var(--ny-tone-sun)');
@endphp
<style>
    .ny-col { display: grid; gap: 16px; }
    .ny-col-lead { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-col-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .ny-col-kpi, .ny-col-card, .ny-col-row { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-col-kpi { display: flex; align-items: center; gap: 12px; padding: 16px; }
    .ny-col-kpi b { display: block; font-size: 28px; line-height: 1; text-align: right; }
    .ny-col-kpi span, .ny-col label { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-col-kpi small { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; font-weight: 600; text-align: right; }
    .ny-col-pill { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-col-pill svg { width: 16px; height: 16px; }
    .ny-col-card { padding: 16px; }
    .ny-col-filters { display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 12px; align-items: end; }
    .ny-col label { display: grid; gap: 4px; }
    .ny-col input, .ny-col select { height: 36px; width: 100%; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 0 12px; color: var(--ny-text); font-size: 14px; }
    .ny-col-btn, .ny-col-quiet { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-col-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-col-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-col h2 { margin: 0 0 12px; font-size: 16px; }
    .ny-col-eq { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-col-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: start; margin-top: 8px; }
    .ny-col-row p { margin: 0; font-size: 14px; }
    .ny-col-row small { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; }
    .ny-col-money { text-align: right; font-size: 14px; font-weight: 700; white-space: nowrap; }
    .ny-col-money span { display: block; color: var(--ny-text-muted); font-size: 12px; font-weight: 600; }
    .ny-col-badge { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; border-radius: 999px; background: var(--c); color: var(--ny-surface); padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .ny-col-sub { margin: 8px 0 0; padding-left: 12px; border-left: 2px solid var(--ny-border); }
    .ny-col-empty h2 { margin: 8px 0 4px; }
    .ny-col-empty p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    @media (max-width: 800px) {
        .ny-col-kpis, .ny-col-filters { grid-template-columns: 1fr; }
        .ny-col-btn, .ny-col-quiet { min-height: 44px; }
    }
</style>
<div class="ny-col">
    <p class="ny-col-lead">{{ __('collections.lead') }}</p>
    <form class="ny-col-card ny-col-filters" method="GET" action="{{ route('company.collections.index') }}">
        <label>{{ __('collections.period') }}
            <select name="vista">
                <option value="mes" @selected($period['view'] === 'mes')>{{ __('collections.month') }}</option>
                <option value="semana" @selected($period['view'] === 'semana')>{{ __('collections.week') }}</option>
            </select>
        </label>
        <label>{{ __('collections.date') }}<input type="date" name="fecha" value="{{ $period['date'] }}"></label>
        <button class="ny-col-btn" type="submit">{{ __('collections.apply') }}</button>
        <a class="ny-col-quiet" href="{{ route('company.collections.export', request()->query()) }}">{{ __('collections.export') }}</a>
    </form>
    <p class="ny-col-eq">{{ $period['label'] }}. {{ __('collections.equation', [
        'opening' => $money->soles($statement['opening_cents']),
        'net' => $money->soles($statement['net_cents']),
        'refunds' => $money->soles($statement['refund_cents']),
        'paid' => $money->soles($statement['paid_cents']),
    ]) }}</p>
    <section class="ny-col-kpis">
        <article class="ny-col-kpi"><span class="ny-col-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="ticket" /></span><div><span>{{ __('collections.sales') }}</span><b data-count="sales">{{ $statement['sales_count'] }}</b></div></article>
        <article class="ny-col-kpi"><span class="ny-col-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="sales" /></span><div><span>{{ __('collections.fare') }}</span><b>{{ $money->soles($statement['fare_cents']) }}</b></div></article>
        <article class="ny-col-kpi"><span class="ny-col-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="service_fee" /></span><div><span>{{ __('collections.fee') }}</span><b>{{ $money->soles($statement['fee_cents']) }}</b><small>{{ __('collections.fee_note') }}</small></div></article>
        <article class="ny-col-kpi"><span class="ny-col-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="wallet" /></span><div><span>{{ __('collections.net') }}</span><b data-count="net">{{ $money->soles($statement['net_cents']) }}</b><small>{{ __('collections.net_note') }}</small></div></article>
        <article class="ny-col-kpi"><span class="ny-col-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="refunds" /></span><div><span>{{ __('collections.refunds') }}</span><b data-count="refunds">{{ $money->soles($statement['refund_cents']) }}</b></div></article>
        <article class="ny-col-kpi"><span class="ny-col-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="check" /></span><div><span>{{ __('collections.paid') }}</span><b data-count="paid">{{ $money->soles($statement['paid_cents']) }}</b></div></article>
        <article class="ny-col-kpi"><span class="ny-col-pill" style="--c: {{ $pendingTone }}"><x-ui.icon name="collections" /></span><div><span>{{ __('collections.pending') }}</span><b data-count="pending">{{ $money->soles($pending) }}</b>@if($pending <= 0)<small>{{ $pendingLabel }}</small>@endif</div></article>
    </section>

    <section class="ny-col-card">
        <h2>{{ __('collections.tickets') }}</h2>
        @forelse($statement['sales'] as $sale)
            @php($tone = match ($sale['state']) { 'paid' => 'var(--ny-tone-leaf)', 'refunded' => 'var(--ny-tone-violet)', 'partial' => 'var(--ny-tone-sun)', default => 'var(--ny-tone-sun)' })
            <article class="ny-col-row">
                <div>
                    <p>{{ $sale['code'] }} · {{ $sale['passenger'] }}</p>
                    <small>{{ \App\Support\DepartureSchedule::show($sale['when']) }}</small>
                    <span class="ny-col-badge" style="--c: {{ $tone }}">{{ __('collections.state_'.$sale['state']) }}</span>
                </div>
                <div class="ny-col-money">
                    {{ $money->soles($sale['net_cents']) }}
                    <span>{{ __('collections.fare') }} {{ $money->soles($sale['fare_cents']) }}</span>
                    <span>{{ __('collections.fee') }} {{ $money->soles($sale['fee_cents']) }}</span>
                </div>
            </article>
        @empty
            <div class="ny-col-empty">
                <h2>{{ __('collections.empty_tickets') }}</h2>
                <p>{{ __('collections.empty_tickets_text') }}</p>
            </div>
        @endforelse
    </section>

    <section class="ny-col-card">
        <h2>{{ __('collections.refund_list') }}</h2>
        @forelse($statement['refunds'] as $refund)
            <article class="ny-col-row">
                <div>
                    <p>{{ $refund['code'] }} · {{ $refund['passenger'] }}</p>
                    <small>{{ \App\Support\DepartureSchedule::show($refund['when']) }} · {{ $refund['reason'] }}</small>
                </div>
                <div class="ny-col-money">{{ $money->soles($refund['amount_cents']) }}</div>
            </article>
        @empty
            <p class="ny-col-eq">{{ __('collections.empty_tickets_text') }}</p>
        @endforelse
    </section>

    <section class="ny-col-card">
        <h2>{{ __('collections.payouts') }}</h2>
        @forelse($statement['payouts'] as $payout)
            <article class="ny-col-row">
                <div>
                    <p>{{ $payout['method'] }} · {{ $payout['operation'] }}</p>
                    <small>{{ $payout['when']?->format('d/m/Y') }}@if($payout['note']) · {{ $payout['note'] }}@endif</small>
                    <span class="ny-col-badge" style="--c: {{ $payout['status'] === 'voided' ? 'var(--ny-tone-coral)' : 'var(--ny-tone-leaf)' }}">{{ $payout['status'] === 'voided' ? __('collections.state_voided') : __('collections.state_paid') }}</span>
                    @if($payout['reason'])<small>{{ $payout['reason'] }}</small>@endif
                    @if($payout['tickets'])
                        <div class="ny-col-sub">
                            @foreach($payout['tickets'] as $ticket)
                                <p>{{ $ticket['code'] }} · {{ $ticket['passenger'] }} · {{ $money->soles($ticket['amount_cents']) }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="ny-col-money">{{ $money->soles($payout['amount_cents']) }}</div>
            </article>
        @empty
            <p class="ny-col-eq">{{ __('collections.empty_payouts') }}</p>
        @endforelse
    </section>
</div>
@endsection
