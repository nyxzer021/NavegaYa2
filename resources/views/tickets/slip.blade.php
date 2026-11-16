@php($seat = $ticket->reservationSeat)
@php($reservation = $seat->reservation)
@php($isAir = $reservation->isAir())
@php($trip = $isAir ? $reservation->airDeparture : $reservation->departure)
@php($route = $isAir ? $trip->airRoute : $trip->transportRoute)
@php($vehicle = $isAir ? $trip->aircraft : $trip->vessel)
@php($company = $vehicle->organization)
@php($origin = $isAir ? $route->origin_city : $route->originPort->city)
@php($destination = $isAir ? $route->destination_city : $route->destinationPort->city)
@php($place = $isAir ? ($seat->aircraftSeat->code ?? '') : ($seat->seat->code ?? __('pos.hammock')))
@php($payment = $reservation->payments->first())
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 8px; }
body { margin: 0; color: #000; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
h1 { margin: 0; font-size: 16px; }
h2 { margin: 4px 0; font-size: 14px; }
.box { border: 1px solid #000; padding: 8px; }
.label { font-size: 12px; }
.qr { display: block; width: 160px; height: 160px; margin: 8px auto; }
.center { text-align: center; }
</style>
</head>
<body>
<main class="box">
 <p class="center"><strong>NavegaYA</strong></p>
 <h1 class="center">{{ __('pos.slip_title') }}</h1>
 <p class="center">{{ $company->commercial_name ?: $company->legal_name }}</p>
 <p class="label">{{ __('pos.slip_passenger') }}</p>
 <h2>{{ $seat->passenger_name }}</h2>
 <p>{{ $seat->document_type }} {{ $seat->document_number }}</p>
 <p class="label">{{ __('pos.slip_route') }}</p>
 <p><strong>{{ $origin }} → {{ $destination }}</strong></p>
 <p class="label">{{ __('pos.slip_departure') }}</p>
 <p>{{ \App\Support\DepartureSchedule::show($trip->departure_at) }}</p>
 <p class="label">{{ __('pos.slip_seat') }}</p>
 <p><strong>{{ $place }}</strong> · {{ $vehicle->name }}</p>
 <p class="label">{{ __('pos.slip_total') }}</p>
 <p><strong>S/ {{ number_format((float) $reservation->total_amount, 2) }}</strong>
  @if($payment) · {{ $payment->method }} @endif
 </p>
 <img class="qr" src="{{ $qrDataUri }}" alt="QR">
 <p class="center">{{ __('pos.slip_present') }}</p>
 <p class="center">{{ $reservation->code }}<br>{{ $ticket->code }}</p>
</main>
</body>
</html>
