@extends('layouts.company')
@section('title', __('company_portal.menu.online_payments'))
@section('page-title', __('company_portal.menu.online_payments'))

@section('content')
<style>
    .ny-online { display: grid; gap: 16px; max-width: 720px; }
    .ny-online p, .ny-online dd { margin: 0; font-size: 14px; }
    .ny-online-card { display: grid; gap: 12px; border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); padding: 16px; box-shadow: var(--ny-shadow-sm); }
    .ny-online h2 { margin: 0; font-size: 16px; }
    .ny-online-rate { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .ny-online-rate b { font-size: 28px; line-height: 1; }
    .ny-online label, .ny-online .ny-online-k { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-online-form { display: grid; gap: 12px; }
    .ny-online .ny-company-field { display: grid; gap: 4px; }
    .ny-online button, .ny-online-link { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-online button { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-online-link { width: fit-content; border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-online-error { color: var(--ny-danger); font-size: 12px; }
    @media (min-width: 800px) {
        .ny-online button, .ny-online-link { min-height: 36px; }
    }
</style>
<div class="ny-online">
    <p>{{ __('online_payments.lead') }}</p>
    <section class="ny-online-card">
        <div class="ny-online-rate">
            <span class="ny-online-k">{{ __('online_payments.agreement') }}</span>
            <b>{{ __('online_payments.rate', ['rate' => $rate]) }}</b>
        </div>
        <a class="ny-online-link" href="{{ route('company.collections.index') }}">{{ __('online_payments.collections') }}</a>
    </section>
    <section class="ny-online-card">
        <h2>{{ __('online_payments.account_title') }}</h2>
        <p>{{ __('online_payments.account_help') }}</p>
        <div class="ny-online-rate">
            <span class="ny-online-k">{{ __('online_payments.account') }}</span>
            <span>@include('company.online-payments.presence', ['present' => $accountPresent, 'tail' => $accountTail])</span>
        </div>
        <div class="ny-online-rate">
            <span class="ny-online-k">{{ __('online_payments.cci') }}</span>
            <span>@include('company.online-payments.presence', ['present' => $cciPresent, 'tail' => $cciTail])</span>
        </div>
        <form class="ny-online-form" method="POST" action="{{ route('company.finance.bank') }}">
            @csrf
            @method('PATCH')
            <label class="ny-company-field">{{ __('online_payments.bank') }}
                <input name="bank_name" value="{{ old('bank_name', $bankName) }}" maxlength="120" required>
            </label>
            @error('bank_name')<span class="ny-online-error">{{ $message }}</span>@enderror
            <label class="ny-company-field">{{ __('online_payments.account') }}
                <input name="bank_account" value="" maxlength="40" required autocomplete="off">
            </label>
            @error('bank_account')<span class="ny-online-error">{{ $message }}</span>@enderror
            <label class="ny-company-field">{{ __('online_payments.cci') }}
                <input name="bank_cci" value="" inputmode="numeric" maxlength="20" autocomplete="off">
            </label>
            @error('bank_cci')<span class="ny-online-error">{{ $message }}</span>@enderror
            <button type="submit">{{ __('online_payments.save') }}</button>
        </form>
    </section>
</div>
@endsection
