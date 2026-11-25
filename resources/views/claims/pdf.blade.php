<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px; }
        body { font-family: Helvetica, sans-serif; color: #173b33; font-size: 13px; }
        h1 { margin: 0; font-size: 22px; }
        p { margin: 8px 0; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td { padding: 8px 10px; border: 1px solid #d8e5e0; vertical-align: top; }
        .label { display: block; font-size: 12px; color: #536a62; }
    </style>
</head>
<body>
    <h1>{{ __('claims.sheet') }}</h1>
    <p>{{ $provider_name }} · {{ __('claims.ruc') }} {{ $provider_ruc }}</p>
    <p>{{ __('claims.deadline', ['days' => \App\Support\LimaBusinessCalendar::RESPONSE_BUSINESS_DAYS]) }}</p>
    @include('claims.fields')
</body>
</html>
