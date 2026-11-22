@extends('layouts.company')
@section('title', __('company_portal.menu.reports'))
@section('page-title', __('company_portal.menu.reports'))
@section('content')
@php
    $query = request()->query();
    $collectionsQuery = in_array($report['range']['view'], ['semana', 'mes'], true)
        ? ['vista' => $report['range']['view'], 'fecha' => $report['range']['from']]
        : [];
@endphp
<style>
    .ny-rep { display: grid; gap: 16px; }
    .ny-rep-lead, .ny-rep-help, .ny-rep small { margin: 0; color: var(--ny-text-muted); font-size: 12px; }
    .ny-rep-lead, .ny-rep-help { font-size: 14px; }
    .ny-rep-kpis, .ny-rep-split { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .ny-rep-split { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ny-rep-kpi, .ny-rep-card, .ny-rep-row { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-rep-kpi { display: flex; align-items: center; gap: 12px; padding: 16px; }
    .ny-rep-kpi b, .ny-rep-money { text-align: right; }
    .ny-rep-kpi b { display: block; font-size: 28px; line-height: 1; }
    .ny-rep-kpi span, .ny-rep label { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-rep-pill { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-rep-pill svg, .ny-rep-badge svg { width: 16px; height: 16px; }
    .ny-rep-card { padding: 16px; display: grid; gap: 12px; }
    .ny-rep-head { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    .ny-rep-head h2 { margin: 0; font-size: 16px; }
    .ny-rep-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto; gap: 12px; align-items: end; }
    .ny-rep label { display: grid; gap: 4px; }
    .ny-rep input:not([type=checkbox]):not([type=radio]):not([type=file]), .ny-rep select { height: 36px; min-height: 36px; width: 100%; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 0 12px; color: var(--ny-text); font-size: 14px; }
    .ny-rep-btn, .ny-rep-quiet { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-rep-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-rep-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-rep-row { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(80px, 1fr) auto; gap: 12px; align-items: center; padding: 12px 16px; }
    .ny-rep-row p { margin: 0; font-size: 14px; }
    .ny-rep-row small { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; }
    .ny-rep-money { font-size: 14px; font-weight: 700; white-space: nowrap; }
    .ny-rep-track { height: 8px; border-radius: 999px; background: var(--ny-surface-muted); overflow: hidden; }
    .ny-rep-track span { display: block; height: 100%; width: var(--p); border-radius: 999px; background: var(--c); }
    .ny-rep-badge { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; background: var(--c); color: var(--ny-surface); padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .ny-rep-empty h2 { margin: 8px 0 4px; font-size: 16px; }
    .ny-rep-empty p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    @media (max-width: 800px) {
        .ny-rep-kpis, .ny-rep-split, .ny-rep-filters, .ny-rep-row, .ny-rep-head { grid-template-columns: 1fr; display: grid; }
        .ny-rep-btn, .ny-rep-quiet, .ny-rep input:not([type=checkbox]):not([type=radio]):not([type=file]), .ny-rep select { min-height: 44px; height: 44px; }
        .ny-rep-kpi b, .ny-rep-money { text-align: right; }
    }
</style>
<div class="ny-rep">
    <p class="ny-rep-lead">{{ __('reports.lead') }} <strong>{{ $report['range']['label'] }}</strong></p>
    <form class="ny-rep-card ny-rep-filters" method="GET" action="{{ route('company.reports.index') }}">
        <label>{{ __('reports.period') }}
            <select name="vista">
                @foreach (['hoy' => 'today', 'semana' => 'week', 'mes' => 'month', 'rango' => 'custom'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['vista'] === $value)>{{ __('reports.'.$label) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('reports.from') }}<input type="date" name="desde" value="{{ $filters['desde'] !== '' ? $filters['desde'] : $report['range']['from'] }}"></label>
        <label>{{ __('reports.until') }}<input type="date" name="hasta" value="{{ $filters['hasta'] !== '' ? $filters['hasta'] : $report['range']['to'] }}"></label>
        <button class="ny-rep-btn" type="submit">{{ __('reports.apply') }}</button>
    </form>

    <section class="ny-rep-kpis">
        <article class="ny-rep-kpi"><span class="ny-rep-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="sales" /></span><div><span>{{ __('reports.total') }}</span><b data-count="total">{{ $money->soles($report['total_cents']) }}</b></div></article>
        <article class="ny-rep-kpi"><span class="ny-rep-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="collections" /></span><div><span>{{ __('reports.collections') }}</span><b data-count="collections-net">{{ $money->soles($report['collections']['net_cents']) }}</b></div></article>
        <article class="ny-rep-kpi"><span class="ny-rep-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="parcels" /></span><div><span>{{ __('reports.parcels') }}</span><b data-count="parcels">{{ $money->soles($report['parcels']['cents']) }}</b></div></article>
        <article class="ny-rep-kpi"><span class="ny-rep-pill" style="--c: var(--ny-tone-coral)"><x-ui.icon name="cash_close" /></span><div><span>{{ __('reports.cash') }}</span><b data-count="cash">{{ $money->signed($report['cash']['cents']) }}</b></div></article>
    </section>

    <section class="ny-rep-card">
        <div class="ny-rep-head">
            <h2>{{ __('reports.by_channel') }}</h2>
            <a class="ny-rep-quiet" href="{{ route('company.reports.sales', array_merge($query, ['tabla' => 'canal'])) }}">{{ __('reports.export') }}</a>
        </div>
        <p class="ny-rep-help">{!! __('reports.fee_note', ['amount' => '<b data-count="fee">'.$money->soles($report['collections']['fee_cents']).'</b>']) !!}</p>
        @if ($report['total_count'] === 0)
            <div class="ny-rep-empty"><h2>{{ __('reports.empty_sales') }}</h2><p>{{ __('reports.lead') }}</p></div>
        @else
            @foreach ($report['channels'] as $channel)
                <article class="ny-rep-row">
                    <p>{{ $channel['label'] }}<small>{{ __('reports.passages') }}: {{ $channel['count'] }}</small></p>
                    <div class="ny-rep-track" style="--p: {{ $channel['percent'] }}%; --c: {{ $channel['tone'] }}"><span></span></div>
                    <b class="ny-rep-money" data-count="{{ $loop->index === 0 ? 'web' : ($loop->index === 1 ? 'counter' : 'agency') }}">{{ $money->soles($channel['cents']) }}</b>
                </article>
            @endforeach
        @endif
    </section>

    @foreach (['routes' => ['by_route', 'ruta'], 'departures' => ['by_departure', 'salida'], 'sellers' => ['by_seller', 'vendedor'], 'agencies_sales' => ['by_agency', 'agencia']] as $key => [$title, $table])
        <section class="ny-rep-card">
            <div class="ny-rep-head">
                <h2>{{ __('reports.'.$title) }}</h2>
                <a class="ny-rep-quiet" href="{{ route('company.reports.sales', array_merge($query, ['tabla' => $table])) }}">{{ __('reports.export') }}</a>
            </div>
            @forelse ($report[$key] as $row)
                <article class="ny-rep-row">
                    <p>{{ $row['label'] }}<small>{{ __('reports.passages') }}: {{ $row['count'] }}</small></p>
                    <span></span>
                    <b class="ny-rep-money">{{ $money->soles($row['cents']) }}</b>
                </article>
            @empty
                <div class="ny-rep-empty"><h2>{{ __('reports.empty_sales') }}</h2></div>
            @endforelse
        </section>
    @endforeach

    <section class="ny-rep-card">
        <div class="ny-rep-head">
            <h2>{{ __('reports.occupancy') }}</h2>
            <a class="ny-rep-quiet" href="{{ route('company.reports.occupancy', $query) }}">{{ __('reports.export') }}</a>
        </div>
        <p class="ny-rep-help">{{ __('reports.occupancy_help') }}</p>
        @forelse ($report['occupancy'] as $row)
            <article class="ny-rep-row" data-sold="{{ $row['sold'] }}" data-capacity="{{ $row['capacity'] }}">
                <p>{{ $row['label'] }}<small>{{ $row['when'] }}</small></p>
                <div class="ny-rep-track" style="--p: {{ $row['percent'] }}%; --c: var(--ny-tone-leaf)"><span></span></div>
                <b class="ny-rep-money" data-count="occupancy">{{ __('reports.sold_of', ['sold' => $row['sold'], 'capacity' => $row['capacity']]) }}</b>
            </article>
        @empty
            <div class="ny-rep-empty"><h2>{{ __('reports.empty_occupancy') }}</h2></div>
        @endforelse
    </section>

    <section class="ny-rep-split">
        <article class="ny-rep-card">
            <div class="ny-rep-head">
                <h2>{{ __('reports.parcels') }}</h2>
                <a class="ny-rep-quiet" href="{{ route('company.reports.parcels', $query) }}">{{ __('reports.export') }}</a>
            </div>
            <p class="ny-rep-help">{{ __('reports.due_help') }}</p>
            <p>{{ __('reports.count') }}: {{ $report['parcels']['count'] }} · {{ __('reports.kg') }}: {{ $report['parcels']['kg'] }}</p>
            <p>{{ __('reports.amount') }} <b data-count="parcel-amount">{{ $money->soles($report['parcels']['cents']) }}</b></p>
            <p>{{ __('reports.due') }} <b data-count="due">{{ $money->soles($report['parcels']['due_cents']) }}</b> <small>{{ __('reports.count') }}: {{ $report['parcels']['due_count'] }}</small></p>
        </article>
        <article class="ny-rep-card">
            <div class="ny-rep-head">
                <h2>{{ __('reports.cash') }}</h2>
                <a class="ny-rep-quiet" href="{{ route('company.reports.cash', $query) }}">{{ __('reports.export') }}</a>
            </div>
            <p class="ny-rep-help">{{ __('reports.cash_help') }}</p>
            @forelse ($report['cash']['rows'] as $row)
                @php($tone = $row['cents'] > 0 ? 'var(--ny-tone-leaf)' : ($row['cents'] < 0 ? 'var(--ny-tone-coral)' : 'var(--ny-tone-sun)'))
                <article class="ny-rep-row">
                    <p>{{ $row['seller'] }}<small>{{ __('reports.sessions') }}: {{ $row['sessions'] }}</small></p>
                    <span class="ny-rep-badge" style="--c: {{ $tone }}">{{ $row['cents'] > 0 ? __('reports.over') : ($row['cents'] < 0 ? __('reports.short') : __('reports.exact')) }}</span>
                    <b class="ny-rep-money">{{ $money->signed($row['cents']) }}</b>
                </article>
            @empty
                <div class="ny-rep-empty"><h2>{{ __('reports.empty_cash') }}</h2></div>
            @endforelse
        </article>
    </section>

    <section class="ny-rep-split">
        <article class="ny-rep-card">
            <div class="ny-rep-head"><h2>{{ __('reports.refunds') }}</h2><a class="ny-rep-quiet" href="{{ route('company.refunds.index') }}">{{ __('reports.open_refunds') }}</a></div>
            <p class="ny-rep-help">{{ __('reports.refunds_help') }}</p>
            <p><span class="ny-rep-badge" style="--c: var(--ny-tone-sun)"><x-ui.icon name="refunds" />{{ __('reports.requested') }}</span> <b data-count="refunds-requested">{{ $report['refunds']['requested'] }}</b></p>
            <p><span class="ny-rep-badge" style="--c: var(--ny-tone-sun)"><x-ui.icon name="refunds" />{{ __('reports.approved') }}</span> <b>{{ $money->soles($report['refunds']['approved_cents']) }}</b></p>
            <p><span class="ny-rep-badge" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="check" />{{ __('reports.paid') }}</span> <b data-count="refunds-paid">{{ $money->soles($report['refunds']['paid_cents']) }}</b></p>
            <p><span class="ny-rep-badge" style="--c: var(--ny-tone-coral)"><x-ui.icon name="refunds" />{{ __('reports.rejected') }}</span> <b>{{ $report['refunds']['rejected'] }}</b></p>
        </article>
        <article class="ny-rep-card">
            <div class="ny-rep-head"><h2>{{ __('reports.accounts') }}</h2><a class="ny-rep-quiet" href="{{ route('company.agency-commissions.index') }}">{{ __('reports.open_accounts') }}</a></div>
            <p class="ny-rep-help">{{ __('reports.accounts_help') }}</p>
            <p>{{ __('reports.amount') }} <b>{{ $money->signed($report['agency_accounts']['amount_cents']) }}</b></p>
            <p>{{ __('reports.collected') }} <b>{{ $money->signed($report['agency_accounts']['paid_cents']) }}</b></p>
            <p>{{ __('reports.balance') }} <b data-count="agency-balance">{{ $money->signed($report['agency_accounts']['balance_cents']) }}</b></p>
            <p>{{ __('reports.overdue') }} <b data-count="agency-overdue">{{ $money->signed($report['agency_accounts']['overdue_cents']) }}</b></p>
        </article>
    </section>

    <section class="ny-rep-split">
        <article class="ny-rep-card">
            <div class="ny-rep-head"><h2>{{ __('reports.collections') }}</h2><a class="ny-rep-quiet" href="{{ route('company.collections.index', $collectionsQuery) }}">{{ __('reports.open_collections') }}</a></div>
            <p class="ny-rep-help">{{ __('reports.collections_help') }}</p>
            <p>{{ __('reports.net') }} <b>{{ $money->soles($report['collections']['net_cents']) }}</b> · {{ __('reports.pending') }} <b data-count="collections-pending">{{ $money->soles($report['collections']['pending_cents']) }}</b></p>
        </article>
        <article class="ny-rep-card">
            <div class="ny-rep-head"><h2>{{ __('reports.receipts') }}</h2><a class="ny-rep-quiet" href="{{ route('company.receipts.index', ['desde' => $report['range']['from'], 'hasta' => $report['range']['to']]) }}">{{ __('reports.open_receipts') }}</a></div>
            <p>{{ __('reports.passages') }}: {{ $report['receipts']['tickets'] }} · {{ $money->soles($report['receipts']['ticket_cents']) }}</p>
            <p>{{ __('reports.parcels') }}: {{ $report['receipts']['parcels'] }} · <b data-count="receipts-parcels">{{ $money->soles($report['receipts']['parcel_cents']) }}</b></p>
        </article>
    </section>
</div>
@endsection
