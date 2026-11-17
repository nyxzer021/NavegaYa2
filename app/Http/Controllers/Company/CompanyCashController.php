<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CargoShipment;
use App\Models\CashSession;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\Payment;
use App\Models\User;
use App\Services\CashDesk;
use App\Support\AdminScope;
use App\Support\DepartureSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyCashController extends Controller
{
    public function __construct(private readonly CashDesk $desk) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $organization = $this->organization($request);
        $day = $this->day($request);
        [$start, $end] = DepartureSchedule::dayBounds($day);
        $sites = $this->sites($user, $organization->id);
        $open = CashSession::query()
            ->where('organization_id', $organization->id)
            ->whereNull('closed_at')
            ->when(! $user->isCompanyAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->with(['user', 'site'])
            ->orderBy('opened_at')
            ->get();
        $closed = CashSession::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('closed_at')
            ->whereBetween('opened_at', [$start, $end])
            ->when(! $user->isCompanyAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->when($request->filled('sede'), fn ($query) => $query->where('organization_site_id', $request->integer('sede')))
            ->when($request->filled('vendedor') && $user->isCompanyAdmin(), fn ($query) => $query->where('user_id', $request->integer('vendedor')))
            ->with(['user', 'site'])
            ->orderBy('opened_at')
            ->get();
        $summaries = [];
        foreach ($open->concat($closed) as $session) {
            $summaries[$session->id] = $this->desk->summary($session);
        }
        $taken = $open->where('user_id', $user->id)->pluck('organization_site_id');
        $available = $sites->reject(fn (OrganizationSite $site): bool => $taken->contains($site->id))->values();

        return view('company.cash.index', [
            'organization' => $organization,
            'openSessions' => $open,
            'closedSessions' => $closed,
            'summaries' => $summaries,
            'receivable' => $this->rows($this->desk->receivable($organization->id)),
            'loose' => $user->isCompanyAdmin() ? $this->loose($organization->id, $start, $end) : collect(),
            'sites' => $sites,
            'availableSites' => $available,
            'canOpen' => $sites->isEmpty() ? $open->where('user_id', $user->id)->isEmpty() : $available->isNotEmpty(),
            'sellers' => $user->isCompanyAdmin()
                ? User::query()->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organization->id))->orderBy('name')->get(['users.id', 'users.name'])
                : collect(),
            'day' => $day->timezone(DepartureSchedule::ZONE)->toDateString(),
            'admin' => $user->isCompanyAdmin(),
            'daily' => $user->isCompanyAdmin() ? $this->daily($open, $closed, $summaries, $start, $end) : null,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
            'site_id' => ['nullable', 'integer'],
        ], [
            'opening_float.required' => 'Indica el fondo inicial.',
        ]);
        try {
            $this->desk->open($request->user(), $organization->id, $data['site_id'] ?? null, (float) $data['opening_float']);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('company.cash-close.index')->with('success', __('cash.opened'));
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        $this->organization($request);
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:255'],
        ], [
            'counted_cash.required' => 'Indica el efectivo contado.',
        ]);
        try {
            $this->desk->close($request->user(), $cashSession, (float) $data['counted_cash'], $data['closing_note'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('company.cash-close.index')->with('success', __('cash.closed'));
    }

    public function print(Request $request, CashSession $cashSession): View
    {
        $organization = $this->organization($request);
        $this->desk->assertCanTouch($request->user(), $cashSession);
        $cashSession->load(['user', 'site']);

        return view('company.cash.print', [
            'organization' => $organization,
            'session' => $cashSession,
            'summary' => $this->desk->summary($cashSession),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $organization = $this->organization($request);
        $day = $this->day($request);
        [$start, $end] = DepartureSchedule::dayBounds($day);
        $sessions = CashSession::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('opened_at', [$start, $end])
            ->when(! $user->isCompanyAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->with(['user', 'site', 'payments.collector', 'payments.reservation.soldBy'])
            ->orderBy('opened_at')
            ->get();
        $name = 'cierre-'.$day->timezone(DepartureSchedule::ZONE)->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($sessions): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Turno', 'Abierto', 'Sede', 'Vendedor', 'Proposito', 'Metodo', 'Monto', 'Efectivo neto', 'Operacion', 'Nota']);
            foreach ($sessions as $session) {
                $payments = $session->payments->where('status', 'confirmed')->whereNull('voided_at');
                if ($payments->isEmpty()) {
                    fputcsv($out, [$session->id, DepartureSchedule::show($session->opened_at), $session->site?->name, $session->user?->name, '', '', '', '', '', $session->closing_note]);

                    continue;
                }
                foreach ($payments as $payment) {
                    $net = $payment->method === 'cash'
                        ? round((float) ($payment->cash_tendered ?? 0) - (float) ($payment->cash_change ?? 0), 2)
                        : '';
                    fputcsv($out, [
                        $session->id,
                        DepartureSchedule::show($session->opened_at),
                        $session->site?->name,
                        $payment->collector?->name ?: $payment->reservation?->soldBy?->name,
                        $payment->purpose === 'cargo' ? 'encomienda' : 'pasaje',
                        $payment->method,
                        $payment->amount,
                        $net,
                        $payment->operation_number,
                        $payment->adjustment_note ?: $session->closing_note,
                    ]);
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  Collection<int, CashSession>  $open
     * @param  Collection<int, CashSession>  $closed
     * @param  array<int, array{purposes: array<string, float>, methods: array<string, float>, sellers: array<string, float>, operations: list<array{method: string, number: string, amount: float, purpose: string}>, commission: float, expected: float}>  $summaries
     * @return array{purposes: array<string, float>, methods: array<string, float>, sellers: array<string, float>, operations: array{}, commission: float, expected: float, counted: float, difference: float}|null
     */
    private function daily(Collection $open, Collection $closed, array $summaries, Carbon $start, Carbon $end): ?array
    {
        $sessions = $closed->concat($open->filter(
            fn (CashSession $session): bool => $session->opened_at !== null
                && $session->opened_at->greaterThanOrEqualTo($start)
                && $session->opened_at->lessThanOrEqualTo($end),
        ))->unique('id')->values();
        if ($sessions->isEmpty()) {
            return null;
        }

        $purposes = [];
        $methods = [];
        $sellers = [];
        $expected = 0;
        $counted = 0;
        $difference = 0;
        $commission = 0;
        foreach ($sessions as $session) {
            $summary = $summaries[$session->id];
            foreach ($summary['purposes'] as $key => $amount) {
                $purposes[$key] = ($purposes[$key] ?? 0) + (int) round($amount * 100);
            }
            foreach ($summary['methods'] as $key => $amount) {
                $methods[$key] = ($methods[$key] ?? 0) + (int) round($amount * 100);
            }
            foreach ($summary['sellers'] as $key => $amount) {
                $sellers[$key] = ($sellers[$key] ?? 0) + (int) round($amount * 100);
            }
            $expected += (int) round($summary['expected'] * 100);
            $commission += (int) round($summary['commission'] * 100);
            if ($session->closed_at !== null) {
                $counted += (int) round(((float) $session->counted_cash) * 100);
                $difference += (int) round(((float) $session->difference) * 100);
            }
        }

        $money = fn (array $cents): array => array_map(fn (int $cents): float => round($cents / 100, 2), $cents);

        return [
            'purposes' => $money($purposes),
            'methods' => $money($methods),
            'sellers' => $money($sellers),
            'operations' => [],
            'commission' => round($commission / 100, 2),
            'expected' => round($expected / 100, 2),
            'counted' => round($counted / 100, 2),
            'difference' => round($difference / 100, 2),
        ];
    }

    private function organization(Request $request): Organization
    {
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);

        return Organization::query()->findOrFail($organizationId);
    }

    private function day(Request $request): Carbon
    {
        $value = $request->string('fecha')->toString();
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::parse($value, DepartureSchedule::ZONE);
        }

        return now()->timezone(DepartureSchedule::ZONE);
    }

    /**
     * @return Collection<int, OrganizationSite>
     */
    private function sites(User $user, int $organizationId): Collection
    {
        $sites = OrganizationSite::query()->where('organization_id', $organizationId)->where('is_active', true)->orderBy('name')->get();
        if ($user->organization_site_id) {
            return $sites->where('id', $user->organization_site_id)->values();
        }

        return $sites;
    }

    /**
     * @param  Collection<int, CargoShipment>  $shipments
     * @return list<array{code: string, site: string, departure: string, amount: float, days: int}>
     */
    private function rows(Collection $shipments): array
    {
        $today = now()->timezone(DepartureSchedule::ZONE)->startOfDay();

        return $shipments->map(function ($shipment) use ($today): array {
            $route = $shipment->departure?->transportRoute;
            $from = ($shipment->received_at ?? $shipment->created_at)?->copy()->timezone(DepartureSchedule::ZONE)->startOfDay();

            return [
                'code' => (string) $shipment->code,
                'site' => $shipment->site?->name ?: __('cash.no_site'),
                'departure' => $route ? trim(($route->originPort?->name ?? '').' → '.($route->destinationPort?->name ?? '')) : __('cash.no_departure'),
                'amount' => (float) $shipment->amount,
                'days' => $from ? (int) $from->diff($today)->days : 0,
            ];
        })->all();
    }

    /**
     * @return Collection<int, Payment>
     */
    private function loose(int $organizationId, Carbon $start, Carbon $end): Collection
    {
        return Payment::query()
            ->whereNull('cash_session_id')
            ->where('provider', 'pos')
            ->where('status', 'confirmed')
            ->whereNull('voided_at')
            ->whereBetween('paid_at', [$start, $end])
            ->where(function ($query) use ($organizationId): void {
                $query->whereHas('cargoShipment', fn ($cargo) => $cargo->where('organization_id', $organizationId))
                    ->orWhereHas('reservation.departure.transportRoute', fn ($route) => $route->where('organization_id', $organizationId));
            })
            ->with(['collector', 'reservation.soldBy', 'site', 'reservation'])
            ->get();
    }
}
