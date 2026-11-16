@extends('layouts.company')
@section('title', __('agencies.lots_title'))
@section('page-title', __('agencies.lots_title'))
@section('content')
<div class="ny-agency">
 <header class="ny-agency-hero">
  <p class="ny-agency-kicker">{{ $agency->name }}</p>
  <h2>{{ __('agencies.lots_title') }}</h2>
  <p>{{ __('agencies.lots_lead') }}</p>
 </header>
 @if($lots->isEmpty())
  <section class="ny-agency-empty">
   <span class="ny-agency-pill is-lg" style="--c: var(--ny-tone-violet)"><x-ui.icon name="lots" /></span>
   <h2>{{ __('agencies.lots_empty') }}</h2>
  </section>
 @else
  @foreach($lots as $lot)
   @php($tally = $tallies[$lot->id])
   <article class="ny-agency-card is-agency">
    <h3><span class="ny-agency-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="lots" /></span>{{ \App\Support\DepartureSchedule::show($lot->departure->departure_at, 'D d M · H:i') }}</h3>
    <p>{{ $lot->departure->transportRoute->originPort->city }} → {{ $lot->departure->transportRoute->destinationPort->city }} · {{ $lot->departure->vessel->name }}</p>
    @if($tally['assigned'] > 0)
     <div class="ny-agency-bar">
      <i class="is-sold" style="width: {{ round($tally['sold'] / $tally['assigned'] * 100, 2) }}%"></i>
      <i class="is-free" style="width: {{ round($tally['free'] / $tally['assigned'] * 100, 2) }}%"></i>
      <i class="is-released" style="width: {{ round($tally['released'] / $tally['assigned'] * 100, 2) }}%"></i>
     </div>
    @endif
    <ul class="ny-agency-counts">
     <li>{{ __('agencies.sold') }} <b>{{ $tally['sold'] }}</b></li>
     <li>{{ __('agencies.free') }} <b>{{ $tally['free'] }}</b></li>
     <li>{{ __('agencies.released') }} <b>{{ $tally['released'] }}</b></li>
    </ul>
    <span @class(['ny-agency-badge', 'is-paid' => $lot->payment_status === 'paid', 'is-pending' => $lot->payment_status !== 'paid'])>
     <x-ui.icon :name="$lot->payment_status === 'paid' ? 'check' : 'cash'" />
     {{ $lot->payment_status === 'paid' ? __('agencies.paid') : __('agencies.pending') }}
    </span>
   </article>
  @endforeach
 @endif
</div>
@endsection
