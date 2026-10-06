<?php

namespace App\Http\Controllers;

use App\Models\RouteDeparture;
use App\Support\AdminScope;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdminManifestController extends Controller
{
    public function show(RouteDeparture $departure): View
    {
        return view('manifests.show', $this->data($departure));
    }

    public function csv(RouteDeparture $departure): Response
    {
        $data = $this->data($departure);
        $rows = [['N°', 'Asiento', 'Tipo documento', 'Documento', 'Nombres y apellidos', 'Edad', 'Destino', 'Código boleto', 'Estado abordaje']];
        foreach ($data['passengers'] as $index => $seat) {
            $rows[] = [$index + 1, $seat->seat?->code, $seat->document_type, $seat->document_number, $seat->passenger_name, $seat->passenger_age, $data['destination'], $seat->ticket?->code, $seat->ticket?->status];
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="manifiesto-'.$departure->code.'.csv"']);
    }

    private function data(RouteDeparture $departure): array
    {
        $departure->load(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization']);
        $organizationId = AdminScope::organizationId(auth()->user());
        abort_if($organizationId && $departure->transportRoute->organization_id !== $organizationId, 403);
        $passengers = $departure->reservations()->whereNotIn('status', ['expired', 'cancelled'])
            ->with(['seats.seat', 'seats.ticket'])->get()->flatMap->seats
            ->sortBy(fn ($seat) => $seat->seat?->row_position * 100 + $seat->seat?->column_position)->values();

        return ['departure' => $departure, 'passengers' => $passengers, 'destination' => $departure->transportRoute->destinationPort->city];
    }
}
