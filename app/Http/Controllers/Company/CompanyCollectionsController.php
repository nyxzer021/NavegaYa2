<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CompanyCollections;
use App\Support\CompanyTeam;
use App\Support\CsvCell;
use App\Support\DepartureSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyCollectionsController extends Controller
{
    public function __construct(private readonly CompanyCollections $collections) {}

    public function index(Request $request): View
    {
        $organization = CompanyTeam::organization($request);
        $period = $this->collections->period((string) $request->query('vista', 'mes'), (string) $request->query('fecha', ''));

        return view('company.collections.index', [
            'organization' => $organization,
            'period' => $period,
            'statement' => $this->collections->statement($organization, $period),
            'money' => $this->collections,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $organization = CompanyTeam::organization($request);
        $period = $this->collections->period((string) $request->query('vista', 'mes'), (string) $request->query('fecha', ''));
        $statement = $this->collections->statement($organization, $period);
        $name = 'cobros-'.$period['from'].'.csv';

        return response()->streamDownload(function () use ($statement): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvCell::row([
                __('collections.csv_when'), __('collections.csv_ticket'), __('collections.csv_passenger'),
                __('collections.csv_fare'), __('collections.csv_discount'), __('collections.csv_fee'),
                __('collections.csv_net'), __('collections.csv_state'),
            ]));
            foreach ($statement['sales'] as $sale) {
                fputcsv($out, CsvCell::row([
                    DepartureSchedule::show($sale['when'], 'Y-m-d H:i'),
                    $sale['code'],
                    $sale['passenger'],
                    $this->collections->decimal($sale['fare_cents']),
                    $this->collections->decimal($sale['discount_cents']),
                    $this->collections->decimal($sale['fee_cents']),
                    $this->collections->decimal($sale['net_cents']),
                    __('collections.state_'.$sale['state']),
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
