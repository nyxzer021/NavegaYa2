<style>
    .ny-book { display: grid; gap: 16px; }
    .ny-book-lead { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-book-head { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    .ny-book-card, .ny-book-row, .ny-book-empty { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-book-card, .ny-book-empty { padding: 16px; }
    .ny-book-row { display: grid; gap: 12px; padding: 16px; }
    .ny-book-row p { margin: 0; font-size: 14px; color: var(--ny-text); }
    .ny-book-row small { display: block; margin-top: 4px; color: var(--ny-text-muted); font-size: 12px; }
    .ny-book-badge { display: inline-flex; align-items: center; min-height: 28px; border-radius: 999px; padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .ny-book-badge.is-leaf { background: color-mix(in srgb, var(--ny-tone-leaf) 18%, white); color: var(--ny-tone-leaf-deep); }
    .ny-book-badge.is-sun { background: color-mix(in srgb, var(--ny-tone-sun) 28%, white); color: var(--ny-tone-sun-deep); }
    .ny-book-badge.is-coral { background: color-mix(in srgb, var(--ny-tone-coral) 18%, white); color: var(--ny-tone-coral-deep); }
    .ny-book label { display: grid; gap: 4px; color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-book textarea { width: 100%; min-height: 96px; border: 1px solid var(--ny-border); border-radius: 8px; padding: 8px 12px; color: var(--ny-text); font-size: 14px; }
    .ny-book-btn, .ny-book-quiet { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-book-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-book-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-book-error { margin: 0; color: var(--ny-tone-coral-deep); font-size: 14px; font-weight: 700; }
    .ny-book-empty h2 { margin: 8px 0 4px; font-size: 16px; }
    .ny-book-empty p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    @media (max-width: 800px) { .ny-book-head { display: grid; } .ny-book-btn, .ny-book-quiet, .ny-book textarea { min-height: 44px; } }
</style>
<div class="ny-book">
    <div class="ny-book-head">
        <p class="ny-book-lead">{{ __('claims.lead_panel') }}</p>
        <a class="ny-book-quiet" href="{{ $exportRoute }}">{{ __('claims.export') }}</a>
    </div>
    @if($errors->any())<p class="ny-book-error">{{ $errors->first() }}</p>@endif
    @forelse($claims as $claim)
        @php($count = $claim->countdown())
        <article class="ny-book-row" data-code="{{ $claim->code }}" data-tone="{{ $count['tone'] }}">
            <p><strong>{{ $claim->code }}</strong> · {{ $claim->consumer_name }}</p>
            <small>{{ __('claims.provider_'.$claim->provider) }} · {{ $claim->organization?->legal_name ?: __('claims.platform_legal_name') }} · {{ $claim->filed_on->format('d/m/Y') }}</small>
            <small>{{ __('claims.type_'.$claim->claim_type) }} · {{ $claim->good_description }}</small>
            <small>{{ $claim->detail }}</small>
            <span class="ny-book-badge is-{{ $count['tone'] }}">
                @if($count['overdue']){{ __('claims.overdue') }}@elseif($count['days'] === 0){{ __('claims.due_today') }}@else{{ __('claims.days_left', ['days' => $count['days']]) }}@endif
            </span>
            @if($claim->answered())
                <p>{{ $claim->response_text }}</p>
                <small>{{ __('claims.answered_on', ['date' => \App\Support\DepartureSchedule::show($claim->responded_at, 'd/m/Y H:i')]) }}</small>
            @elseif(($canAnswerCompany && $claim->provider === 'company') || (! $canAnswerCompany && $claim->provider === 'platform'))
                <form method="POST" action="{{ route($respondRoute, $claim) }}">
                    @csrf
                    <label>{{ __('claims.response') }}
                        <textarea name="response" required minlength="10" maxlength="4000" placeholder="{{ __('claims.response_placeholder') }}">{{ old('response') }}</textarea>
                    </label>
                    <button class="ny-book-btn" type="submit">{{ __('claims.send_response') }}</button>
                </form>
            @else
                <small>{{ __('claims.company_answers') }}</small>
            @endif
        </article>
    @empty
        <section class="ny-book-empty"><h2>{{ __('claims.book') }}</h2><p>{{ __('claims.empty') }}</p></section>
    @endforelse
</div>
