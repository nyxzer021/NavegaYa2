<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CompanyReports;
use App\Support\CompanyTeam;
use App\Support\CsvCell;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyReportsController extends Controller
{
    public function __construct(private readonly CompanyReports $reports) {}

    public function index(Request $request): View
    {
        $organization = CompanyTeam::organization($request);
        [$view, $from, $to] = $this->filters($request);

        return view('company.reports.index', [
            'organization' => $organization,
            'report' => $this->reports->report($organization, $view, $from, $to),
            'money' => $this->reports,
            'filters' => ['vista' => $view, 'desde' => $from, 'hasta' => $to],
        ]);
    }

    public function sales(Request $request): StreamedResponse
    {
        return $this->csv($request, 'ventas', function ($out, array $report) use ($request): void {
            fputcsv($out, CsvCell::row([
                __('reports.csv_group'), __('reports.csv_name'), __('reports.csv_count'), __('reports.csv_amount'),
            ]));
            $tables = [
                'canal' => [__('reports.by_channel'), $report['channels']],
                'ruta' => [__('reports.by_route'), $report['routes']],
                'salida' => [__('reports.by_departure'), $report['departures']],
                'vendedor' => [__('reports.by_seller'), $report['sellers']],
                'agencia' => [__('reports.by_agency'), $report['agencies_sales']],
            ];
            $only = (string) $request->query('tabla', '');
            if (! isset($tables[$only])) {
                $only = '';
            }
            foreach ($tables as $key => [$group, $rows]) {
                if ($only !== '' && $only !== $key) {
                    continue;
                }
                foreach ($rows as $row) {
                    fputcsv($out, CsvCell::row([
                        $group, $row['label'], (string) $row['count'], $this->reports->signed($row['cents']),
                    ]));
                }
            }
            fputcsv($out, CsvCell::row([
                __('reports.total'), __('reports.total'), (string) $report['total_count'], $this->reports->signed($report['total_cents']),
            ]));
        });
    }

    public function occupancy(Request $request): StreamedResponse
    {
        return $this->csv($request, 'ocupacion', function ($out, array $report): void {
            fputcsv($out, CsvCell::row([
                __('reports.csv_route'), __('reports.csv_when'), __('reports.csv_sold'), __('reports.csv_capacity'),
            ]));
            foreach ($report['occupancy'] as $row) {
                fputcsv($out, CsvCell::row([$row['label'], $row['when'], (string) $row['sold'], (string) $row['capacity']]));
            }
        });
    }

    public function parcels(Request $request): StreamedResponse
    {
        return $this->csv($request, 'encomiendas', function ($out, array $report): void {
            fputcsv($out, CsvCell::row([
                __('reports.csv_count'), __('reports.csv_kg'), __('reports.csv_amount'), __('reports.csv_due'),
            ]));
            $parcels = $report['parcels'];
            fputcsv($out, CsvCell::row([
                (string) $parcels['count'], $parcels['kg'], $this->reports->signed($parcels['cents']), $this->reports->signed($parcels['due_cents']),
            ]));
        });
    }

    public function cash(Request $request): StreamedResponse
    {
        return $this->csv($request, 'cajas', function ($out, array $report): void {
            fputcsv($out, CsvCell::row([__('reports.csv_seller'), __('reports.csv_sessions'), __('reports.csv_difference')]));
            foreach ($report['cash']['rows'] as $row) {
                fputcsv($out, CsvCell::row([$row['seller'], (string) $row['sessions'], $this->reports->signed($row['cents'])]));
            }
        });
    }

    /**
     * @param  callable(resource, array<string, mixed>): void  $write
     */
    private function csv(Request $request, string $name, callable $write): StreamedResponse
    {
        $organization = CompanyTeam::organization($request);
        [$view, $from, $to] = $this->filters($request);
        $report = $this->reports->report($organization, $view, $from, $to);
        $file = 'reportes-'.$name.'-'.$report['range']['from'].'.csv';

        return response()->streamDownload(function () use ($write, $report): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write($out, $report);
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function filters(Request $request): array
    {
        $view = (string) $request->query('vista', 'mes');

        return [$view, (string) $request->query('desde', ''), (string) $request->query('hasta', '')];
    }
}
