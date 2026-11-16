<!doctype html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title>{{ $shipment->code }} · NavegaYA</title>
 @vite(['resources/css/app.css'])
</head>
<body class="ny-slip is-{{ $paper }}">
 <nav class="ny-slip-tools">
  <a href="{{ route('company.parcels.index') }}">{{ __('cargo.title') }}</a>
  <a href="{{ route('company.parcels.guide', [$shipment, 'papel' => 'a4']) }}">{{ __('cargo.paper_a4') }}</a>
  <a href="{{ route('company.parcels.guide', [$shipment, 'papel' => '80']) }}">{{ __('cargo.paper_80') }}</a>
  <a href="{{ route('company.parcels.guide', [$shipment, 'papel' => 'etiqueta']) }}">{{ __('cargo.label') }}</a>
  <button type="button" onclick="window.print()">{{ __('cargo.print') }}</button>
 </nav>
 @if(session('success'))<p class="ny-slip-ok" role="status">{{ session('success') }}</p>@endif
 <article class="ny-slip-sheet">
  <header>
   <p>{{ $paper === 'etiqueta' ? __('cargo.label_title') : __('cargo.guide_title') }}</p>
   <h1>{{ $shipment->code }}</h1>
   <strong>{{ $shipment->organization?->commercial_name ?: $shipment->organization?->legal_name }}</strong>
  </header>
  <img src="{{ $qr }}" alt="QR {{ $shipment->code }}">
  <dl>
   <div><dt>{{ __('cargo.to') }}</dt><dd>{{ $shipment->recipient_name }}</dd></div>
   <div><dt>{{ __('cargo.from') }}</dt><dd>{{ $shipment->sender_name }}</dd></div>
   <div><dt>{{ __('cargo.route') }}</dt><dd>@if($shipment->departure){{ $shipment->departure->transportRoute->originPort->city }} → {{ $shipment->departure->transportRoute->destinationPort->city }}@else{{ __('cargo.no_departure') }}@endif</dd></div>
   <div><dt>{{ __('cargo.weight') }}</dt><dd>{{ number_format((float) $shipment->declared_weight_kg, 2) }} kg · {{ __('cargo.packages_count', ['count' => $shipment->package_count]) }}</dd></div>
   @if($shipment->fragile)<div><dt>{{ __('cargo.fragile') }}</dt><dd>{{ __('cargo.fragile') }}</dd></div>@endif
   @if($paper !== 'etiqueta')
    <div><dt>{{ __('cargo.description') }}</dt><dd>{{ $shipment->description }}</dd></div>
    <div><dt>{{ __('cargo.price') }}</dt><dd>S/ {{ number_format((float) $shipment->amount, 2) }} · {{ $shipment->payment_status === 'pending' ? __('cargo.pending') : __('cargo.paid') }}</dd></div>
   @endif
  </dl>
 </article>
</body>
</html>
