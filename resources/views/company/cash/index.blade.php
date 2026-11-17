@php($money = fn (float $amount): string => 'S/ '.number_format($amount, 2))
@extends('layouts.company')
@section('title', __('cash.title'))
@section('page-title', __('cash.title'))
@section('content')
<div class="ny-cash">
 <header class="ny-cash-hero">
  <p>{{ __('cash.lead') }}</p>
  <a class="ny-cash-btn is-quiet" href="{{ route('company.cash-close.export', ['fecha' => $day]) }}">{{ __('cash.export') }}</a>
 </header>

 @if(session('success'))<p class="ny-cash-note" role="status">{{ session('success') }}</p>@endif
 @if($errors->any())<p class="ny-cash-note is-alert" role="alert">{{ $errors->first() }}</p>@endif

 @if($admin)
 <form class="ny-cash-filters" method="GET" action="{{ route('company.cash-close.index') }}">
  <label>{{ __('cash.date') }}<input type="date" name="fecha" value="{{ $day }}"></label>
  <label>{{ __('cash.site') }}
   <select name="sede">
    <option value="">{{ __('cash.all') }}</option>
    @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('sede') === (string) $site->id)>{{ $site->name }}</option>@endforeach
   </select>
  </label>
  <label>{{ __('cash.seller') }}
   <select name="vendedor">
    <option value="">{{ __('cash.all') }}</option>
    @foreach($sellers as $seller)<option value="{{ $seller->id }}" @selected((string) request('vendedor') === (string) $seller->id)>{{ $seller->name }}</option>@endforeach
   </select>
  </label>
  <button type="submit">{{ __('cash.filter') }}</button>
 </form>
 @endif

 @if($admin && $daily)
 <section class="ny-cash-card">
  <h2>{{ __('cash.daily') }}</h2>
  <p>{{ __('cash.daily_body') }}</p>
  @include('company.cash.summary', ['summary' => $daily])
  <p>{{ __('cash.counted') }} <b>{{ $money($daily['counted']) }}</b> · {{ __('cash.difference') }} <b>{{ $money($daily['difference']) }}</b></p>
 </section>
 @endif

 @if($canOpen)
 <section class="ny-cash-card">
  <h2><span class="ny-cash-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="cash" /></span>{{ __('cash.open_title') }}</h2>
  <p>{{ __('cash.open_body') }}</p>
  <form class="ny-cash-open" method="POST" action="{{ route('company.cash-close.open') }}">
   @csrf
   @if($availableSites->isNotEmpty())
    <label>{{ __('cash.site') }}
     <select name="site_id" required>
      @foreach($availableSites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach
     </select>
    </label>
   @endif
   <label>{{ __('cash.float') }}
    <input name="opening_float" type="number" min="0" step="0.01" inputmode="decimal" required value="{{ old('opening_float') }}">
   </label>
   <button type="submit">{{ __('cash.open') }}</button>
  </form>
 </section>
 @endif

 @foreach($openSessions as $session)
  @php($summary = $summaries[$session->id])
  <section class="ny-cash-card is-open">
   <h2><span class="ny-cash-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="cash" /></span>{{ $session->user?->name }}</h2>
   <p>{{ $session->site?->name ?: __('cash.no_site') }} · {{ __('cash.opened_at') }} {{ \App\Support\DepartureSchedule::show($session->opened_at) }} · {{ __('cash.float') }} {{ $money((float) $session->opening_float) }}</p>
   @include('company.cash.summary', ['summary' => $summary])
   <form class="ny-cash-open" method="POST" action="{{ route('company.cash-close.close', $session) }}">
    @csrf
    <label>{{ __('cash.counted') }}
     <input name="counted_cash" type="number" min="0" step="0.01" inputmode="decimal" required value="{{ old('counted_cash') }}">
    </label>
    <label>{{ __('cash.note') }}
     <input name="closing_note" maxlength="255" value="{{ old('closing_note') }}" placeholder="{{ __('cash.note_hint') }}">
    </label>
    <button type="submit">{{ __('cash.close') }}</button>
   </form>
  </section>
 @endforeach

 <section class="ny-cash-card">
  <h2><span class="ny-cash-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="parcels" /></span>{{ __('cash.receivable') }}</h2>
  <p>{{ __('cash.receivable_body') }}</p>
  @if($receivable === [])
   <p class="ny-cash-muted">{{ __('cash.receivable_empty') }}</p>
  @else
   <ul class="ny-cash-list">
    @foreach($receivable as $row)
     <li>
      <strong>{{ $row['code'] }}</strong>
      <span>{{ $row['site'] }} · {{ $row['departure'] }}</span>
      <b>{{ $money($row['amount']) }}</b>
      <em>{{ $row['days'] > 0 ? __('cash.age_days', ['count' => $row['days']]) : __('cash.age_today') }}</em>
     </li>
    @endforeach
   </ul>
  @endif
 </section>

 <section class="ny-cash-card">
  <h2>{{ __('cash.closed_list') }}</h2>
  @if($closedSessions->isEmpty())
   <p class="ny-cash-muted">{{ __('cash.empty_day') }}</p>
  @else
   <ul class="ny-cash-list">
    @foreach($closedSessions as $session)
     @php($cents = (int) round(((float) $session->difference) * 100))
     <li>
      <strong>{{ $session->user?->name }}</strong>
      <span>{{ $session->site?->name ?: __('cash.no_site') }} · {{ \App\Support\DepartureSchedule::show($session->opened_at) }}</span>
      <b class="{{ $cents === 0 ? 'is-exact' : ($cents > 0 ? 'is-over' : 'is-short') }}">{{ $cents === 0 ? __('cash.exact') : (($cents > 0 ? __('cash.over') : __('cash.short')).' '.$money((float) $session->difference)) }}</b>
      @if($session->closing_note)<span>{{ $session->closing_note }}</span>@endif
      <a href="{{ route('company.cash-close.print', $session) }}">{{ __('cash.print') }}</a>
     </li>
    @endforeach
   </ul>
  @endif
 </section>

 @if($admin && $loose->isNotEmpty())
 <section class="ny-cash-card">
  <h2>{{ __('cash.loose') }}</h2>
  <p>{{ __('cash.loose_body') }}</p>
  <ul class="ny-cash-list">
   @foreach($loose as $payment)
    <li>
     <strong>{{ $payment->collector?->name ?: $payment->reservation?->soldBy?->name ?: __('cash.unknown_seller') }}</strong>
     <span>{{ $payment->site?->name ?: __('cash.no_site') }} · {{ $payment->purpose === 'cargo' ? __('cash.cargo') : __('cash.ticket') }}</span>
     <b>{{ $money((float) $payment->amount) }}</b>
    </li>
   @endforeach
  </ul>
 </section>
 @endif
</div>
@endsection
