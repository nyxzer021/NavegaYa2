@extends('layouts.admin')
@section('title', __('admin_portal.menu.agencies'))
@section('content')
<div class="ny-agency">
 <header class="ny-agency-hero">
  <h2>{{ __('admin_portal.menu.agencies') }}</h2>
  <p>{{ __('agencies.admin_lead') }}</p>
 </header>
 @if(session('success'))<p class="ny-agency-note" role="status">{{ session('success') }}</p>@endif
 @if($agencies->isEmpty())
  <section class="ny-agency-empty">
   <span class="ny-agency-pill is-lg" style="--c: var(--ny-tone-violet)"><x-ui.icon name="store" /></span>
   <h2>{{ __('agencies.admin_empty') }}</h2>
  </section>
 @else
  @foreach($agencies as $agency)
   <article class="ny-agency-card is-agency">
    <header class="ny-agency-head">
     <span class="ny-agency-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="store" /></span>
     <div>
      <h3>{{ $agency->name }}</h3>
      <p>{{ $agency->organization->commercial_name ?: $agency->organization->legal_name }} · RUC {{ $agency->ruc }}</p>
     </div>
     <span @class(['ny-agency-badge', 'is-paid' => $agency->status === 'active', 'is-pending' => $agency->status !== 'active'])>
      <x-ui.icon :name="$agency->status === 'active' ? 'check' : 'close'" />
      {{ $agency->status === 'active' ? __('agencies.active') : __('agencies.suspended') }}
     </span>
    </header>
    @if($agency->status === 'active')
     <form method="POST" action="{{ route('admin.agencies.suspend', $agency) }}" class="ny-agency-form">
      @csrf
      <label>{{ __('agencies.reason') }}<input name="suspension_reason" required></label>
      <button class="ny-agency-main" type="submit">{{ __('agencies.suspend') }}</button>
     </form>
    @else
     @if($agency->suspension_reason)<p class="ny-agency-note is-alert">{{ $agency->suspension_reason }}</p>@endif
     <form method="POST" action="{{ route('admin.agencies.reactivate', $agency) }}" class="ny-agency-form">
      @csrf
      <button class="ny-agency-main" type="submit">{{ __('agencies.reactivate') }}</button>
     </form>
    @endif
   </article>
  @endforeach
 @endif
</div>
@endsection
