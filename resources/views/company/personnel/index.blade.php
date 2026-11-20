@extends('layouts.company')
@section('title', __('company_portal.menu.team'))
@section('page-title', __('company_portal.menu.team'))
@section('content')
@php
    $tone = [
        'admin' => 'var(--ny-tone-violet)',
        'seller' => 'var(--ny-tone-river)',
        'operator' => 'var(--ny-tone-leaf)',
        'finance' => 'var(--ny-tone-sun)',
    ];
    $roleLabel = fn (string $profile): string => __('company_portal.role.'.($profile === 'seller' ? 'counter' : $profile));
    $company = $organization->commercial_name ?: $organization->legal_name;
    $inviteUrl = session('invite_url');
    $whatsapp = $inviteUrl
        ? 'https://wa.me/?text='.rawurlencode(__('staff.whatsapp_text', ['name' => session('invite_name'), 'company' => $company, 'url' => $inviteUrl]))
        : null;
@endphp
<style>
    .ny-staff { display: grid; gap: 16px; }
    .ny-staff-lead { margin: 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-staff-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .ny-staff-kpi, .ny-staff-card, .ny-staff-row { border: 1px solid var(--ny-border); border-radius: 12px; background: var(--ny-surface); }
    .ny-staff-kpi { display: flex; align-items: center; gap: 12px; padding: 16px; }
    .ny-staff-kpi b { display: block; font-size: 28px; line-height: 1; }
    .ny-staff-kpi span { color: var(--ny-text-muted); font-size: 12px; font-weight: 700; }
    .ny-staff-pill { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 8px; background: var(--c); color: var(--ny-surface); }
    .ny-staff-pill svg { width: 16px; height: 16px; }
    .ny-staff-card, .ny-staff-row { padding: 16px; }
    .ny-staff-card h2, .ny-staff-row h3 { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 16px; }
    .ny-staff-form, .ny-staff-edit { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; align-items: end; margin-top: 12px; }
    .ny-staff label { display: grid; gap: 4px; color: var(--ny-text); font-size: 12px; font-weight: 700; }
    .ny-staff input, .ny-staff select { height: 40px; border: 1px solid var(--ny-border); border-radius: 8px; background: var(--ny-surface); padding: 0 12px; color: var(--ny-text); font-size: 14px; }
    .ny-staff-hint, .ny-staff-meta { margin: 8px 0 0; color: var(--ny-text-muted); font-size: 12px; }
    .ny-staff-btn, .ny-staff-quiet, .ny-staff-danger { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .ny-staff-btn { border: 0; background: var(--ny-primary-deep); color: var(--ny-text-on-dark); }
    .ny-staff-quiet { border: 1px solid var(--ny-border); background: var(--ny-surface); color: var(--ny-text); }
    .ny-staff-danger { border: 1px solid color-mix(in srgb, var(--ny-tone-coral) 45%, var(--ny-border)); background: var(--ny-surface); color: var(--ny-tone-coral-deep); }
    .ny-staff-main { min-height: 40px; }
    .ny-staff-note { margin: 0; border-radius: 12px; background: color-mix(in srgb, var(--ny-tone-leaf) 14%, var(--ny-surface)); padding: 12px; color: var(--ny-tone-leaf-deep); font-size: 14px; }
    .ny-staff-note.is-alert { background: color-mix(in srgb, var(--ny-tone-sun) 24%, var(--ny-surface)); color: var(--ny-tone-sun-deep); }
    .ny-staff-link { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 8px; }
    .ny-staff-link input { min-width: 0; flex: 1; }
    .ny-staff-badge { display: inline-flex; align-items: center; min-height: 24px; border-radius: 999px; background: color-mix(in srgb, var(--c) 18%, var(--ny-surface)); padding: 0 8px; color: var(--c); font-size: 12px; font-weight: 700; }
    .ny-staff-row { display: grid; gap: 8px; }
    .ny-staff-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
    .ny-staff-person { min-width: 0; }
    .ny-staff-person p { margin: 2px 0 0; color: var(--ny-text-muted); font-size: 14px; }
    .ny-staff-menu { margin-top: 4px; }
    .ny-staff-menu summary { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; min-height: 36px; border: 1px solid var(--ny-border); border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 700; cursor: pointer; list-style: none; }
    .ny-staff-menu summary::-webkit-details-marker { display: none; }
    @media (max-width: 800px) {
        .ny-staff-kpis, .ny-staff-form, .ny-staff-edit { grid-template-columns: 1fr; }
        .ny-staff-btn, .ny-staff-quiet, .ny-staff-danger, .ny-staff-menu summary { min-height: 44px; }
    }
</style>
<div class="ny-staff">
    <p class="ny-staff-lead">{{ __('staff.lead') }}</p>
    <section class="ny-staff-kpis">
        <article class="ny-staff-kpi"><span class="ny-staff-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="check" /></span><div><b>{{ $counts['active'] }}</b><span>{{ __('staff.active') }}</span></div></article>
        <article class="ny-staff-kpi"><span class="ny-staff-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="messages" /></span><div><b>{{ $counts['invited'] }}</b><span>{{ __('staff.invited_count') }}</span></div></article>
        <article class="ny-staff-kpi"><span class="ny-staff-pill" style="--c: var(--ny-tone-coral)"><x-ui.icon name="lock" /></span><div><b>{{ $counts['suspended'] }}</b><span>{{ __('staff.suspended_count') }}</span></div></article>
    </section>

    @if(session('success'))<p class="ny-staff-note" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="ny-staff-note is-alert" role="alert">{{ $errors->first() }}</p>@endif
    @if($inviteUrl)
        <section class="ny-staff-card">
            <h2>{{ __('staff.link') }}</h2>
            <div class="ny-staff-link">
                <input id="staff-invite-url" readonly value="{{ $inviteUrl }}">
                <button class="ny-staff-quiet" type="button" id="staff-copy">{{ __('staff.copy') }}</button>
                <a class="ny-staff-btn" target="_blank" rel="noopener" href="{{ $whatsapp }}">{{ __('staff.whatsapp') }}</a>
            </div>
        </section>
    @endif

    <section class="ny-staff-card">
        <h2><span class="ny-staff-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="team" /></span> {{ __('staff.invite') }}</h2>
        <form class="ny-staff-form" method="POST" action="{{ route('company.staff.store') }}">
            @csrf
            <label>{{ __('staff.name') }}<input name="name" value="{{ old('name') }}" required maxlength="120"></label>
            <label>{{ __('staff.email') }}<input name="email" type="email" value="{{ old('email') }}" required maxlength="150"></label>
            <label>{{ __('staff.profile') }}
                <select name="profile" required>
                    @foreach($profiles as $profile)
                        <option value="{{ $profile }}" @selected(old('profile', 'seller') === $profile)>{{ $roleLabel($profile) }}</option>
                    @endforeach
                </select>
            </label>
            @if($sites->isNotEmpty())
                <label>{{ __('staff.site') }}
                    <select name="site_id">
                        <option value="">{{ __('staff.site_optional') }}</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="ny-staff-btn ny-staff-main" type="submit">{{ __('staff.send') }}</button>
        </form>
        @if($sites->isNotEmpty())<p class="ny-staff-hint">{{ __('staff.site_hint') }}</p>@endif
    </section>

    @foreach($invitations as $invitation)
        @php($expired = $invitation->expires_at->isPast())
        <article class="ny-staff-row">
            <div class="ny-staff-top">
                <div class="ny-staff-person">
                    <h3>{{ $invitation->name }}</h3>
                    <p>{{ $invitation->email }}</p>
                </div>
                <div>
                    <span class="ny-staff-badge" style="--c: {{ $tone[$invitation->profile] }}">{{ $roleLabel($invitation->profile) }}</span>
                    <span class="ny-staff-badge" style="--c: var(--ny-tone-sun)">{{ __('staff.state_invited') }}</span>
                </div>
            </div>
            <p class="ny-staff-meta">{{ $invitation->site?->name ?: __('staff.no_site') }} · {{ $expired ? __('staff.expired') : __('staff.expires', ['date' => \App\Support\DepartureSchedule::show($invitation->expires_at)]) }}</p>
            <div class="ny-staff-link">
                <input readonly value="{{ route('staff.invitation', $invitation->token) }}">
                <a class="ny-staff-quiet" target="_blank" rel="noopener" href="{{ 'https://wa.me/?text='.rawurlencode(__('staff.whatsapp_text', ['name' => $invitation->name, 'company' => $company, 'url' => route('staff.invitation', $invitation->token)])) }}">{{ __('staff.whatsapp') }}</a>
            </div>
            <details class="ny-staff-menu">
                <summary>{{ __('staff.actions') }}</summary>
                <form class="ny-staff-edit" method="POST" action="{{ route('company.staff.invitations.update', $invitation) }}">
                    @csrf @method('PATCH')
                    <label>{{ __('staff.profile') }}
                        <select name="profile">
                            @foreach($profiles as $profile)
                                <option value="{{ $profile }}" @selected($invitation->profile === $profile)>{{ $roleLabel($profile) }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($sites->isNotEmpty())
                        <label>{{ __('staff.site') }}
                            <select name="site_id">
                                <option value="">{{ __('staff.site_optional') }}</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" @selected((int) $invitation->organization_site_id === (int) $site->id)>{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <button class="ny-staff-quiet" type="submit">{{ __('staff.save') }}</button>
                </form>
                <div class="ny-staff-link">
                    <form method="POST" action="{{ route('company.staff.invitations.resend', $invitation) }}">@csrf<button class="ny-staff-quiet" type="submit">{{ __('staff.resend') }}</button></form>
                    <form method="POST" action="{{ route('company.staff.invitations.cancel', $invitation) }}">@csrf @method('DELETE')<button class="ny-staff-danger" type="submit">{{ __('staff.cancel') }}</button></form>
                </div>
            </details>
        </article>
    @endforeach

    @foreach($members as $member)
        @php($profile = \App\Support\CompanyTeam::memberProfile($member, $organization->id) ?? 'seller')
        @php($active = $member->organizations->first()?->pivot->status === 'active')
        @php($siteName = $sites->firstWhere('id', $member->organization_site_id)?->name)
        @php($drawer = $openCash->get($member->id))
        <article class="ny-staff-row">
            <div class="ny-staff-top">
                <div class="ny-staff-person">
                    <h3>{{ $member->name }}</h3>
                    <p>{{ $member->email }}</p>
                </div>
                <div>
                    <span class="ny-staff-badge" style="--c: {{ $tone[$profile] ?? 'var(--ny-tone-river)' }}">{{ $roleLabel($profile) }}</span>
                    <span class="ny-staff-badge" style="--c: {{ $active ? 'var(--ny-tone-leaf)' : 'var(--ny-tone-coral)' }}">{{ $active ? __('staff.state_active') : __('staff.state_suspended') }}</span>
                </div>
            </div>
            <p class="ny-staff-meta">{{ $siteName ?: __('staff.no_site') }} · {{ __('staff.last_login') }}: {{ $member->last_login_at ? \App\Support\DepartureSchedule::show($member->last_login_at) : __('staff.never') }}</p>
            @if($drawer)
                <p class="ny-staff-note is-alert">{{ __('staff.open_cash', ['name' => $member->name, 'site' => $drawer->site?->name ?: __('staff.no_site'), 'when' => \App\Support\DepartureSchedule::show($drawer->opened_at)]) }}</p>
            @endif
            @if((int) session('open_cash_user') === (int) $member->id)
                <form method="POST" action="{{ route('company.staff.toggle', $member) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="confirm" value="1">
                    <button class="ny-staff-danger" type="submit">{{ __('staff.suspend_confirm') }}</button>
                </form>
            @endif
            <details class="ny-staff-menu">
                <summary>{{ __('staff.actions') }}</summary>
                <form class="ny-staff-edit" method="POST" action="{{ route('company.staff.update', $member) }}">
                    @csrf @method('PATCH')
                    <label>{{ __('staff.profile') }}
                        <select name="profile">
                            @foreach($profiles as $option)
                                <option value="{{ $option }}" @selected($profile === $option)>{{ $roleLabel($option) }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($sites->isNotEmpty())
                        <label>{{ __('staff.site') }}
                            <select name="site_id">
                                <option value="">{{ __('staff.site_optional') }}</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" @selected((int) $member->organization_site_id === (int) $site->id)>{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <button class="ny-staff-quiet" type="submit">{{ __('staff.save') }}</button>
                </form>
                <form method="POST" action="{{ route('company.staff.toggle', $member) }}">
                    @csrf @method('PATCH')
                    @if($drawer)<input type="hidden" name="confirm" value="1">@endif
                    <button class="{{ $active ? 'ny-staff-danger' : 'ny-staff-quiet' }}" type="submit">{{ $active ? ($drawer ? __('staff.suspend_confirm') : __('staff.suspend')) : __('staff.reactivate') }}</button>
                </form>
            </details>
        </article>
    @endforeach
</div>
<script>
    document.getElementById('staff-copy')?.addEventListener('click', function () {
        var input = document.getElementById('staff-invite-url');
        input.select();
        navigator.clipboard.writeText(input.value);
        this.textContent = @json(__('staff.copied'));
    });
</script>
@endsection
