@extends('layouts.company')
@section('title', __('cargo.title'))
@section('page-title', __('cargo.title'))
@section('content')
<div class="ny-parcel" x-data="{ length: {{ (int) old('length_cm', 0) }}, width: {{ (int) old('width_cm', 0) }}, height: {{ (int) old('height_cm', 0) }}, method: @js(old('method', 'cash')), tendered: @js(old('cash_tendered', '')), price: @js(old('amount', '')), departure: @js((string) old('route_departure_id', $prefill['departure_id'])), capacities: @js($capacities) }">
 <header class="ny-parcel-hero">
  <p class="ny-parcel-kicker">{{ __('cargo.kicker') }}</p>
  <h2>{{ __('cargo.title') }}</h2>
  <p>{{ __('cargo.lead') }}</p>
 </header>

 @if($errors->any())<p class="ny-parcel-note is-alert" role="alert">{{ $errors->first() }}</p>@endif
 @if($loose > 0)
  <p class="ny-parcel-note is-alert" role="status">{{ __('cargo.reassign_title') }}. {{ __('cargo.reassign_body') }}</p>
 @endif

 @if($receivedToday > 0 || $boardedToday > 0 || $deliveredToday > 0 || $pendingPay > 0)
 <section class="ny-parcel-kpis">
  @if($receivedToday > 0)<article class="ny-parcel-kpi" style="--c: var(--ny-tone-sun)"><span class="ny-parcel-pill"><x-ui.icon name="parcels" /></span><b>{{ $receivedToday }}</b><span>{{ __('cargo.received_today') }}</span></article>@endif
  @if($boardedToday > 0)<article class="ny-parcel-kpi" style="--c: var(--ny-tone-river)"><span class="ny-parcel-pill"><x-ui.icon name="sailboat" /></span><b>{{ $boardedToday }}</b><span>{{ __('cargo.boarded_today') }}</span></article>@endif
  @if($deliveredToday > 0)<article class="ny-parcel-kpi" style="--c: var(--ny-tone-leaf)"><span class="ny-parcel-pill"><x-ui.icon name="check" /></span><b>{{ $deliveredToday }}</b><span>{{ __('cargo.delivered_today') }}</span></article>@endif
  @if($pendingPay > 0)<article class="ny-parcel-kpi" style="--c: var(--ny-tone-coral)"><span class="ny-parcel-pill"><x-ui.icon name="wallet" /></span><b>{{ $pendingPay }}</b><span>{{ __('cargo.to_collect') }}</span></article>@endif
 </section>
 @endif

 @if($rates !== [])
 <section class="ny-parcel-card">
  <h3><span class="ny-parcel-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="fares" /></span>{{ __('cargo.reference') }}</h3>
  <p>{{ __('cargo.reference_note') }}</p>
  <ul class="ny-parcel-rates">
   @foreach(['pequeno' => 'small', 'mediano' => 'medium', 'grande' => 'large'] as $key => $label)
    @if(isset($rates[$key]))<li><b>{{ __('cargo.'.$label) }}</b><span>S/ {{ number_format((float) $rates[$key], 2) }}</span></li>@endif
   @endforeach
  </ul>
  @if($portNote)<p>{{ $portNote }}</p>@endif
 </section>
 @endif

 <form class="ny-parcel-card" method="POST" action="{{ route('company.parcels.store') }}">
  @csrf
  <h3><span class="ny-parcel-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="parcels" /></span>{{ __('cargo.new') }}</h3>
  <div class="ny-parcel-grid">
   <fieldset>
    <legend><span class="ny-parcel-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="user" /></span>{{ __('cargo.sender') }}</legend>
    <label>{{ __('cargo.name') }}<input name="sender_name" value="{{ old('sender_name', $prefill['sender_name']) }}" required></label>
    <label>{{ __('cargo.document') }}<input name="sender_document" value="{{ old('sender_document', $prefill['sender_document']) }}" required></label>
    <label>{{ __('cargo.phone') }}<input name="sender_phone" value="{{ old('sender_phone', $prefill['sender_phone']) }}" required></label>
   </fieldset>
   <fieldset>
    <legend><span class="ny-parcel-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="user" /></span>{{ __('cargo.recipient') }}</legend>
    <label>{{ __('cargo.name') }}<input name="recipient_name" value="{{ old('recipient_name') }}" required></label>
    <label>{{ __('cargo.document') }}<input name="recipient_document" value="{{ old('recipient_document') }}" required></label>
    <label>{{ __('cargo.phone') }}<input name="recipient_phone" value="{{ old('recipient_phone') }}" required></label>
   </fieldset>
  </div>
  <label>{{ __('cargo.description') }}<input name="description" value="{{ old('description') }}" required></label>
  <div class="ny-parcel-measures">
   <label>{{ __('cargo.packages') }}<input type="number" name="package_count" min="1" value="{{ old('package_count', 1) }}" required></label>
   <label><span class="ny-parcel-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="scale" /></span>{{ __('cargo.weight') }}<input type="number" name="declared_weight_kg" min="0.01" step="0.01" value="{{ old('declared_weight_kg', $prefill['weight']) }}" required></label>
   <label><span class="ny-parcel-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="ruler" /></span>{{ __('cargo.length') }}<input type="number" name="length_cm" min="1" x-model.number="length" required></label>
   <label>{{ __('cargo.width') }}<input type="number" name="width_cm" min="1" x-model.number="width" required></label>
   <label>{{ __('cargo.height') }}<input type="number" name="height_cm" min="1" x-model.number="height" required></label>
  </div>
  <p class="ny-parcel-volume" x-show="length > 0 && width > 0 && height > 0" x-cloak>{{ __('cargo.volume') }}: <b x-text="(length * width * height).toLocaleString('es-PE') + ' cm³ · ' + ((length * width * height) / 1000).toFixed(2) + ' L'"></b></p>
  <label class="ny-parcel-check"><input type="checkbox" name="fragile" value="1" @checked(old('fragile'))><span class="ny-parcel-pill" style="--c: var(--ny-tone-coral)"><x-ui.icon name="fragile" /></span>{{ __('cargo.fragile') }}</label>
  <label>{{ __('cargo.departure') }}
   <select name="route_departure_id" x-model="departure">
    <option value="">{{ __('cargo.departure_later') }}</option>
    @foreach($departures as $departure)
     <option value="{{ $departure->id }}">{{ \App\Support\DepartureSchedule::show($departure->departure_at, 'D d M · H:i') }} · {{ $departure->transportRoute->originPort->city }} → {{ $departure->transportRoute->destinationPort->city }}</option>
    @endforeach
   </select>
  </label>
  <template x-if="departure && capacities[departure]">
   <div>
    <p class="ny-parcel-help" x-show="!capacities[departure].limit">{{ __('cargo.no_capacity') }}</p>
    <div class="ny-parcel-bar" x-show="capacities[departure].limit" :class="'is-' + capacities[departure].tone"><i :style="'width:' + capacities[departure].percent + '%'"></i></div>
    <p class="ny-parcel-help" x-show="capacities[departure].limit" x-text="capacities[departure].used + ' kg / ' + capacities[departure].limit + ' kg'"></p>
   </div>
  </template>
  <label>{{ __('cargo.price') }}<input type="number" name="amount" min="0" step="0.01" x-model="price" required></label>
  <h4>{{ __('cargo.pay') }}</h4>
  <div class="ny-parcel-pays">
   <label class="ny-parcel-pay" style="--c: var(--ny-tone-leaf-deep)" :class="method === 'cash' && 'is-on'"><input type="radio" name="method" value="cash" x-model="method"><span class="ny-parcel-pill"><x-ui.icon name="cash" /></span>{{ __('cargo.cash') }}</label>
   <label class="ny-parcel-pay" style="--c: var(--ny-tone-violet-deep)" :class="method === 'yape' && 'is-on'"><input type="radio" name="method" value="yape" x-model="method"><span class="ny-parcel-pill"><x-ui.icon name="wallet" /></span>{{ __('cargo.yape') }}</label>
   <label class="ny-parcel-pay" style="--c: var(--ny-tone-coral-deep)" :class="method === 'card' && 'is-on'"><input type="radio" name="method" value="card" x-model="method"><span class="ny-parcel-pill"><x-ui.icon name="card" /></span>{{ __('cargo.card') }}</label>
   <label class="ny-parcel-pay is-sun" style="--c: var(--ny-tone-sun-deep)" :class="method === 'recipient' && 'is-on'"><input type="radio" name="method" value="recipient" x-model="method"><span class="ny-parcel-pill"><x-ui.icon name="user" /></span>{{ __('cargo.recipient_pays') }}</label>
  </div>
  <label x-show="method === 'cash'" x-cloak>{{ __('cargo.received_amount') }}<input type="number" name="cash_tendered" min="0" step="0.01" x-model="tendered" :required="method === 'cash'"></label>
  <p class="ny-parcel-change" x-show="method === 'cash' && price !== '' && tendered !== ''" x-cloak>{{ __('cargo.change') }} <b x-text="'S/ ' + (Math.round((Number(tendered) - Number(price)) * 100) / 100).toFixed(2)"></b></p>
  <label x-show="method === 'yape' || method === 'card'" x-cloak>{{ __('cargo.operation') }}<input name="operation_number" value="{{ old('operation_number') }}" :required="method === 'yape' || method === 'card'"></label>
  @if($sites->isNotEmpty())
   <label x-show="method !== 'recipient'" x-cloak>{{ __('cargo.site') }}
    <select name="site_id" :required="method !== 'recipient'">
     @foreach($sites as $site)<option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->name }}</option>@endforeach
    </select>
   </label>
  @endif
  <button class="ny-parcel-main" type="submit">{{ __('cargo.save') }}</button>
 </form>

 @forelse($shipments as $shipment)
  @php($volume = (int) $shipment->length_cm * (int) $shipment->width_cm * (int) $shipment->height_cm)
  @php($load = $capacities[$shipment->route_departure_id] ?? null)
  <article class="ny-parcel-card is-parcel">
   <header class="ny-parcel-head">
    <span class="ny-parcel-pill" style="--c: var(--ny-tone-sun)"><x-ui.icon name="parcels" /></span>
    <div>
     <h3>{{ $shipment->code }}</h3>
     <p>{{ $shipment->sender_name }} → {{ $shipment->recipient_name }} · {{ $shipment->description }}</p>
    </div>
    <span @class(['ny-parcel-badge', 'is-received' => $shipment->status === 'received', 'is-boarded' => $shipment->status === 'boarded', 'is-delivered' => $shipment->status === 'delivered'])>
     <x-ui.icon :name="$shipment->status === 'delivered' ? 'check' : ($shipment->status === 'boarded' ? 'sailboat' : 'parcels')" />
     {{ __('cargo.status_'.$shipment->status) }}
    </span>
   </header>
   <ul class="ny-parcel-facts">
    <li><span class="ny-parcel-pill" style="--c: var(--ny-tone-river)"><x-ui.icon name="scale" /></span>{{ number_format((float) $shipment->declared_weight_kg, 2) }} kg</li>
    @if($volume > 0)<li><span class="ny-parcel-pill" style="--c: var(--ny-tone-violet)"><x-ui.icon name="ruler" /></span>{{ number_format($volume, 0, '.', ' ') }} cm³</li>@endif
    @if($shipment->fragile)<li><span class="ny-parcel-pill" style="--c: var(--ny-tone-coral)"><x-ui.icon name="fragile" /></span>{{ __('cargo.fragile') }}</li>@endif
    <li>S/ {{ number_format((float) $shipment->amount, 2) }}</li>
   </ul>
   <p>
    @if($shipment->payment_status === 'pending')
     <span class="ny-parcel-badge is-received"><x-ui.icon name="wallet" />{{ __('cargo.pending') }}</span>
    @else
     <span class="ny-parcel-badge is-delivered"><x-ui.icon name="check" />{{ __('cargo.paid') }}</span>
    @endif
    @if($shipment->needs_reassignment)<span class="ny-parcel-badge is-received">{{ __('cargo.no_departure') }}</span>@endif
   </p>
   @if($shipment->departure)
    <p>{{ \App\Support\DepartureSchedule::show($shipment->departure->departure_at, 'D d M · H:i') }} · {{ $shipment->departure->transportRoute->originPort->city }} → {{ $shipment->departure->transportRoute->destinationPort->city }}</p>
    @if($load && $load['limit'])
     <div @class(['ny-parcel-bar', 'is-'.$load['tone']])<i style="width: {{ $load['percent'] }}%"></i></div>
     <p class="ny-parcel-help">{{ __('cargo.used', ['used' => number_format($load['used'], 2), 'limit' => number_format($load['limit'], 2)]) }}</p>
    @elseif($load)
     <p class="ny-parcel-help">{{ __('cargo.no_capacity') }}</p>
    @endif
   @else
    <p>{{ __('cargo.no_departure') }}</p>
   @endif
   <p class="ny-parcel-links">
    <a href="{{ route('company.parcels.guide', $shipment) }}">{{ __('cargo.guide') }}</a>
    <a href="{{ route('company.parcels.guide', [$shipment, 'papel' => 'etiqueta']) }}">{{ __('cargo.label') }}</a>
   </p>
   @if($shipment->status === 'received')
    <form method="POST" action="{{ route('company.parcels.assign', $shipment) }}">
     @csrf @method('PATCH')
     <label>{{ __('cargo.departure') }}
      <select name="route_departure_id" required>
       @foreach($departures as $departure)
        <option value="{{ $departure->id }}">{{ \App\Support\DepartureSchedule::show($departure->departure_at, 'D d M · H:i') }} · {{ $departure->transportRoute->originPort->city }} → {{ $departure->transportRoute->destinationPort->city }}</option>
       @endforeach
      </select>
     </label>
     <button class="ny-parcel-soft" type="submit">{{ __('cargo.assign') }}</button>
    </form>
    @if($shipment->route_departure_id)
     <form method="POST" action="{{ route('company.parcels.board', $shipment) }}">@csrf @method('PATCH')<button class="ny-parcel-main" type="submit">{{ __('cargo.board') }}</button></form>
    @endif
   @endif
   @if($shipment->status === 'boarded')
    <form method="POST" action="{{ route('company.parcels.deliver', $shipment) }}">
     @csrf @method('PATCH')
     <label>{{ __('cargo.collector') }}<input name="collector_name"></label>
     <label>{{ __('cargo.collector_document') }}<input name="collector_document" required></label>
     @if($shipment->payment_status !== 'paid')
      <div class="ny-parcel-pays">
       <label class="ny-parcel-pay" style="--c: var(--ny-tone-leaf-deep)"><input type="radio" name="method" value="cash" checked><span class="ny-parcel-pill"><x-ui.icon name="cash" /></span>{{ __('cargo.cash') }}</label>
       <label class="ny-parcel-pay" style="--c: var(--ny-tone-violet-deep)"><input type="radio" name="method" value="yape"><span class="ny-parcel-pill"><x-ui.icon name="wallet" /></span>{{ __('cargo.yape') }}</label>
       <label class="ny-parcel-pay" style="--c: var(--ny-tone-coral-deep)"><input type="radio" name="method" value="card"><span class="ny-parcel-pill"><x-ui.icon name="card" /></span>{{ __('cargo.card') }}</label>
      </div>
      <label>{{ __('cargo.received_amount') }}<input type="number" name="cash_tendered" min="0" step="0.01"></label>
      <label>{{ __('cargo.operation') }}<input name="operation_number"></label>
      @if($sites->isNotEmpty())
       <label>{{ __('cargo.site') }}<select name="site_id">@foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach</select></label>
      @endif
     @endif
     <button class="ny-parcel-main" type="submit">{{ __('cargo.deliver') }}</button>
    </form>
   @endif
  </article>
 @empty
  <section class="ny-parcel-empty">
   <span class="ny-parcel-pill is-lg" style="--c: var(--ny-tone-sun)"><x-ui.icon name="parcels" /></span>
   <h2>{{ __('cargo.empty_title') }}</h2>
   <p>{{ __('cargo.empty_body') }}</p>
  </section>
 @endforelse
</div>
@endsection
