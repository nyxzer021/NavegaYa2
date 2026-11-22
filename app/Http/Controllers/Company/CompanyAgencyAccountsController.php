<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyLot;
use App\Models\AgencyLotPayment;
use App\Services\AgencyAccounts;
use App\Support\CompanyTeam;
use App\Support\CsvCell;
use App\Support\DepartureSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyAgencyAccountsController extends Controller
{
    public function __construct(private readonly AgencyAccounts $accounts) {}

    public function index(Request $request): View
    {
        $organization = CompanyTeam::organization($request);
        $agencyId = $request->integer('agencia');
        if ($agencyId > 0 && ! Agency::query()->whereKey($agencyId)->where('organization_id', $organization->id)->exists()) {
            $agencyId = 0;
        }

        return view('company.agency-accounts.index', [
            'organization' => $organization,
            'agencies' => Agency::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name']),
            'agencyId' => $agencyId,
            'statement' => $this->accounts->statement($organization, $agencyId > 0 ? $agencyId : null),
            'money' => $this->accounts,
            'today' => now()->timezone(DepartureSchedule::ZONE)->toDateString(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $organization = CompanyTeam::organization($request);
        $statement = $this->accounts->statement($organization);
        $name = 'cuentas-agencias-'.now()->timezone(DepartureSchedule::ZONE)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($statement): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvCell::row([
                __('agency_accounts.csv_agency'), __('agency_accounts.csv_when'), __('agency_accounts.csv_route'),
                __('agency_accounts.csv_assigned'), __('agency_accounts.csv_sold'), __('agency_accounts.csv_released'),
                __('agency_accounts.csv_unit'), __('agency_accounts.csv_amount'), __('agency_accounts.csv_paid'),
                __('agency_accounts.csv_balance'), __('agency_accounts.csv_status'),
            ]));
            foreach ($statement['agencies'] as $group) {
                foreach ($group['lots'] as $row) {
                    $lot = $row['lot'];
                    $quote = $row['quote'];
                    fputcsv($out, CsvCell::row([
                        $group['agency']->name,
                        DepartureSchedule::show($lot->departure?->departure_at, 'Y-m-d H:i'),
                        $this->routeLabel($lot),
                        (string) $quote['assigned'],
                        (string) $quote['sold'],
                        (string) $quote['released'],
                        $this->accounts->decimal($this->accounts->cents((string) $lot->unit_price)),
                        $this->accounts->decimal($quote['amount_cents']),
                        $this->accounts->decimal($quote['paid_cents']),
                        $this->accounts->decimal($quote['balance_cents']),
                        __('agency_accounts.status_'.$quote['status']),
                    ]));
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, AgencyLot $agencyLot): RedirectResponse
    {
        $this->ownedLot($request, $agencyLot);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', 'string', 'max:40'],
            'operation_number' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->accounts->record(
            $request,
            $agencyLot,
            (string) $data['amount'],
            $data['paid_on'],
            trim($data['method']),
            trim($data['operation_number']),
            $data['note'] ?? null,
        );

        return back()->with('success', __('agency_accounts.recorded'));
    }

    public function void(Request $request, AgencyLotPayment $agencyLotPayment): RedirectResponse
    {
        $this->ownedPayment($request, $agencyLotPayment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->accounts->void($request, $agencyLotPayment, $data['reason']);

        return back()->with('success', __('agency_accounts.voided_flash'));
    }

    private function ownedLot(Request $request, AgencyLot $lot): void
    {
        $lot->loadMissing('agency');
        abort_unless((int) $lot->agency?->organization_id === (int) CompanyTeam::organization($request)->id, 404);
    }

    private function ownedPayment(Request $request, AgencyLotPayment $payment): void
    {
        abort_unless((int) $payment->organization_id === (int) CompanyTeam::organization($request)->id, 404);
    }

    private function routeLabel(AgencyLot $lot): string
    {
        $origin = $lot->departure?->transportRoute?->originPort?->name;
        $destination = $lot->departure?->transportRoute?->destinationPort?->name;
        if ($origin === null && $destination === null) {
            return '';
        }

        return ($origin ?: __('audit.unknown_place')).' → '.($destination ?: __('audit.unknown_place'));
    }
}
