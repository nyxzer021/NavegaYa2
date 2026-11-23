@extends('layouts.admin')

@section('title', __('admin_portal.menu.reconciliation').' · NavegaYA')

@section('content')
<style>
    .ny-main .ny-recon { display: grid; gap: 16px; max-width: 960px; color: var(--ny-text); font-size: 14px; }
    .ny-main .ny-recon h1 { margin: 0; font-size: 20px; }
    .ny-main .ny-recon h2 { margin: 0; font-size: 16px; }
    .ny-main .ny-recon p, .ny-main .ny-recon li, .ny-main .ny-recon label, .ny-main .ny-recon span, .ny-main .ny-recon button, .ny-main .ny-recon input, .ny-main .ny-recon a { font-size: 14px !important; }
    .ny-main .ny-recon .ny-recon-k { font-size: 12px !important; font-weight: 700; color: var(--ny-text-muted); }
    .ny-main .ny-recon .ny-recon-figure { font-size: 28px !important; line-height: 1; }
    .ny-main .ny-recon-card { display: grid; gap: 12px; border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); padding: 16px; box-shadow: var(--ny-shadow-sm); }
    .ny-main .ny-recon-kpis, .ny-main .ny-recon-days { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .ny-main .ny-recon-actions, .ny-main .ny-recon-review { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; }
    .ny-main .ny-recon input { width: 100%; min-width: 0; min-height: 40px !important; }
    .ny-main .ny-recon button, .ny-main .ny-recon-link { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; border-radius: 8px; padding: 0 12px; font-weight: 700; text-decoration: none; }
    .ny-main .ny-recon button { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-main .ny-recon-link { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-main .ny-recon-note { margin: 0; border-radius: 8px; padding: 12px; background: var(--ny-surface-muted); }
    .ny-main .ny-recon-ok { background: var(--ny-success-bg); color: var(--ny-success); }
    .ny-main .ny-recon-bad { background: var(--ny-danger-bg); color: var(--ny-danger); }
    .ny-main .ny-recon-row { display: flex; justify-content: space-between; gap: 12px; min-width: 0; }
    .ny-main .ny-recon-badge { border-radius: 999px; padding: 2px 8px; font-size: 12px !important; font-weight: 700; color: var(--ny-surface); background: var(--ny-success); }
    @media (max-width: 799px) {
        .ny-main .ny-recon-kpis, .ny-main .ny-recon-days { grid-template-columns: 1fr; }
    }
    @media (min-width: 800px) {
        .ny-main .ny-recon button, .ny-main .ny-recon-link { min-height: 36px; }
    }
</style>
<div class="ny-recon">
    <div>
        <h1>{{ __('admin_portal.menu.reconciliation') }}</h1>
        <p>{{ __('reconciliation.lead') }}</p>
    </div>
    @if (session('status'))
        <p class="ny-recon-note ny-recon-ok">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ny-recon-note ny-recon-bad">{{ session('error') }}</p>
    @endif
    @error('archivo')
        <p class="ny-recon-note ny-recon-bad">{{ $message }}</p>
    @enderror

    <form class="ny-recon-actions" method="GET">
        <label class="ny-recon-k">{{ __('reconciliation.month') }}
            <input type="month" name="mes" value="{{ $month }}">
        </label>
        <button type="submit">{{ __('reconciliation.see') }}</button>
        <a class="ny-recon-link" href="{{ route('admin.reconciliation.export', ['mes' => $month]) }}">{{ __('reconciliation.export') }}</a>
    </form>

    <section class="ny-recon-kpis">
        @foreach ([
            'summary_charged' => $summary['charged'],
            'summary_refunded' => $summary['refunded'],
            'summary_fee' => $summary['culqi_fee'],
            'summary_service' => $summary['service_fee'],
            'summary_fares' => $summary['fares'],
            'summary_deposited' => $summary['deposited'],
        ] as $label => $amount)
            <article class="ny-recon-card">
                <span class="ny-recon-k">{{ __('reconciliation.'.$label) }}</span>
                <b>S/ {{ $amount }}</b>
            </article>
        @endforeach
        <article class="ny-recon-card">
            <span class="ny-recon-k">{{ __('reconciliation.summary_gain') }}</span>
            <b class="ny-recon-figure">S/ {{ $summary['net_gain'] }}</b>
            <p class="ny-recon-k">{{ __('reconciliation.gain_help') }}</p>
        </article>
    </section>

    <section class="ny-recon-card">
        <h2>{{ __('reconciliation.upload') }}</h2>
        <form class="ny-recon-actions" method="POST" action="{{ route('admin.reconciliation.import') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="mes" value="{{ $month }}">
            <input type="file" name="archivo" accept=".csv,text/csv" required>
            <button type="submit">{{ __('reconciliation.import') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.reconciliation.fetch') }}">
            @csrf
            <input type="hidden" name="mes" value="{{ $month }}">
            <button type="submit">{{ __('reconciliation.fetch') }}</button>
        </form>
    </section>

    <section>
        <h2>{{ __('reconciliation.days') }}</h2>
        <div class="ny-recon-days">
            @forelse ($days as $day)
                <article class="ny-recon-card">
                    <strong>{{ $day['date'] }}</strong>
                    <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.culqi_charges') }}</span><span>S/ {{ $day['culqi'] }}</span></div>
                    <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.local_charges') }}</span><span>S/ {{ $day['local'] }}</span></div>
                    <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.culqi_refunds') }}</span><span>S/ {{ $day['culqi_refund'] }}</span></div>
                    <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.local_refunds') }}</span><span>S/ {{ $day['local_refund'] }}</span></div>
                    <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.fee') }}</span><span>S/ {{ $day['fee'] }}</span></div>
                    <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.net') }}</span><span>S/ {{ $day['net'] }}</span></div>
                </article>
            @empty
                <p>{{ __('reconciliation.none') }}</p>
            @endforelse
        </div>
    </section>

    <section class="ny-recon-card">
        <h2>{{ __('reconciliation.differences') }}</h2>
        @forelse ($differences as $difference)
            <article class="ny-recon-card">
                <div class="ny-recon-row">
                    <strong>{{ __('reconciliation.kind_'.$difference['kind']) }}</strong>
                    @if ($difference['reviewed'])
                        <span class="ny-recon-badge">{{ __('reconciliation.reviewed_badge') }}</span>
                    @endif
                </div>
                <div class="ny-recon-row"><span class="ny-recon-k">{{ $difference['date'] }}</span><span>{{ $difference['reference'] }}</span></div>
                <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.culqi_amount') }}</span><span>{{ $difference['culqi'] === null ? __('connections.absent') : 'S/ '.$difference['culqi'] }}</span></div>
                <div class="ny-recon-row"><span class="ny-recon-k">{{ __('reconciliation.local_amount') }}</span><span>{{ $difference['local'] === null ? __('connections.absent') : 'S/ '.$difference['local'] }}</span></div>
                @if ($difference['note'])
                    <p>{{ $difference['note'] }}</p>
                @endif
                <form class="ny-recon-review" method="POST" action="{{ route('admin.reconciliation.review') }}">
                    @csrf
                    <input type="hidden" name="mes" value="{{ $month }}">
                    <input type="hidden" name="difference_key" value="{{ $difference['key'] }}">
                    <label class="ny-recon-k">{{ __('reconciliation.note') }}
                        <input name="note" maxlength="500" required value="">
                    </label>
                    <button type="submit">{{ __('reconciliation.review') }}</button>
                </form>
            </article>
        @empty
            <p>{{ __('reconciliation.none') }}</p>
        @endforelse
    </section>
</div>
@endsection
