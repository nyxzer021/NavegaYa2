<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <title>{{ __('cash.title') }}</title>
 @vite(['resources/css/app.css'])
</head>
<body class="ny-cash-print">
 <article class="ny-cash-slip">
  <h1>{{ $organization->commercial_name ?: $organization->legal_name }}</h1>
  <p>{{ __('cash.title') }}</p>
  <p>{{ $session->user?->name }} · {{ $session->site?->name ?: __('cash.no_site') }}</p>
  <p>{{ __('cash.opened_at') }} {{ \App\Support\DepartureSchedule::show($session->opened_at) }}</p>
  @if($session->closed_at)<p>{{ __('cash.closed_at') }} {{ \App\Support\DepartureSchedule::show($session->closed_at) }}</p>@endif
  <p>{{ __('cash.float') }} S/ {{ number_format((float) $session->opening_float, 2) }}</p>
  @include('company.cash.summary', ['summary' => $summary])
  @if($session->closed_at)
   <p>{{ __('cash.counted') }} S/ {{ number_format((float) $session->counted_cash, 2) }}</p>
   <p>{{ __('cash.difference') }} S/ {{ number_format((float) $session->difference, 2) }}</p>
   @if($session->closing_note)<p>{{ $session->closing_note }}</p>@endif
  @endif
  <button type="button" onclick="window.print()">{{ __('cash.print') }}</button>
 </article>
</body>
</html>
