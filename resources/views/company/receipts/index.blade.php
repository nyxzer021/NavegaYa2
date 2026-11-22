@extends('layouts.company')
@section('title', __('company_portal.menu.receipts'))
@section('page-title', __('company_portal.menu.receipts'))
@section('content')
<style>
    .ny-rcp { display: grid; gap: 16px; }
    .ny-rcp-lead { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-rcp-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .ny-rcp-kpi, .ny-rcp-card, .ny-rcp-row { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-rcp-kpi { display: flex; align-items: center; gap: 12px; padding: 16px; }
    .ny-rcp-kpi b { display: block; font-size: 28px; line-height: 1; text-align: right; }
    .ny-rcp-kpi span, .ny-rcp label { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-rcp-pill { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-rcp-pill svg { width: 16px; height: 16px; }
    .ny-rcp-card { padding: 16px; }
    .ny-rcp-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto auto; gap: 12px; align-items: end; }
    .ny-rcp label { display: grid; gap: 4px; }
    .ny-rcp input:not([type=checkbox]):not([type=radio]):not([type=file]), .ny-rcp select { height: 36px; min-height: 36px; width: 100%; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 0 12px; color: var(--ny-text); font-size: 14px; }
    .ny-rcp-btn, .ny-rcp-quiet { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-rcp-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-rcp-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-rcp-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: start; padding: 16px; }
    .ny-rcp-row p { margin: 0; font-size: 14px; }
    .ny-rcp-row small { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; }
    .ny-rcp-money { text-align: right; font-size: 14px; font-weight: 700; white-space: nowrap; }
    .ny-rcp-badge { display: inline-flex; align-items: center; gap: 4px; margin-top: 8px; margin-right: 6px; border-radius: 999px; background: var(--c); color: var(--ny-surface); padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .ny-rcp-badge svg { width: 16px; height: 16px; }
    .ny-rcp-docs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .ny-rcp-empty h2 { margin: 8px 0 4px; font-size: 16px; }
    .ny-rcp-empty p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    @media (max-width: 800px) {
        .ny-rcp-kpis, .ny-rcp-filters, .ny-rcp-row { grid-template-columns: 1fr; }
        .ny-rcp-btn, .ny-rcp-quiet, .ny-rcp input:not([type=checkbox]):not([type=radio]):not([type=file]), .ny-rcp select { min-height: 44px; height: 44px; }
        .ny-rcp-money { text-align: left; }
    }
</style>
<div class="ny-rcp">
    <p class="ny-rcp-lead">{{ __('receipts.lead') }}</p>
    <form class="ny-rcp-card ny-rcp-filters" method="GET" action="{{ route('company.receipts.index') }}">
        <label>{{ __('receipts.from') }}<input type="date" name="desde" value="{{ $filters['desde'] }}"></label>
        <label>{{ __('receipts.until') }}<input type="date" name="hasta" value="{{ $filters['hasta'] }}"></label>
        <label>{{ __('receipts.kind') }}
            <select name="tipo">
                <option value="">{{ __('receipts.all_kinds') }}</option>
                <option value="boleto" @selected($filters['tipo'] === 'boleto')>{{ __('receipts.kind_ticket') }}</option>
                <option value="encomienda" @selected($filters['tipo'] === 'encomienda')>{{ __('receipts.kind_parcel') }}</option>
            </select>
        </label>
        <label>{{ __('receipts.client') }}<input name="cliente" value="{{ $filters['cliente'] }}" placeholder="{{ __('receipts.client_placeholder') }}"></label>
        <button class="ny-rcp-btn" type="submit">{{ __('receipts.apply') }}</button>
        <a class="ny-rcp-quiet" href="{{ route('company.receipts.export', request()->query()) }}">{{ __('receipts.export') }}</a>
    </form>
    <section class="ny-rcp-kpis">
        <article class="ny-rcp-kpi"><span class="ny-rcp-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="receipts" /></span><div><span>{{ __('receipts.kpi_receipts') }}</span><b data-count="receipts">{{ $catalog['kpis']['receipts'] }}</b></div></article>
        <article class="ny-rcp-kpi"><span class="ny-rcp-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="ticket" /></span><div><span>{{ __('receipts.kpi_tickets') }}</span><b data-count="tickets">{{ $money->soles($catalog['kpis']['ticket_cents']) }}</b></div></article>
        <article class="ny-rcp-kpi"><span class="ny-rcp-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="parcels" /></span><div><span>{{ __('receipts.kpi_parcels') }}</span><b data-count="parcels">{{ $money->soles($catalog['kpis']['parcel_cents']) }}</b></div></article>
        <article class="ny-rcp-kpi"><span class="ny-rcp-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="page" /></span><div><span>{{ __('receipts.kpi_invoices') }}</span><b data-count="invoices">{{ $catalog['kpis']['invoices'] }}</b></div></article>
    </section>
    @forelse($catalog['rows'] as $row)
        <article class="ny-rcp-row">
            <div>
                <p>{{ $row['client'] }}</p>
                <small>{{ \App\Support\DepartureSchedule::show($row['when'], 'Y-m-d H:i') }} · {{ $row['document'] }}@if($row['reference']) · {{ $row['reference'] }}@endif</small>
                <span class="ny-rcp-badge" style="--c: {{ $row['tone'] }}"><x-ui.icon name="{{ $row['icon'] }}" />{{ $row['channel_label'] }}</span>
                <span class="ny-rcp-badge" style="--c: {{ $row['status_tone'] }}">{{ $row['status_label'] }}</span>
                @if($row['invoice'])<span class="ny-rcp-badge" style="--c: var(--ny-tone-sun)">{{ __('receipts.wants_invoice') }}</span>@endif
                <div class="ny-rcp-docs">
                    @foreach($row['tickets'] as $ticket)
                        <a class="ny-rcp-quiet" href="{{ route('company.receipts.ticket', $ticket['id']) }}">{{ __('receipts.ticket') }} {{ $ticket['code'] }}</a>
                    @endforeach
                    @if($row['shipment_id'])
                        <a class="ny-rcp-quiet" href="{{ route('company.receipts.guide', $row['shipment_id']) }}">{{ __('receipts.guide') }}</a>
                    @endif
                    @if($row['receipt'])
                        <a class="ny-rcp-quiet" href="{{ route('company.receipts.certificate', $row['receipt']) }}">{{ __('receipts.certificate') }} {{ $row['receipt']->number }}</a>
                    @else
                        <form method="POST" action="{{ route('company.receipts.issue', $row['payment']) }}">
                            @csrf
                            <button class="ny-rcp-btn" type="submit">{{ __('receipts.issue') }}</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="ny-rcp-money">{{ $money->soles($row['amount_cents']) }}</div>
        </article>
    @empty
        <section class="ny-rcp-card ny-rcp-empty">
            <span class="ny-rcp-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="receipts" /></span>
            <h2>{{ __('receipts.empty_title') }}</h2>
            <p>{{ __('receipts.empty_body') }}</p>
        </section>
    @endforelse
</div>
@endsection
