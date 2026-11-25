<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsumerClaim;
use App\Services\ConsumerClaims;
use App\Support\CsvCell;
use App\Support\DepartureSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminClaimController extends Controller
{
    public function __construct(private readonly ConsumerClaims $claims) {}

    public function index(): View
    {
        return view('admin.claims.index', [
            'claims' => $this->rows(),
            'respondRoute' => 'admin.claims.respond',
            'exportRoute' => route('admin.claims.export'),
            'canAnswerCompany' => false,
        ]);
    }

    public function export(): StreamedResponse
    {
        $claims = $this->rows();
        $name = 'libro-reclamaciones-'.now()->timezone(DepartureSchedule::ZONE)->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($claims): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvCell::row([
                __('claims.csv_code'), __('claims.csv_date'), __('claims.csv_provider'), __('claims.csv_company'),
                __('claims.csv_consumer'), __('claims.csv_document'), __('claims.csv_phone'), __('claims.csv_email'),
                __('claims.csv_address'), __('claims.csv_type'), __('claims.csv_good'), __('claims.csv_amount'),
                __('claims.csv_detail'), __('claims.csv_request'), __('claims.csv_due'), __('claims.csv_response'),
            ]));
            foreach ($claims as $claim) {
                $sheet = $this->claims->sheet($claim);
                fputcsv($out, CsvCell::row([
                    $claim->code, $sheet['date'], __('claims.provider_'.$claim->provider), $sheet['provider_name'],
                    $claim->consumer_name, $sheet['document'], $claim->phone, $claim->email, $claim->address,
                    $sheet['type'], $claim->good_description, $sheet['amount'], $claim->detail, $claim->request_text,
                    $sheet['due'], (string) ($claim->response_text ?? ''),
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function respond(Request $request, ConsumerClaim $consumerClaim): RedirectResponse
    {
        abort_unless($consumerClaim->provider === 'platform', 403);
        $data = $request->validate(['response' => ['required', 'string', 'min:10', 'max:4000']]);
        $this->claims->answer($consumerClaim, $request->user(), $data['response']);

        return back()->with('success', __('claims.answered_flash'));
    }

    /**
     * @return Collection<int, ConsumerClaim>
     */
    private function rows()
    {
        return ConsumerClaim::query()->with('organization')->orderByDesc('filed_on')->orderByDesc('id')->get();
    }
}
