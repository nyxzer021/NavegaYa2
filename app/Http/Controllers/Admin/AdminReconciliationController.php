<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CulqiException;
use App\Services\CulqiGateway;
use App\Services\CulqiReconciliation;
use App\Services\MercadoPagoException;
use App\Services\MercadoPagoGateway;
use App\Support\CsvCell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReconciliationController extends Controller
{
    public function __construct(private readonly CulqiReconciliation $reconciliation) {}

    public function index(Request $request): View
    {
        $month = $this->month($request);

        return view('admin.reconciliation.index', $this->reconciliation->month($month));
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);
        $contents = file_get_contents($request->file('archivo')->getRealPath());
        if (! is_string($contents)) {
            throw ValidationException::withMessages(['archivo' => __('reconciliation.empty')]);
        }
        $result = $this->reconciliation->importFile($contents, $request->file('archivo')->getClientOriginalName(), $this->user($request), $request);

        return back()->with('status', $result['duplicate_file']
            ? __('reconciliation.duplicate_file')
            : __('reconciliation.imported', ['created' => $result['created'], 'existing' => $result['existing']]));
    }

    public function fetch(Request $request, CulqiGateway $culqi, MercadoPagoGateway $mercadoPago): RedirectResponse
    {
        $month = $this->month($request);
        if ($request->string('provider')->toString() === 'mercadopago') {
            try {
                $movements = $mercadoPago->listMovements($month.'-01', $this->monthEnd($month));
            } catch (MercadoPagoException $exception) {
                return back()->with('error', $exception->getMessage());
            }
            $result = $this->reconciliation->importMovements($movements, $this->user($request), $request);

            return back()->with('status', __('reconciliation.fetched', ['created' => $result['created'], 'existing' => $result['existing']]));
        }
        try {
            $charges = $culqi->listCharges($month.'-01', $this->monthEnd($month));
        } catch (CulqiException $exception) {
            return back()->with('error', $exception->getMessage());
        }
        $result = $this->reconciliation->importCharges($charges, $this->user($request), $request);

        return back()->with('status', __('reconciliation.fetched', ['created' => $result['created'], 'existing' => $result['existing']]));
    }

    public function review(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mes' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'difference_key' => ['required', 'regex:/^(culqi_charge|local_charge|mp_charge|mp_local_charge|amount|culqi_refund|local_refund|mp_refund|mp_local_refund):(chr_|ref_|mp_|mpr_)[A-Za-z0-9_]+$/'],
            'note' => ['required', 'string', 'max:500'],
        ]);
        $this->reconciliation->review($request, $data['mes'], $data['difference_key'], trim($data['note']));

        return back()->with('status', __('reconciliation.reviewed'));
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->reconciliation->month($this->month($request));
        $name = 'conciliacion-'.$report['month'].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvCell::row([__('reconciliation.csv_concept'), __('reconciliation.csv_amount')]));
            foreach ([
                'charged' => 'summary_charged',
                'refunded' => 'summary_refunded',
                'culqi_fee' => 'summary_fee',
                'service_fee' => 'summary_service',
                'fares' => 'summary_fares',
                'net_gain' => 'summary_gain',
                'deposited' => 'summary_deposited',
            ] as $key => $label) {
                fputcsv($out, CsvCell::row([__('reconciliation.'.$label), $report['summary'][$key]]));
            }
            fputcsv($out, CsvCell::row([
                __('reconciliation.csv_date'), __('reconciliation.csv_kind'), __('reconciliation.csv_reference'),
                __('reconciliation.csv_culqi'), __('reconciliation.csv_local'), __('reconciliation.csv_note'),
            ]));
            foreach ($report['differences'] as $difference) {
                fputcsv($out, CsvCell::row([
                    $difference['date'],
                    __('reconciliation.kind_'.$difference['kind']),
                    $difference['reference'],
                    $difference['culqi'] ?? '',
                    $difference['local'] ?? '',
                    $difference['note'] ?? '',
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function month(Request $request): string
    {
        $month = $request->string('mes')->toString();
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return now()->timezone('America/Lima')->format('Y-m');
        }

        return $month;
    }

    private function monthEnd(string $month): string
    {
        return Carbon::createFromFormat('Y-m-d', $month.'-01', 'America/Lima')->endOfMonth()->toDateString();
    }
}
