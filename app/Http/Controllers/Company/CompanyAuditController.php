<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\CompanyAudit;
use App\Support\CompanyTeam;
use App\Support\CsvCell;
use App\Support\DepartureSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyAuditController extends Controller
{
    public function index(Request $request): View
    {
        $organization = CompanyTeam::organization($request);
        $logs = CompanyAudit::query($organization, $request)->paginate(20)->withQueryString();
        [$start, $end] = DepartureSchedule::dayBounds();
        $base = AuditLog::query()->where('organization_id', $organization->id);

        return view('company.audit.index', [
            'organization' => $organization,
            'logs' => $logs,
            'employees' => User::query()
                ->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organization->id))
                ->orderBy('name')
                ->get(['users.id', 'users.name']),
            'kinds' => array_keys(CompanyAudit::kinds()),
            'filters' => [
                'empleado' => $request->integer('empleado') ?: '',
                'tipo' => (string) $request->query('tipo', ''),
                'desde' => (string) $request->query('desde', ''),
                'hasta' => (string) $request->query('hasta', ''),
            ],
            'counts' => [
                'today' => (clone $base)->whereBetween('created_at', [$start, $end])->count(),
                'month' => (clone $base)->where('created_at', '>=', now()->timezone(DepartureSchedule::ZONE)->startOfMonth()->utc())->count(),
                'listed' => $logs->total(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $organization = CompanyTeam::organization($request);
        $logs = CompanyAudit::query($organization, $request)->get();
        $name = 'auditoria-'.now()->timezone(DepartureSchedule::ZONE)->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($logs): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvCell::row([__('audit.csv_when'), __('audit.csv_who'), __('audit.csv_what'), __('audit.csv_ip')]));
            foreach ($logs as $log) {
                fputcsv($out, CsvCell::row([
                    DepartureSchedule::show($log->created_at, 'Y-m-d H:i'),
                    $log->user?->name ?: __('audit.someone'),
                    CompanyAudit::present($log)['sentence'],
                    $log->ip_address ?: '',
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
