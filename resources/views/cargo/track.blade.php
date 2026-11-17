<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title>{{ __('tracking.title') }} · NavegaYA</title>
 @vite(['resources/css/app.css'])
</head>
<body class="ny-track">
 <main>
  <p class="ny-track-kicker">{{ __('tracking.kicker') }}</p>
  <h1>{{ __('tracking.title') }}</h1>
  <p>{{ __('tracking.lead') }}</p>
  @if($errors->any())<p class="ny-track-note" role="alert">{{ $errors->first() }}</p>@endif
  <form method="POST" action="{{ route('cargo.track.lookup') }}">
   @csrf
   <label>{{ __('tracking.code') }}<input name="code" value="{{ old('code', $code) }}" required autocomplete="off"></label>
   <label>{{ __('tracking.document') }}<input name="document" value="{{ old('document') }}" required autocomplete="off"></label>
   <button type="submit">{{ __('tracking.search') }}</button>
  </form>
  @if($shipment)
   @php($status = in_array($shipment->status, ['received', 'boarded', 'delivered'], true) ? $shipment->status : null)
   <article class="ny-track-card">
    <h2>{{ $shipment->code }}</h2>
    <p><span @class(['ny-track-badge', 'is-received' => $status === 'received', 'is-boarded' => $status === 'boarded', 'is-delivered' => $status === 'delivered'])>{{ $status ? __('cargo.status_'.$status) : __('tracking.event_other') }}</span></p>
    <dl>
     <div><dt>{{ __('tracking.route') }}</dt><dd>@if($shipment->departure){{ $shipment->departure->transportRoute->originPort->city }} → {{ $shipment->departure->transportRoute->destinationPort->city }}@else{{ __('tracking.no_departure') }}@endif</dd></div>
     <div><dt>{{ __('tracking.when') }}</dt><dd>@if($shipment->departure){{ \App\Support\DepartureSchedule::show($shipment->departure->departure_at, 'D d M · H:i') }}@else{{ __('tracking.no_departure') }}@endif</dd></div>
     <div><dt>{{ __('tracking.vessel') }}</dt><dd>{{ $shipment->departure?->vessel?->name ?: __('tracking.no_departure') }}</dd></div>
     <div><dt>{{ __('tracking.sender_document') }}</dt><dd>{{ $senderDocument }}</dd></div>
     <div><dt>{{ __('tracking.recipient_document') }}</dt><dd>{{ $recipientDocument }}</dd></div>
    </dl>
    <h3>{{ __('tracking.history') }}</h3>
    <ol>
     @foreach($shipment->events as $event)
      <li><span>{{ in_array($event->kind, ['received', 'assigned', 'boarded', 'delivered', 'unassigned'], true) ? __('tracking.event_'.$event->kind) : __('tracking.event_other') }}</span><time>{{ \App\Support\DepartureSchedule::show($event->happened_at, 'd M · H:i') }}</time></li>
     @endforeach
    </ol>
   </article>
  @endif
 </main>
</body>
</html>
