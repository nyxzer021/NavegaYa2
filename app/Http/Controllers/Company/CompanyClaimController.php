<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ConsumerClaim;
use App\Services\ConsumerClaims;
use App\Support\CompanyTeam;
use App\Support\CsvCell;
use App\Support\DepartureSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyClaimController extends Controller
{
    public function __construct(private readonly ConsumerClaims $claims) {}

    public function index(Request $request): View
    {
        $organization = CompanyTeam::organization($request);

        return view('company.claims.index', [
            'organization' => $organization,
            'claims' => $this->rows($organization->id),
            'respondRoute' => 'company.claims.respond',
            'exportRoute' => route('company.claims.export'),
            'canAnswerCompany' => true,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $organization = CompanyTeam::organization($request);
        $claims = $this->rows($organization->id);
        $name = 'libro-reclamaciones-'.now()->timezone(DepartureSchedule::ZONE)->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($claims): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvCell::row($this->headers()));
            foreach ($claims as $claim) {
                fputcsv($out, CsvCell::row($this->line($claim)));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function respond(Request $request, ConsumerClaim $consumerClaim): RedirectResponse
    {
        $organization = CompanyTeam::organization($request);
        abort_unless($consumerClaim->provider === 'company' && (int) $consumerClaim->organization_id === (int) $organization->id, 404);
        $data = $request->validate(['response' => ['required', 'string', 'min:10', 'max:4000']]);
        $this->claims->answer($consumerClaim, $request->user(), $data['response']);

        return back()->with('success', __('claims.answered_flash'));
    }

    /**
     * @return Collection<int, ConsumerClaim>
     */
    private function rows(int $organizationId)
    {
        return ConsumerClaim::query()
            ->where('provider', 'company')
            ->where('organization_id', $organizationId)
            ->with('organization')
            ->orderByDesc('filed_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            __('claims.csv_code'), __('claims.csv_date'), __('claims.csv_provider'), __('claims.csv_company'),
            __('claims.csv_consumer'), __('claims.csv_document'), __('claims.csv_phone'), __('claims.csv_email'),
            __('claims.csv_address'), __('claims.csv_type'), __('claims.csv_good'), __('claims.csv_amount'),
            __('claims.csv_detail'), __('claims.csv_request'), __('claims.csv_due'), __('claims.csv_response'),
        ];
    }

    /**
     * @return list<string>
     */
    private function line(ConsumerClaim $claim): array
    {
        $sheet = $this->claims->sheet($claim);

        return [
            $claim->code,
            $sheet['date'],
            __('claims.provider_'.$claim->provider),
            $sheet['provider_name'],
            $claim->consumer_name,
            $sheet['document'],
            $claim->phone,
            $claim->email,
            $claim->address,
            $sheet['type'],
            $claim->good_description,
            $sheet['amount'],
            $claim->detail,
            $claim->request_text,
            $sheet['due'],
            (string) ($claim->response_text ?? ''),
        ];
    }
}
