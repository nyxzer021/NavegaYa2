@extends('layouts.company')
@section('title', __('agencies.title'))
@section('page-title', __('agencies.title'))
@section('content')
<div class="ny-agency">
 <header class="ny-agency-hero">
  <p class="ny-agency-kicker">{{ __('agencies.kicker') }}</p>
  <h2>{{ __('agencies.title') }}</h2>
  <p>{{ __('agencies.lead') }}</p>
 </header>
 @if(session('invite_url'))<p class="ny-agency-note">{{ __('agencies.invite_link') }} <a href="{{ session('invite_url') }}">{{ session('invite_url') }}</a></p>@endif
 @if($errors->any())<p class="ny-agency-note is-alert" role="alert">{{ $errors->first() }}</p>@endif

 @if($activeAgencies > 0 || $pendingLots > 0 || $freeSeats > 0)
 <section class="ny-agency-kpis">
  @if($activeAgencies > 0)
   <article class="ny-agency-kpi" style="--c: var(--ny-tone-violet)"><span class="ny-agency-pill"><x-ui.icon name="store" /></span><b>{{ $activeAgencies }}</b><span>{{ __('agencies.kpi_agencies') }}</span></article>
  @endif
  @if($pendingLots > 0)
   <article class="ny-agency-kpi" style="--c: var(--ny-tone-sun)"><span class="ny-agency-pill"><x-ui.icon name="cash" /></span><b>{{ $pendingLots }}</b><span>{{ __('agencies.kpi_pending') }}</span></article>
  @endif
  @if($freeSeats > 0)
   <article class="ny-agency-kpi" style="--c: var(--ny-tone-river)"><span class="ny-agency-pill"><x-ui.icon name="ticket" /></span><b>{{ $freeSeats }}</b><span>{{ __('agencies.kpi_free') }}</span></article>
  @endif
 </section>
 @endif

 <section class="ny-agency-card">
  <h3><span class="ny-agency-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="branches" /></span>{{ __('agencies.branches_title') }}</h3>
  <p>{{ __('agencies.branches_lead') }}</p>
  @if($sites->isEmpty())
   <div class="ny-agency-empty">
    <span class="ny-agency-pill is-lg" style="--c: var(--ny-tone-river)"><x-ui.icon name="branches" /></span>
    <p>{{ __('agencies.branch_empty') }}</p>
    <a class="ny-agency-main" href="{{ route('company.branches.index') }}">{{ __('agencies.open_sites') }}</a>
   </div>
  @else
   <div class="ny-agency-grid">
    @foreach($sites as $site)
     <article class="ny-agency-branch">
      <span class="ny-agency-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="branches" /></span>
      <h4>{{ $site->name }}</h4>
      <p>{{ $site->city }}@if($site->address) · {{ $site->address }}@endif</p>
      @if($sellers->isNotEmpty())
       <form method="POST" action="{{ route('company.branches.assign', $site) }}">
        @csrf
        <label>{{ __('agencies.assign') }}
         <select name="user_id" required>
          @foreach($sellers as $seller)
           <option value="{{ $seller->id }}" @selected($seller->organization_site_id === $site->id)>{{ $seller->name }}</option>
          @endforeach
         </select>
        </label>
        <button class="ny-agency-soft" type="submit">{{ __('agencies.assign_save') }}</button>
       </form>
      @else
       <p>{{ __('agencies.no_sellers') }}</p>
      @endif
     </article>
    @endforeach
   </div>
  @endif
 </section>

 @if($agencies->isEmpty())
  <section class="ny-agency-empty">
   <span class="ny-agency-pill is-lg" style="--c: var(--ny-tone-violet)"><x-ui.icon name="store" /></span>
   <h2>{{ __('agencies.empty_title') }}</h2>
   <p>{{ __('agencies.empty_body') }}</p>
  </section>
 @endif

 <section class="ny-agency-card">
  <h3><span class="ny-agency-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="store" /></span>{{ __('agencies.create_title') }}</h3>
  <form method="POST" action="{{ route('company.agencies.store') }}" class="ny-agency-form">
   @csrf
   <label>{{ __('agencies.name') }}<input name="name" value="{{ old('name') }}" required></label>
   <label>{{ __('agencies.ruc') }}<input name="ruc" value="{{ old('ruc') }}" inputmode="numeric" required></label>
   <label>{{ __('agencies.contact') }}<input name="contact_name" value="{{ old('contact_name') }}" required></label>
   <label>{{ __('agencies.phone') }}<input name="phone" value="{{ old('phone') }}" required></label>
   <label>{{ __('agencies.surcharge') }}<input type="number" name="max_surcharge_percent" min="0" max="100" step="0.01" value="{{ old('max_surcharge_percent', 0) }}"></label>
   <button class="ny-agency-main" type="submit">{{ __('agencies.save') }}</button>
  </form>
 </section>

 @foreach($agencies as $agency)
  <article class="ny-agency-card is-agency">
   <header class="ny-agency-head">
    <span class="ny-agency-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="store" /></span>
    <div>
     <h3>{{ $agency->name }}</h3>
     <p>RUC {{ $agency->ruc }} · {{ $agency->contact_name }} · {{ $agency->phone }}</p>
     <p>{{ __('agencies.surcharge_label') }} {{ number_format((float) $agency->max_surcharge_percent, 2) }}%</p>
     <p>{{ __('agencies.seller') }}: {{ $agency->sellers->first()->name ?? __('agencies.no_seller') }}</p>
    </div>
    <span @class(['ny-agency-badge', 'is-paid' => $agency->status === 'active', 'is-pending' => $agency->status !== 'active'])>
     <x-ui.icon :name="$agency->status === 'active' ? 'check' : 'close'" />
     {{ $agency->status === 'active' ? __('agencies.active') : __('agencies.suspended') }}
    </span>
   </header>
   @php($platformLocked = $agency->isSuspendedByPlatform())
   @if($agency->suspension_reason)<p class="ny-agency-note is-alert">{{ $agency->suspension_reason }}</p>@endif
   @if($platformLocked)<p class="ny-agency-note is-alert">{{ __('agencies.platform_locked') }}</p>@endif

   <form method="POST" action="{{ route('company.agencies.update', $agency) }}" class="ny-agency-form">
    @csrf @method('PATCH')
    <label>{{ __('agencies.status') }}
     <select name="status">
      <option value="active" @selected($agency->status === 'active') @disabled($platformLocked)>{{ __('agencies.active') }}</option>
      <option value="suspended" @selected($agency->status === 'suspended')>{{ __('agencies.suspended') }}</option>
     </select>
    </label>
    <label>{{ __('agencies.surcharge') }}<input type="number" name="max_surcharge_percent" min="0" max="100" step="0.01" value="{{ $agency->max_surcharge_percent }}" required></label>
    <label>{{ __('agencies.reason') }}<input name="suspension_reason" value="{{ $agency->suspension_reason }}"></label>
    <button class="ny-agency-soft" type="submit">{{ __('agencies.save_status') }}</button>
   </form>

   <form method="POST" action="{{ route('company.agencies.invite', $agency) }}" class="ny-agency-form">
    @csrf
    <label>{{ __('agencies.invite_email') }}<input type="email" name="email" required></label>
    <button class="ny-agency-soft" type="submit">{{ __('agencies.invite_send') }}</button>
   </form>

   @foreach($agency->lots as $lot)
    @php($tally = $tallies[$lot->id])
    <section class="ny-agency-lot">
     <h4>{{ \App\Support\DepartureSchedule::show($lot->departure->departure_at, 'D d M · H:i') }} · {{ $lot->departure->transportRoute->originPort->city }} → {{ $lot->departure->transportRoute->destinationPort->city }}</h4>
     <p>{{ __('agencies.lot_total') }} S/ {{ $tally['total'] }} · {{ __('agencies.unit_price') }} S/ {{ $lot->unit_price }}</p>
     @if($tally['assigned'] > 0)
      <div class="ny-agency-bar" role="img" aria-label="{{ __('agencies.sold') }} {{ $tally['sold'] }}, {{ __('agencies.free') }} {{ $tally['free'] }}, {{ __('agencies.released') }} {{ $tally['released'] }}">
       <i class="is-sold" style="width: {{ round($tally['sold'] / $tally['assigned'] * 100, 2) }}%"></i>
       <i class="is-free" style="width: {{ round($tally['free'] / $tally['assigned'] * 100, 2) }}%"></i>
       <i class="is-released" style="width: {{ round($tally['released'] / $tally['assigned'] * 100, 2) }}%"></i>
      </div>
     @endif
     <ul class="ny-agency-counts">
      <li><span class="ny-agency-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="ticket" /></span>{{ __('agencies.sold') }} <b>{{ $tally['sold'] }}</b></li>
      <li><span class="ny-agency-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="lots" /></span>{{ __('agencies.free') }} <b>{{ $tally['free'] }}</b></li>
      <li><span class="ny-agency-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="check" /></span>{{ __('agencies.released') }} <b>{{ $tally['released'] }}</b></li>
     </ul>
     <form method="POST" action="{{ route('company.agencies.lots.pay', $lot) }}">
      @csrf @method('PATCH')
      <input type="hidden" name="payment_status" value="{{ $lot->payment_status === 'paid' ? 'pending' : 'paid' }}">
      <button @class(['ny-agency-badge', 'is-paid' => $lot->payment_status === 'paid', 'is-pending' => $lot->payment_status !== 'paid']) type="submit">
       <x-ui.icon :name="$lot->payment_status === 'paid' ? 'check' : 'cash'" />
       {{ $lot->payment_status === 'paid' ? __('agencies.paid') : __('agencies.pending') }}
      </button>
     </form>
     @if($lot->holds->isNotEmpty())
      <form method="POST" action="{{ route('company.agencies.lots.release', $lot) }}" class="ny-agency-form">
       @csrf
       @foreach($lot->holds as $hold)
        <label class="ny-agency-check"><input type="checkbox" name="seats[]" value="{{ $hold->vessel_seat_id }}"> {{ $hold->seat->code }}</label>
       @endforeach
       <label>{{ __('agencies.release_note') }}<input name="note" required></label>
       <button class="ny-agency-soft" type="submit">{{ __('agencies.release_save') }}</button>
      </form>
     @endif
    </section>
   @endforeach

   <h4>{{ __('agencies.lot_title') }}</h4>
   <form method="GET" class="ny-agency-form">
    <input type="hidden" name="agencia" value="{{ $agency->id }}">
    <label>{{ __('agencies.departure') }}
     <select name="salida" onchange="this.form.submit()">
      <option value="">{{ __('agencies.choose_departure') }}</option>
      @foreach($departures as $departure)
       <option value="{{ $departure->id }}" @selected($picked?->id === $departure->id && $focus === $agency->id)>{{ \App\Support\DepartureSchedule::show($departure->departure_at, 'D d M · H:i') }} · {{ $departure->transportRoute->originPort->city }} → {{ $departure->transportRoute->destinationPort->city }}</option>
      @endforeach
     </select>
    </label>
   </form>
   @if($picked && $focus === $agency->id)
    <form method="POST" action="{{ route('company.agencies.lots.store', $agency) }}" class="ny-agency-form">
     @csrf
     <input type="hidden" name="departure_id" value="{{ $picked->id }}">
     <p>{{ __('agencies.seat_help') }}</p>
     @include('components.vessel-deck-styles')
     @foreach($decks as $deck)
      <h4>{{ $deck['deck'] === 'principal' ? __('agencies.deck_main') : ($deck['deck'] === 'superior' ? __('agencies.deck_upper') : __('agencies.deck_other', ['deck' => $deck['deck']])) }}</h4>
      <x-vessel-hull :columns="$deck['plan']['columns']">
       @foreach($deck['plan']['grid'] as $line)
        <div class="ny-row">
         @foreach($line as $cell)
          @if($seat = $cell['seat'])
           <label class="ny-seat-form"><input class="peer sr-only" type="checkbox" name="seats[]" value="{{ $seat->id }}" @disabled(in_array($seat->id, $busyIds, true))><span @class(['ny-seat', 'is-taken' => in_array($seat->id, $busyIds, true)])>{{ $seat->code }}</span></label>
          @else
           <x-vessel-deco :type="$cell['type']" />
          @endif
         @endforeach
        </div>
       @endforeach
      </x-vessel-hull>
     @endforeach
     @if($hammocks > 0)
      <label>{{ __('agencies.hammocks') }}<input type="number" name="hammocks" min="0" max="{{ $hammocks }}" value="0"></label>
     @endif
     <label>{{ __('agencies.unit_price') }}<input type="number" name="unit_price" min="0" step="0.01" required></label>
     <label>{{ __('agencies.payment') }}
      <select name="payment_status">
       <option value="pending">{{ __('agencies.pending') }}</option>
       <option value="paid">{{ __('agencies.paid') }}</option>
      </select>
     </label>
     <label>{{ __('agencies.note') }}<input name="note"></label>
     <button class="ny-agency-main" type="submit">{{ __('agencies.assign_lot') }}</button>
    </form>
   @endif
  </article>
 @endforeach
</div>
@endsection
