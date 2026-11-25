<x-public-layout :title="__('claims.book')">
<style>
    .ny-claim { max-width: 880px; margin: 24px auto; padding: 0 16px 40px; display: grid; gap: 16px; }
    .ny-claim h1 { margin: 0; font-size: 20px; color: var(--ny-text); }
    .ny-claim h2 { margin: 0; font-size: 16px; color: var(--ny-text); }
    .ny-claim p, .ny-claim small { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-claim-card { display: grid; gap: 12px; border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); padding: 16px 20px; }
    .ny-claim-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .ny-claim label { display: grid; gap: 4px; color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-claim input:not([type=checkbox]):not([type=radio]), .ny-claim select, .ny-claim textarea { width: 100%; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 8px 12px; color: var(--ny-text); font-size: 14px; }
    .ny-claim input:not([type=checkbox]):not([type=radio]), .ny-claim select { height: 40px; }
    .ny-claim textarea { min-height: 96px; }
    .ny-claim-choice { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .ny-claim-choice label, .ny-claim-check { display: flex; gap: 8px; align-items: flex-start; min-height: 44px; border: 1px solid var(--ny-border); border-radius: 8px; padding: 12px; color: var(--ny-text); font-size: 14px; font-weight: 700; }
    .ny-claim-choice input, .ny-claim-check input { width: 18px; height: 18px; margin-top: 2px; }
    .ny-claim-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; border: 0; border-radius: 8px; padding: 0 16px; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); font-size: 14px; font-weight: 700; }
    .ny-claim-error { margin: 0; color: var(--ny-tone-coral-deep); font-size: 14px; font-weight: 700; }
    .ny-claim-date-label { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-claim-date { font-size: 16px; color: var(--ny-text); font-weight: 700; }
    @media (max-width: 700px) {
        .ny-claim-grid, .ny-claim-choice { grid-template-columns: 1fr; }
        .ny-claim input:not([type=checkbox]):not([type=radio]), .ny-claim select, .ny-claim-btn { min-height: 44px; height: 44px; }
        .ny-claim textarea { height: auto; }
    }
</style>
<form class="ny-claim" method="POST" action="{{ route('claims.store') }}">
    @csrf
    <h1>{{ __('claims.book') }}</h1>
    <p>{{ __('claims.lead') }}</p>
    <p>{{ __('claims.deadline', ['days' => $days]) }}</p>
    @if($errors->any())<p class="ny-claim-error">{{ $errors->first() }}</p>@endif
    <section class="ny-claim-card">
        <h2>{{ __('claims.provider_heading') }}</h2>
        <div class="ny-claim-choice">
            <label><input type="radio" name="provider" value="company" @checked(old('provider', $organization ? 'company' : 'platform') === 'company')>{{ __('claims.provider_company') }}</label>
            <label><input type="radio" name="provider" value="platform" @checked(old('provider', $organization ? 'company' : 'platform') === 'platform')>{{ __('claims.provider_platform') }}</label>
        </div>
        <small>{{ __('claims.provider_company_help') }}</small>
        <small>{{ __('claims.provider_platform_help') }}</small>
        <p>{{ __('claims.platform_legal_name') }} · {{ __('claims.ruc') }} {{ __('claims.platform_ruc') }}</p>
        <label>{{ __('claims.company') }}
            <select name="organization_id">
                <option value="">{{ __('claims.company') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('organization_id', $organization?->id) === $company->id)>{{ $company->legal_name }} · {{ __('claims.ruc') }} {{ $company->ruc }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('claims.reservation') }}<input name="reservation_code" value="{{ old('reservation_code', $reservationCode) }}" maxlength="40"></label>
        <div><span class="ny-claim-date-label">{{ __('claims.date') }}</span><div class="ny-claim-date">{{ $filedOn }}</div></div>
    </section>
    <section class="ny-claim-card">
        <h2>{{ __('claims.consumer') }}</h2>
        <div class="ny-claim-grid">
            <label>{{ __('claims.name') }}<input name="consumer_name" value="{{ old('consumer_name') }}" required maxlength="160"></label>
            <label>{{ __('claims.document_type') }}
                <select name="document_type" required>
                    @foreach(['dni', 'ce', 'passport'] as $document)
                        <option value="{{ $document }}" @selected(old('document_type') === $document)>{{ __('claims.document_'.$document) }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('claims.document_number') }}<input name="document_number" value="{{ old('document_number') }}" required maxlength="20"></label>
            <label>{{ __('claims.phone') }}<input name="phone" value="{{ old('phone') }}" required maxlength="30"></label>
            <label>{{ __('claims.address') }}<input name="address" value="{{ old('address') }}" required maxlength="200"></label>
            <label>{{ __('claims.email') }}<input name="email" type="email" value="{{ old('email') }}" required maxlength="160"></label>
        </div>
        <label class="ny-claim-check"><input type="checkbox" name="is_minor" value="1" @checked(old('is_minor'))>{{ __('claims.minor') }}</label>
        <small>{{ __('claims.minor_help') }}</small>
        <div class="ny-claim-grid">
            <label>{{ __('claims.guardian_name') }}<input name="guardian_name" value="{{ old('guardian_name') }}" maxlength="160"></label>
            <label>{{ __('claims.guardian_document') }}<input name="guardian_document" value="{{ old('guardian_document') }}" maxlength="20"></label>
            <label>{{ __('claims.guardian_relationship') }}
                <select name="guardian_relationship">
                    <option value="">{{ __('claims.guardian_relationship') }}</option>
                    @foreach(['padre', 'madre', 'apoderado'] as $relationship)
                        <option value="{{ $relationship }}" @selected(old('guardian_relationship') === $relationship)>{{ __('claims.relationship_'.$relationship) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>
    <section class="ny-claim-card">
        <h2>{{ __('claims.good_heading') }}</h2>
        <div class="ny-claim-grid">
            <label>{{ __('claims.good_kind') }}
                <select name="good_kind" required>
                    <option value="service" @selected(old('good_kind', 'service') === 'service')>{{ __('claims.good_service') }}</option>
                    <option value="product" @selected(old('good_kind') === 'product')>{{ __('claims.good_product') }}</option>
                </select>
            </label>
            <label>{{ __('claims.amount') }}<input name="amount" type="number" min="0" step="0.01" value="{{ old('amount', $amount) }}" required inputmode="decimal"></label>
        </div>
        <label>{{ __('claims.description') }}<input name="good_description" value="{{ old('good_description', $description) }}" required minlength="5" maxlength="500"></label>
        <h2>{{ __('claims.type_heading') }}</h2>
        <div class="ny-claim-choice">
            <label><input type="radio" name="claim_type" value="reclamo" @checked(old('claim_type', 'reclamo') === 'reclamo')>{{ __('claims.type_reclamo') }}</label>
            <label><input type="radio" name="claim_type" value="queja" @checked(old('claim_type') === 'queja')>{{ __('claims.type_queja') }}</label>
        </div>
        <small>{{ __('claims.type_reclamo_help') }}</small>
        <small>{{ __('claims.type_queja_help') }}</small>
        <label>{{ __('claims.detail') }}<textarea name="detail" required minlength="10" maxlength="2000">{{ old('detail') }}</textarea></label>
        <label>{{ __('claims.request') }}<textarea name="request_text" required minlength="5" maxlength="1000">{{ old('request_text') }}</textarea></label>
        <label class="ny-claim-check"><input type="checkbox" name="email_accepted" value="1" @checked(old('email_accepted')) required>{{ __('claims.email_consent') }}</label>
        <button class="ny-claim-btn" type="submit"><x-ui.icon name="scale" class="h-4 w-4" />{{ __('claims.submit') }}</button>
    </section>
</form>
</x-public-layout>
