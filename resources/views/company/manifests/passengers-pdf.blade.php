<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #173b33; font-size: 11px; }
        h1 { font-size: 16px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px; text-align: left; }
        th { background: #f1f5f9; }
        .warn { color: #9f1239; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Manifiesto de pasajeros</h1>
    <p>{{ $departure->transportRoute->originPort?->city }} → {{ $departure->transportRoute->destinationPort?->city }} · {{ \App\Support\DepartureSchedule::show($departure->departure_at) }} · {{ $departure->vessel?->name }}</p>
    <p>Capitán: {{ $captain }}</p>
    <p>Tripulación:
        @forelse($crew as $assignment)
            {{ $assignment->member?->name }} ({{ \App\Support\CrewRoster::ROLE_LABELS[$assignment->member?->role] ?? $assignment->member?->role }})@if(! $loop->last), @endif
        @empty
            Sin asignar
        @endforelse
    </p>
    <p class="{{ $people > $vests ? 'warn' : '' }}">Chalecos: {{ $vests }} · Personas a bordo en el manifiesto: {{ $people }}@if($people > $vests) · Faltan chalecos @endif</p>
    <table>
        <thead><tr><th>Nombre</th><th>DNI</th><th>Edad o tipo</th><th>Plaza</th><th>Estado</th></tr></thead>
        <tbody>
        @foreach($passengers as $seat)
            <tr>
                <td>{{ $seat->passenger_name }}</td>
                <td>{{ $seat->document_number }}</td>
                <td>{{ $seat->passenger_age ?: ($seat->passenger_type === 'infant' ? 'Infante' : ($seat->passenger_type ?: 'Adulto')) }}</td>
                <td>{{ $seat->passenger_type === 'infant' ? 'En brazos' : ($seat->seat?->code ?? '—') }}</td>
                <td>{{ $seat->ticket?->status === 'boarded' ? 'Abordó' : ($seat->ticket?->status === 'no_show' ? 'No se presentó' : 'Pendiente') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
