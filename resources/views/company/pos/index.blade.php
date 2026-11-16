@extends('layouts.company')
@section('title', __('pos.title'))
@section('page-title', __('pos.title'))
@section('content')
<div class="ny-pos">
 <header class="ny-pos-hero">
  <p class="ny-pos-kicker">{{ __('pos.kicker') }}</p>
  <h2>{{ __('pos.headline') }}</h2>
  <p>{{ __('pos.lead') }}</p>
 </header>

 @if(session('success'))<p class="ny-pos-note" role="status">{{ session('success') }}</p>@endif
 @if($errors->any())<p class="ny-pos-note is-alert" role="alert">{{ $errors->first() }}</p>@endif

 @if($todayCount > 0 || $freeSeats > 0 || ($prices['seat']['adult'] ?? null))
 <section class="ny-pos-kpis" aria-label="{{ __('pos.title') }}">
  @if($todayCount > 0)
   <article class="ny-pos-kpi" style="--c: var(--ny-tone-river)"><span class="ny-pos-pill"><x-ui.icon name="departures" /></span><b>{{ $todayCount }}</b><span>{{ __('pos.today') }}</span></article>
  @endif
  @if($freeSeats > 0)
   <article class="ny-pos-kpi" style="--c: var(--ny-tone-leaf)"><span class="ny-pos-pill"><x-ui.icon name="ticket" /></span><b>{{ $freeSeats }}</b><span>{{ __('pos.free') }}</span></article>
  @endif
  @if($prices['seat']['adult'] ?? null)
   <article class="ny-pos-kpi" style="--c: var(--ny-tone-sun)"><span class="ny-pos-pill"><x-ui.icon name="cash" /></span><b>S/ {{ number_format($prices['seat']['adult'], 2) }}</b><span>{{ __('pos.fare') }}</span></article>
  @endif
 </section>
 @endif

 @if($agencySuspended ?? false)
  <section class="ny-pos-empty">
   <span class="ny-pos-pill is-lg" style="--c: var(--ny-tone-coral)"><x-ui.icon name="store" /></span>
   <h2>{{ __('agencies.suspended_sale') }}</h2>
   <p>{{ __('agencies.suspended_sale_body') }}</p>
  </section>
 @elseif($departures->isEmpty())
  <section class="ny-pos-empty">
   <span class="ny-pos-pill is-lg" style="--c: var(--ny-tone-river)"><x-ui.icon name="fleet" /></span>
   <h2>{{ __('pos.empty_title') }}</h2>
   <p>{{ __('pos.empty_body') }}</p>
   @if(auth()->user()?->isCompanyAdmin())<a class="ny-pos-main" href="{{ route('company.departures.create') }}">{{ __('pos.schedule') }}</a>@endif
   @if($later->isNotEmpty())<a class="ny-pos-soft" href="{{ route('company.pos.index', ['horizonte' => 'semana']) }}">{{ __('pos.later') }}</a>@endif
  </section>
 @else
  @if(! ($agency ?? null))
  <div class="ny-pos-switch">
   <a href="{{ route('company.pos.index', array_filter(['departure' => $selectedDeparture?->id])) }}" @class(['is-on' => ! $week])>{{ __('pos.only_today') }}</a>
   <a href="{{ route('company.pos.index', array_filter(['horizonte' => 'semana', 'departure' => $selectedDeparture?->id])) }}" @class(['is-on' => $week])>{{ __('pos.this_week') }}</a>
  </div>
  @endif
  <form method="GET" class="ny-pos-card">
   @if($week)<input type="hidden" name="horizonte" value="semana">@endif
   <label>{{ __('pos.departure') }}
    <select name="departure" onchange="this.form.submit()">
     @foreach($departures as $departure)
      <option value="{{ $departure->id }}" @selected($selectedDeparture?->id === $departure->id)>{{ \App\Support\DepartureSchedule::show($departure->departure_at, 'D d M · H:i') }} · {{ $departure->transportRoute->originPort->city }} → {{ $departure->transportRoute->destinationPort->city }} · {{ $departure->vessel->name }}</option>
     @endforeach
    </select>
   </label>
  </form>

  <form method="POST" action="{{ route('company.pos.store') }}" class="ny-pos-grid" x-data="posDesk({{ \Illuminate\Support\Js::from(['prices' => $prices, 'discounts' => $discounts->map(fn ($discount) => ['id' => $discount->id, 'kind' => $discount->kind, 'value' => (float) $discount->value])->values(), 'cargo' => $allowance['cargo'], 'agency' => ($agency ?? null) !== null, 'percent' => (float) (($agency ?? null)?->max_surcharge_percent ?? 0), 'departure' => $selectedDeparture?->id, 'parcels' => route('company.parcels.index')]) }})">
   @csrf
   <input type="hidden" name="departure_id" value="{{ $selectedDeparture->id }}">
   <ol class="ny-pos-steps">
    <li class="is-done"><span><x-ui.icon name="check" /></span>{{ __('pos.step_departure') }}</li>
    <li :class="seat ? 'is-done' : 'is-now'"><span x-show="!seat">2</span><span x-cloak x-show="seat"><x-ui.icon name="check" /></span>{{ __('pos.step_seat') }}</li>
    <li :class="named ? 'is-done' : (seat ? 'is-now' : '')"><span x-show="!named">3</span><span x-cloak x-show="named"><x-ui.icon name="check" /></span>{{ __('pos.step_passenger') }}</li>
    <li :class="named ? 'is-now' : ''"><span>4</span>{{ __('pos.step_pay') }}</li>
    <li><span>5</span>{{ __('pos.step_ticket') }}</li>
   </ol>
   @include('components.vessel-deck-styles')
   <section class="ny-pos-card">
    <h3>{{ __('pos.seat_title') }}</h3>
    <p>{{ __('pos.seat_help') }}</p>
    <div class="ny-pos-legend">
     <span><i class="ny-seat"></i>{{ __('pos.free_seat') }}</span>
     <span><i class="ny-seat is-taken"></i>{{ __('pos.taken_seat') }}</span>
     <span><i class="ny-seat is-held"></i>{{ __('pos.held_seat') }}</span>
    </div>
    @foreach($decks as $deck)
     <h4>{{ $deck['deck'] === 'principal' ? __('pos.deck_main') : ($deck['deck'] === 'superior' ? __('pos.deck_upper') : __('pos.deck_other', ['deck' => $deck['deck']])) }}</h4>
     <x-vessel-hull :columns="$deck['plan']['columns']">
      @foreach($deck['plan']['grid'] as $line)
       @php($firstSeat = collect($line)->pluck('seat')->filter()->first())
       <div class="ny-row"><span class="ny-rowno">{{ $firstSeat ? preg_replace('/\D.*/', '', $firstSeat->code) : '' }}</span>
        @foreach($line as $cell)
         @if($seat = $cell['seat'])
          @php($held = in_array($seat->id, $heldIds, true))
          @php($taken = ! $held && (in_array($seat->id, $occupiedIds, true) || ! $seat->is_available))
          @php($mine = ($agency ?? null) && in_array($seat->id, $sellableIds, true))
          @php($blocked = ($agency ?? null) ? (! $mine || $taken) : ($held || $taken))
          <label class="ny-seat-form"><input type="radio" name="seat_id" value="{{ $seat->id }}" data-space="seat" class="peer sr-only" @disabled($blocked) @change="space = 'seat'; seat = true"><span @class(['ny-seat', 'is-taken' => $taken || (($agency ?? null) && ! $mine && ! $held), 'is-held' => $held && ! $mine])>{{ $seat->code }}</span></label>
         @else
          <x-vessel-deco :type="$cell['type']" />
         @endif
        @endforeach
       </div>
      @endforeach
     </x-vessel-hull>
     <x-vessel-deco-legend :plan="$deck['plan']" />
    @endforeach
    @if($cabins->isNotEmpty() || $lotHammocks->isNotEmpty() || (! ($agency ?? null) && $hammocks > 0))
     <div class="ny-pos-places">
      @foreach($cabins as $cabin)
       @php($held = in_array($cabin->id, $heldIds, true))
       @php($taken = ! $held && (in_array($cabin->id, $occupiedIds, true) || ! $cabin->is_available))
       @php($mine = ($agency ?? null) && in_array($cabin->id, $sellableIds, true))
       @php($blocked = ($agency ?? null) ? (! $mine || $taken) : ($held || $taken))
       <label class="ny-pos-place"><input type="radio" name="seat_id" value="{{ $cabin->id }}" data-space="cabin_bed" @disabled($blocked) @change="space = 'cabin_bed'; seat = true"><span>{{ __('pos.cabin', ['code' => $cabin->code]) }}</span>@if($held && ! $mine)<em>{{ __('pos.held_seat') }}</em>@endif</label>
      @endforeach
      @foreach($lotHammocks as $hammock)
       <label class="ny-pos-place"><input type="radio" name="seat_id" value="{{ $hammock->id }}" data-space="hammock" @change="space = 'hammock'; seat = true"><span>{{ $hammock->code }}</span></label>
      @endforeach
      @if(! ($agency ?? null) && $hammocks > 0)
       <label class="ny-pos-place"><input type="radio" name="place" value="hammock" @change="space = 'hammock'; seat = true"><span>{{ __('pos.hammock') }}</span><em>{{ __('pos.hammocks', ['count' => $hammocks]) }}</em></label>
      @endif
     </div>
    @endif
   </section>

   <aside class="ny-pos-card ny-pos-sale">
    <h3><span class="ny-pos-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="user" /></span>{{ __('pos.passenger') }}</h3>
    <label>{{ __('pos.first_name') }}<input name="first_name" x-model="firstName" required autocomplete="given-name"></label>
    <label>{{ __('pos.last_name') }}<input name="last_name" x-model="lastName" required autocomplete="family-name"></label>
    <label>{{ __('pos.document_type') }}
     <select name="document_type">
      @foreach(['DNI', 'CE', 'Pasaporte'] as $type)<option @selected(old('document_type') === $type)>{{ $type }}</option>@endforeach
     </select>
    </label>
    <label>{{ __('pos.document_number') }}<input name="document_number" x-model="documentNumber" required><small>{{ __('pos.document_help') }}</small></label>
    <label>{{ __('pos.guardian') }} <em>{{ __('pos.optional') }}</em><input name="guardian_document" value="{{ old('guardian_document') }}"></label>
    <label>{{ __('pos.phone') }} <em>{{ __('pos.optional') }}</em><input name="phone" x-model="phone" inputmode="tel"></label>
    <label>{{ __('pos.email') }} <em>{{ __('pos.optional') }}</em><input type="email" name="email" value="{{ old('email') }}"></label>
    <label>{{ __('pos.passenger_type') }}
     <select name="passenger_type" x-model="passenger">
      @foreach(['adult', 'child', 'student', 'senior', 'infant'] as $type)<option value="{{ $type }}">{{ __('pos.'.$type) }}</option>@endforeach
     </select>
    </label>
    @if($agency ?? null)
     <label>{{ __('agencies.sale_price') }}
      <input type="number" name="sale_price" min="0" step="0.01" value="{{ old('sale_price') }}" x-model="salePrice" required>
      <small>{{ __('agencies.price_between') }} <span x-text="range"></span></small>
     </label>
    @endif
    @if($discounts->isNotEmpty())
     <label>{{ __('pos.discount') }}
      <select name="discount_id" x-model="discountId">
       <option value="">{{ __('pos.no_discount') }}</option>
       @foreach($discounts as $discount)<option value="{{ $discount->id }}">{{ $discount->name }}</option>@endforeach
      </select>
     </label>
    @endif
    @if($sites->isNotEmpty())
     <label>{{ __('pos.site') }}
      <select name="site_id" required>
       @foreach($sites as $site)<option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->name }}</option>@endforeach
      </select>
     </label>
    @endif

    <h3><span class="ny-pos-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="luggage" /></span>{{ __('pos.baggage') }}</h3>
    <p class="ny-pos-help">{{ __('pos.allowance', ['hand' => $allowance['hand'], 'hold' => $allowance['hold']]) }}</p>
    <div class="ny-pos-split">
     <label>{{ __('pos.hand') }}<input type="number" name="hand_kg" min="0" max="200" value="{{ old('hand_kg', 0) }}" x-model.number="hand"></label>
     <label>{{ __('pos.hold') }}<input type="number" name="hold_kg" min="0" max="500" value="{{ old('hold_kg', 0) }}" x-model.number="hold"></label>
    </div>
    <p class="ny-pos-note is-alert" x-cloak x-show="over">{{ __('pos.cargo_warn', ['kg' => $allowance['cargo']]) }} @if(! ($agency ?? null))<a :href="cargoHref">{{ __('pos.cargo_link') }}</a>@endif</p>

    <h3><span class="ny-pos-pill" style="--c: var(--ny-tone-leaf)"><x-ui.icon name="cash" /></span>{{ __('pos.pay') }}</h3>
    <div class="ny-pos-pays">
     <label class="ny-pos-pay" style="--c: var(--ny-tone-leaf-deep)" :class="method === 'cash' && 'is-on'"><input type="radio" name="method" value="cash" x-model="method"><span class="ny-pos-pill"><x-ui.icon name="cash" /></span>{{ __('pos.cash') }}</label>
     <label class="ny-pos-pay" style="--c: var(--ny-tone-violet-deep)" :class="method === 'yape' && 'is-on'"><input type="radio" name="method" value="yape" x-model="method"><span class="ny-pos-pill"><x-ui.icon name="wallet" /></span>{{ __('pos.yape') }}</label>
     <label class="ny-pos-pay" style="--c: var(--ny-tone-coral-deep)" :class="method === 'card' && 'is-on'"><input type="radio" name="method" value="card" x-model="method"><span class="ny-pos-pill"><x-ui.icon name="card" /></span>{{ __('pos.card') }}</label>
    </div>
    <label x-show="method === 'cash'" x-cloak>{{ __('pos.received') }}<input type="number" name="cash_tendered" min="0" step="0.01" value="{{ old('cash_tendered') }}" x-model="tendered" :required="method === 'cash'"></label>
    <p class="ny-pos-change" x-show="method === 'cash' && change !== null" x-cloak><span>{{ __('pos.change') }}</span><b x-text="money(change)"></b></p>
    <label x-show="method !== 'cash'" x-cloak>{{ __('pos.operation') }}<input name="operation_number" value="{{ old('operation_number') }}" :required="method !== 'cash'"></label>

    <div class="ny-pos-total">
     <span>{{ __('pos.total') }}</span>
     <strong x-text="total === null ? '{{ __('pos.no_fare') }}' : money(total)"></strong>
     <small>{{ __('pos.no_fee') }}</small>
    </div>
    <button class="ny-pos-main" type="submit">{{ __('pos.submit') }}</button>
   </aside>
  </form>
  <script>
   function posDesk(config) {
    return {
     prices: config.prices, discounts: config.discounts, cargoLimit: config.cargo, agency: config.agency, percent: Number(config.percent || 0),
     method: @js(old('method', 'cash')), passenger: @js(old('passenger_type', 'adult')), space: 'seat', seat: false,
     discountId: @js((string) old('discount_id', '')), tendered: @js(old('cash_tendered', '')), hand: @js((int) old('hand_kg', 0)), hold: @js((int) old('hold_kg', 0)),
     firstName: @js(old('first_name', '')), lastName: @js(old('last_name', '')), documentNumber: @js(old('document_number', '')), phone: @js(old('phone', '')), salePrice: @js(old('sale_price', '')), departureId: String(config.departure || ''), parcelBase: config.parcels,
     get named() { return String(this.firstName).trim() !== '' && String(this.lastName).trim() !== ''; },
     get base() { const row = this.prices[this.space] || {}; return row[this.passenger] ?? null; },
     get range() {
      if (!this.agency || this.base === null) return '';
      const cap = Math.round(this.base * (100 + this.percent)) / 100;
      return 'S/ ' + Number(this.base).toFixed(2) + ' – S/ ' + cap.toFixed(2);
     },
     get discount() {
      const row = this.discounts.find((item) => String(item.id) === String(this.discountId));
      if (!row || this.base === null) return 0;
      const off = row.kind === 'percent' ? this.base * Number(row.value) / 100 : Number(row.value);
      return Math.min(Math.max(0, Math.round(off * 100) / 100), this.base);
     },
     get total() {
      if (this.agency) {
       if (this.salePrice === '' || this.salePrice === null) return this.base;
       const price = Math.round(Number(this.salePrice) * 100) / 100;
       return Number.isFinite(price) ? price : null;
      }
      return this.base === null ? null : Math.round((this.base - this.discount) * 100) / 100;
     },
     get change() {
      if (this.total === null || this.tendered === '' || this.tendered === null) return null;
      return Math.round((Number(this.tendered) - this.total) * 100) / 100;
     },
     get over() { return Number(this.hand) + Number(this.hold) > Number(this.cargoLimit); },
     get cargoHref() {
      const params = new URLSearchParams();
      const name = (String(this.firstName) + ' ' + String(this.lastName)).trim();
      if (name) params.set('remitente', name);
      if (this.documentNumber) params.set('documento', this.documentNumber);
      if (this.phone) params.set('telefono', this.phone);
      const kilos = Number(this.hand || 0) + Number(this.hold || 0);
      if (kilos > 0) params.set('peso', String(kilos));
      if (this.departureId) params.set('salida', this.departureId);
      return this.parcelBase + '?' + params.toString();
     },
     money(value) { return 'S/ ' + Number(value).toFixed(2); }
    };
   }
  </script>
 @endif
</div>
@endsection
