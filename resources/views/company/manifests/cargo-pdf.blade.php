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
    </style>
</head>
<body>
    <h1>Manifiesto de carga</h1>
    <p>{{ \App\Support\DepartureSchedule::show($departure->departure_at) }}</p>
    <table>
        <thead><tr><th>Guía</th><th>Remitente</th><th>Peso (kg)</th></tr></thead>
        <tbody>
        @forelse($shipments as $shipment)
            <tr>
                <td>{{ $shipment->code }}</td>
                <td>{{ $shipment->sender_name }}</td>
                <td>{{ number_format((float) ($shipment->verified_weight_kg ?? $shipment->declared_weight_kg), 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No hay carga registrada.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
