@extends('layouts.company')
@section('title', __('company_portal.menu.roles'))
@section('page-title', __('company_portal.menu.roles'))
@section('content')
@php
    $tones = [
        'admin' => 'var(--ny-tone-violet)',
        'seller' => 'var(--ny-tone-river)',
        'operator' => 'var(--ny-tone-leaf)',
        'finance' => 'var(--ny-tone-sun)',
    ];
    $icons = [
        'admin' => 'roles',
        'seller' => 'pos',
        'operator' => 'fleet',
        'finance' => 'reports',
    ];
    $labels = [
        'admin' => 'admin',
        'seller' => 'counter',
        'operator' => 'operator',
        'finance' => 'finance',
    ];
    $group = null;
@endphp
<style>
    .ny-roles { display: grid; gap: 16px; }
    .ny-roles-lead, .ny-roles-note { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-roles-profiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .ny-roles-profile { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); padding: 16px; }
    .ny-roles-pill { display: grid; width: 28px; height: 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-roles-pill svg, .ny-roles-mod svg, .ny-roles-mark svg { width: 16px; height: 16px; }
    .ny-roles-profile h2 { margin: 8px 0 0; font-size: 16px; line-height: 1.3; }
    .ny-roles-profile p { margin: 4px 0 0; color: var(--ny-text-muted); font-size: 12px; line-height: 1.4; }
    .ny-roles-sheet { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); padding: 8px 16px 16px; }
    .ny-roles-group { margin: 16px 0 8px; color: var(--ny-text-muted); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .ny-roles-head, .ny-roles-row { display: grid; grid-template-columns: minmax(0, 1.7fr) repeat(4, minmax(0, 1fr)); gap: 8px; align-items: center; }
    .ny-roles-head { min-height: 36px; color: var(--ny-text-muted); font-size: 12px; font-weight: 700; text-align: center; }
    .ny-roles-head span:first-child { text-align: left; }
    .ny-roles-row { min-height: 44px; border-top: 1px solid var(--ny-border); }
    .ny-roles-module { display: flex; align-items: center; gap: 8px; min-width: 0; font-size: 14px; }
    .ny-roles-mod { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: color-mix(in srgb, var(--c) 22%, var(--ny-surface)); color: var(--c); }
    .ny-roles-mark { display: grid; justify-items: center; gap: 2px; margin-inline: auto; color: var(--c); font-size: 12px; font-weight: 700; }
    .ny-roles-mark span { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
    .ny-roles-mark.is-off { color: var(--ny-text-muted); }
    .ny-roles-states { display: flex; flex-wrap: wrap; gap: 8px 16px; margin: 0; padding: 0; list-style: none; }
    .ny-roles-states li { display: flex; align-items: center; gap: 8px; min-height: 28px; font-size: 12px; font-weight: 700; }
    .ny-roles-dot { width: 12px; height: 12px; border-radius: 999px; background: var(--c); }
    @media (max-width: 800px) {
        .ny-roles-profiles { grid-template-columns: 1fr; }
        .ny-roles-head { display: none; }
        .ny-roles-row { grid-template-columns: minmax(0, 1fr); padding: 8px 0; }
        .ny-roles-marks { display: flex; flex-wrap: wrap; gap: 8px; }
        .ny-roles-mark { margin: 0; min-width: 44px; min-height: 44px; padding: 4px 8px; border-radius: 8px; background: color-mix(in srgb, var(--c) 14%, var(--ny-surface)); }
        .ny-roles-mark.is-off { background: var(--ny-surface-muted); }
        .ny-roles-mark span { position: static; width: auto; height: auto; overflow: visible; clip: auto; }
    }
</style>
<div class="ny-roles">
    <p class="ny-roles-lead">{{ __('company_portal.profiles.lead') }}</p>
    <div class="ny-roles-profiles">
        @foreach($profiles as $profile)
            <article class="ny-roles-profile">
                <span class="ny-roles-pill" style="--c: {{ $tones[$profile] }}"><x-ui.icon :name="$icons[$profile]" /></span>
                <h2>{{ __('company_portal.role.'.$labels[$profile]) }}</h2>
                <p>{{ __('company_portal.profiles.'.$profile.'_text') }}</p>
            </article>
        @endforeach
    </div>
    <section class="ny-roles-sheet">
        <div class="ny-roles-head">
            <span>{{ __('company_portal.menu.roles') }}</span>
            @foreach($profiles as $profile)
                <span>{{ __('company_portal.role.'.$labels[$profile]) }}</span>
            @endforeach
        </div>
        @foreach($modules as $module)
            @if($module['group'] !== $group)
                @php($group = $module['group'])
                <h3 class="ny-roles-group">{{ $group === null ? __('company_portal.menu.home') : __('company_portal.section.'.$group) }}</h3>
            @endif
            <div class="ny-roles-row">
                <div class="ny-roles-module">
                    <span class="ny-roles-mod" style="--c: {{ $tones['operator'] }}"><x-ui.icon :name="$module['key']" /></span>
                    <span>{{ __('company_portal.menu.'.$module['key']) }}</span>
                </div>
                <div class="ny-roles-marks">
                    @foreach($profiles as $profile)
                        @php($sees = in_array('*', $keys[$profile], true) || in_array($module['key'], $keys[$profile], true))
                        <span class="ny-roles-mark {{ $sees ? '' : 'is-off' }}" style="--c: {{ $tones[$profile] }}">
                            <x-ui.icon :name="$sees ? 'check' : 'minus'" />
                            <span>{{ __('company_portal.role.'.$labels[$profile]) }}: {{ $sees ? __('company_portal.profiles.yes') : __('company_portal.profiles.no') }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
    <p class="ny-roles-note">{{ __('company_portal.profiles.cash_note') }}</p>
    <section class="ny-roles-sheet">
        <h3 class="ny-roles-group">{{ __('company_portal.profiles.states') }}</h3>
        <ul class="ny-roles-states">
            <li><span class="ny-roles-dot" style="--c: var(--ny-tone-leaf)"></span>{{ __('company_portal.profiles.state_active') }}</li>
            <li><span class="ny-roles-dot" style="--c: var(--ny-tone-sun)"></span>{{ __('company_portal.profiles.state_invited') }}</li>
            <li><span class="ny-roles-dot" style="--c: var(--ny-tone-coral)"></span>{{ __('company_portal.profiles.state_suspended') }}</li>
        </ul>
        <p class="ny-roles-note">{{ __('company_portal.profiles.states_note') }}</p>
    </section>
</div>
@endsection
