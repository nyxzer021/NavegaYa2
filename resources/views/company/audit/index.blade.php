@extends('layouts.company')
@section('title', __('company_portal.menu.audit'))
@section('page-title', __('company_portal.menu.audit'))
@section('content')
<style>
    .ny-audit { display: grid; gap: 16px; }
    .ny-audit-lead { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-audit-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .ny-audit-kpi, .ny-audit-card, .ny-audit-row { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-audit-kpi { display: flex; align-items: center; gap: 12px; padding: 16px; }
    .ny-audit-kpi b { display: block; font-size: 28px; line-height: 1; }
    .ny-audit-kpi span, .ny-audit label { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-audit-pill { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-audit-pill svg { width: 16px; height: 16px; }
    .ny-audit-card, .ny-audit-row { padding: 16px; }
    .ny-audit-filters { display: grid; grid-template-columns: 1.4fr 1.2fr repeat(2, 0.8fr) auto auto; gap: 12px; align-items: end; }
    .ny-audit label { display: grid; gap: 4px; }
    .ny-audit input, .ny-audit select { height: 36px; width: 100%; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 0 12px; color: var(--ny-text); font-size: 14px; }
    .ny-audit-btn, .ny-audit-quiet { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-audit-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-audit-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-audit-row { display: grid; grid-template-columns: 28px minmax(0, 1fr); gap: 12px; align-items: start; }
    .ny-audit-row p { margin: 0; font-size: 14px; }
    .ny-audit-row small { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; }
    .ny-audit-empty h2 { margin: 8px 0 4px; font-size: 16px; }
    .ny-audit-empty p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-audit-pages { font-size: 14px; }
    @media (max-width: 800px) {
        .ny-audit-kpis, .ny-audit-filters { grid-template-columns: 1fr; }
        .ny-audit-btn, .ny-audit-quiet { min-height: 44px; }
    }
</style>
<div class="ny-audit">
    <p class="ny-audit-lead">{{ __('audit.lead') }}</p>
    <section class="ny-audit-kpis">
        <article class="ny-audit-kpi"><span class="ny-audit-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="check" /></span><div><b>{{ $counts['today'] }}</b><span>{{ __('audit.today') }}</span></div></article>
        <article class="ny-audit-kpi"><span class="ny-audit-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="audit" /></span><div><b>{{ $counts['month'] }}</b><span>{{ __('audit.month') }}</span></div></article>
        <article class="ny-audit-kpi"><span class="ny-audit-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="reports" /></span><div><b>{{ $counts['listed'] }}</b><span>{{ __('audit.listed') }}</span></div></article>
    </section>
    <form class="ny-audit-card ny-audit-filters" method="GET" action="{{ route('company.audit.index') }}">
        <label>{{ __('audit.employee') }}
            <select name="empleado">
                <option value="">{{ __('audit.everyone') }}</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) $filters['empleado'] === (string) $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('audit.kind') }}
            <select name="tipo">
                <option value="">{{ __('audit.all_kinds') }}</option>
                @foreach($kinds as $kind)
                    <option value="{{ $kind }}" @selected($filters['tipo'] === $kind)>{{ __('audit.kind_'.$kind) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('audit.from') }}<input type="date" name="desde" value="{{ $filters['desde'] }}"></label>
        <label>{{ __('audit.until') }}<input type="date" name="hasta" value="{{ $filters['hasta'] }}"></label>
        <button class="ny-audit-btn" type="submit">{{ __('audit.apply') }}</button>
        <a class="ny-audit-quiet" href="{{ route('company.audit.export', request()->query()) }}">{{ __('audit.export') }}</a>
    </form>
    @forelse($logs as $log)
        @php($row = \App\Support\CompanyAudit::present($log))
        <article class="ny-audit-row">
            <span class="ny-audit-pill" style="--c: {{ $row['tone'] }}"><x-ui.icon :name="$row['icon']" /></span>
            <div>
                <p>{{ $row['sentence'] }}</p>
                <small>{{ \App\Support\DepartureSchedule::show($log->created_at) }} · {{ __('audit.ip') }} {{ $log->ip_address ?: __('audit.no_ip') }}</small>
            </div>
        </article>
    @empty
        <section class="ny-audit-card ny-audit-empty">
            <span class="ny-audit-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="audit" /></span>
            <h2>{{ __('audit.empty') }}</h2>
            <p>{{ __('audit.empty_text') }}</p>
        </section>
    @endforelse
    @if($logs->hasPages())
        <div class="ny-audit-pages">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
