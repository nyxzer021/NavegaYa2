<x-public-layout :title="__('claims.book').' · '.$claim->code">
<style>
    .ny-sheet { max-width: 880px; margin: 24px auto; padding: 0 16px 32px; display: grid; gap: 16px; }
    .ny-sheet h1 { margin: 0; font-size: 28px; color: var(--ny-text); }
    .ny-sheet p { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-sheet-card { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); padding: 16px; }
    .ny-sheet table { width: 100%; border-collapse: collapse; }
    .ny-sheet td { padding: 10px 12px; border-bottom: 1px solid var(--ny-border); vertical-align: top; font-size: 14px; color: var(--ny-text); }
    .ny-sheet .label { display: block; margin-bottom: 4px; color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-sheet-btn { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; border-radius: 8px; padding: 0 12px; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-sheet-badge { display: inline-flex; align-items: center; min-height: 28px; border-radius: 999px; padding: 2px 8px; font-size: 12px; font-weight: 700; }
    .ny-sheet-badge.is-leaf { background: color-mix(in srgb, var(--ny-tone-leaf) 18%, white); color: var(--ny-tone-leaf-deep); }
    .ny-sheet-badge.is-sun { background: color-mix(in srgb, var(--ny-tone-sun) 28%, white); color: var(--ny-tone-sun-deep); }
    .ny-sheet-badge.is-coral { background: color-mix(in srgb, var(--ny-tone-coral) 18%, white); color: var(--ny-tone-coral-deep); }
    @media (max-width: 700px) { .ny-sheet table, .ny-sheet tr, .ny-sheet td { display: block; width: 100%; } .ny-sheet-btn { min-height: 44px; } }
</style>
<main class="ny-sheet">
    <h1>{{ __('claims.sheet') }}</h1>
    <p>{{ __('claims.copy_sent') }}</p>
    <p><span class="ny-sheet-badge is-{{ $countdown['tone'] }}" data-tone="{{ $countdown['tone'] }}">
        @if($countdown['overdue']){{ __('claims.overdue') }}@elseif($countdown['days'] === 0){{ __('claims.due_today') }}@else{{ __('claims.days_left', ['days' => $countdown['days']]) }}@endif
    </span></p>
    <a class="ny-sheet-btn" href="{{ route('claims.pdf', $claim->public_token) }}"><x-ui.icon name="scale" class="h-4 w-4" />{{ __('claims.download') }}</a>
    <section class="ny-sheet-card">
        <p><strong>{{ $provider_name }}</strong> · {{ __('claims.ruc') }} {{ $provider_ruc }}</p>
        @include('claims.fields')
    </section>
</main>
</x-public-layout>
