<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CargoShipment;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Ticket;
use App\Services\PaymentReceipts;
use App\Support\BoardingQr;
use App\Support\CargoGuideQr;
use App\Support\CompanyTeam;
use App\Support\CsvCell;
use App\Support\DepartureSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyReceiptsController extends Controller
{
    public function __construct(private readonly PaymentReceipts $receipts) {}

    public function index(Request $request): View
    {
        $organization = CompanyTeam::organization($request);
        $filters = $this->filters($request);

        return view('company.receipts.index', [
            'organization' => $organization,
            'filters' => $filters,
            'catalog' => $this->receipts->catalog($organization, $filters),
            'money' => $this->receipts,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $organization = CompanyTeam::organization($request);
        $catalog = $this->receipts->catalog($organization, $this->filters($request));
        $name = 'comprobantes-'.now()->timezone(DepartureSchedule::ZONE)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($catalog): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvCell::row([
                __('receipts.csv_number'), __('receipts.csv_when'), __('receipts.csv_client'),
                __('receipts.csv_document'), __('receipts.csv_kind'), __('receipts.csv_channel'),
                __('receipts.csv_amount'), __('receipts.csv_invoice'), __('receipts.csv_reference'),
            ]));
            foreach ($catalog['rows'] as $row) {
                fputcsv($out, CsvCell::row([
                    $row['receipt']?->number ?: __('receipts.pending_number'),
                    DepartureSchedule::show($row['when'], 'Y-m-d H:i'),
                    $row['client'],
                    $row['document'],
                    $row['kind_label'],
                    $row['channel_label'],
                    number_format($row['amount_cents'] / 100, 2, '.', ''),
                    $row['invoice'] ? __('receipts.yes') : __('receipts.no'),
                    (string) $row['reference'],
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function certificate(Request $request, PaymentReceipt $paymentReceipt)
    {
        $this->ownedReceipt($request, $paymentReceipt);

        return app('dompdf.wrapper')
            ->loadView('company.receipts.certificate', $this->receipts->certificate($paymentReceipt))
            ->setPaper('a4')
            ->download($paymentReceipt->number.'.pdf');
    }

    public function issue(Request $request, Payment $payment): RedirectResponse
    {
        $organization = CompanyTeam::organization($request);
        abort_unless($this->receipts->organizationId($payment) === (int) $organization->id, 404);
        $receipt = $this->receipts->issue($payment);
        abort_if($receipt === null, 404);

        return redirect()->route('company.receipts.certificate', $receipt);
    }

    public function ticket(Request $request, Ticket $ticket)
    {
        $ticket->load([
            'reservationSeat.reservation.departure.transportRoute.originPort',
            'reservationSeat.reservation.departure.transportRoute.destinationPort',
            'reservationSeat.reservation.departure.vessel.organization',
            'reservationSeat.reservation.airDeparture.airRoute',
            'reservationSeat.reservation.airDeparture.aircraft.organization',
            'reservationSeat.seat',
            'reservationSeat.aircraftSeat',
            'reservationSeat.reservation.payments',
        ]);
        $reservation = $ticket->reservationSeat?->reservation;
        $organizationId = (int) ($reservation?->departure?->transportRoute?->organization_id
            ?? $reservation?->airDeparture?->airRoute?->organization_id);
        abort_unless($organizationId === (int) CompanyTeam::organization($request)->id, 404);
        $qrDataUri = BoardingQr::dataUri($ticket);

        return app('dompdf.wrapper')
            ->loadView('tickets.pdf', compact('ticket', 'qrDataUri'))
            ->setPaper('a4')
            ->download('boleto-'.$ticket->code.'.pdf');
    }

    public function guide(Request $request, CargoShipment $shipment): View
    {
        abort_unless((int) $shipment->organization_id === (int) CompanyTeam::organization($request)->id, 404);

        return view('company.cargo.guide', [
            'shipment' => $shipment->load(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'organization']),
            'paper' => 'a4',
            'qr' => CargoGuideQr::dataUri(route('cargo.track', ['codigo' => $shipment->code])),
        ]);
    }

    /**
     * @return array{desde: string, hasta: string, tipo: string, cliente: string}
     */
    private function filters(Request $request): array
    {
        $kind = (string) $request->query('tipo', '');

        return [
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
            'tipo' => in_array($kind, ['boleto', 'encomienda'], true) ? $kind : '',
            'cliente' => trim((string) $request->query('cliente', '')),
        ];
    }

    private function ownedReceipt(Request $request, PaymentReceipt $receipt): void
    {
        abort_unless((int) $receipt->organization_id === (int) CompanyTeam::organization($request)->id, 404);
    }
}
