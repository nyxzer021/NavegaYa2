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
use App\Support\AuditRecorder;
use App\Support\CompanyPermissions;
use App\Support\CsvCell;
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
        $oversight = CompanyPermissions::viewsAllCash($user);
        $operate = CompanyPermissions::operatesCash($user);
        $organization = $this->organization($request);
        $day = $this->day($request);
        [$start, $end] = DepartureSchedule::dayBounds($day);
        $sites = $this->sites($user, $organization->id);
        $open = CashSession::query()
            ->where('organization_id', $organization->id)
            ->whereNull('closed_at')
            ->when(! $oversight, fn ($query) => $query->where('user_id', $user->id))
            ->with(['user', 'site'])
            ->orderBy('opened_at')
            ->get();
        $closed = CashSession::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('closed_at')
            ->whereBetween('opened_at', [$start, $end])
            ->when(! $oversight, fn ($query) => $query->where('user_id', $user->id))
            ->when($request->filled('sede'), fn ($query) => $query->where('organization_site_id', $request->integer('sede')))
            ->when($request->filled('vendedor') && $oversight, fn ($query) => $query->where('user_id', $request->integer('vendedor')))
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
            'loose' => $oversight ? $this->loose($organization->id, $start, $end) : collect(),
            'sites' => $sites,
            'availableSites' => $available,
            'canOpen' => $operate && ($sites->isEmpty() ? $open->where('user_id', $user->id)->isEmpty() : $available->isNotEmpty()),
            'canOperate' => $operate,
            'sellers' => $oversight
                ? User::query()->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organization->id))->orderBy('name')->get(['users.id', 'users.name'])
                : collect(),
            'day' => $day->timezone(DepartureSchedule::ZONE)->toDateString(),
            'admin' => $oversight,
            'daily' => $oversight ? $this->daily($open, $closed, $summaries, $start, $end) : null,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        abort_unless(CompanyPermissions::operatesCash($request->user()), 403);
        $organization = $this->organization($request);
        $data = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
            'site_id' => ['nullable', 'integer'],
        ], [
            'opening_float.required' => 'Indica el fondo inicial.',
        ]);
        try {
            $session = $this->desk->open($request->user(), $organization->id, $data['site_id'] ?? null, (float) $data['opening_float']);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }
        $session->load('site');
        AuditRecorder::record($request, 'cash.opened', $session, [
            'float' => (string) $session->opening_float,
            'site' => $session->site?->name,
        ]);

        return redirect()->route('company.cash-close.index')->with('success', __('cash.opened'));
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        abort_unless(CompanyPermissions::operatesCash($request->user()), 403);
        $this->organization($request);
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:255'],
        ], [
            'counted_cash.required' => 'Indica el efectivo contado.',
        ]);
        try {
            $session = $this->desk->close($request->user(), $cashSession, (float) $data['counted_cash'], $data['closing_note'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }
        AuditRecorder::record($request, 'cash.closed', $session, [
            'expected' => (string) $session->expected_cash,
            'counted' => (string) $session->counted_cash,
            'difference' => (string) $session->difference,
        ]);

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
            ->when(! CompanyPermissions::viewsAllCash($user), fn ($query) => $query->where('user_id', $user->id))
            ->with(['user', 'site', 'payments.collector', 'payments.reservation.soldBy', 'payments.cargoShipment'])
            ->orderBy('opened_at')
            ->get();
        $name = 'cierre-'.$day->timezone(DepartureSchedule::ZONE)->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($sessions): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Turno', 'Abierto', 'Cerrado', 'Sede', 'Vendedor', 'Referencia', 'Propósito', 'Método', 'Monto', 'Efectivo neto', 'Operación', 'Estado', 'Nota de ajuste', 'Fondo', 'Esperado', 'Contado', 'Diferencia', 'Nota de cierre']);
            foreach ($sessions as $session) {
                $payments = $session->payments
                    ->filter(fn (Payment $payment): bool => in_array($payment->status, ['confirmed', 'voided'], true))
                    ->sortBy('id');
                foreach ($payments as $payment) {
                    fputcsv($out, CsvCell::row($this->paymentRow($session, $payment)));
                }
                fputcsv($out, CsvCell::row($this->summaryRow($session)));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<int|string|null>
     */
    private function paymentRow(CashSession $session, Payment $payment): array
    {
        $voided = $payment->voided_at !== null || $payment->status === 'voided';
        $net = $payment->method !== 'cash'
            ? ''
            : ($voided ? $this->money(0) : $this->money((float) ($payment->cash_tendered ?? 0) - (float) ($payment->cash_change ?? 0)));
        $note = $payment->adjustment_note;
        if (blank($note) && $voided) {
            $note = $payment->reservation?->void_reason;
        }

        return [
            $session->id,
            $this->csvMoment($session->opened_at),
            $session->closed_at ? $this->csvMoment($session->closed_at) : '',
            $session->site?->name,
            $payment->collector?->name ?: $payment->reservation?->soldBy?->name ?: $session->user?->name,
            $payment->reservation?->code ?: $payment->cargoShipment?->code,
            $payment->purpose === 'cargo' ? 'encomienda' : 'pasaje',
            $this->methodLabel((string) $payment->method),
            $this->money((float) $payment->amount),
            $net,
            $payment->operation_number,
            $voided ? 'anulado' : ($this->desk->cents((float) $payment->amount) < 0 ? 'ajuste' : 'confirmado'),
            $note,
            '',
            '',
            '',
            '',
            '',
        ];
    }

    /**
     * @return list<int|string|null>
     */
    private function summaryRow(CashSession $session): array
    {
        $closed = $session->closed_at !== null;

        return [
            $session->id,
            $this->csvMoment($session->opened_at),
            $closed ? $this->csvMoment($session->closed_at) : '',
            $session->site?->name,
            $session->user?->name,
            'Resumen',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $this->money((float) $session->opening_float),
            $this->money($closed ? (float) $session->expected_cash : $this->desk->expectedCash($session)),
            $closed ? $this->money((float) $session->counted_cash) : '',
            $closed ? $this->money((float) $session->difference) : '',
            $session->closing_note,
        ];
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'efectivo',
            'yape' => 'yape',
            'plin' => 'plin',
            'transfer' => 'transferencia',
            'card' => 'tarjeta',
            default => $method,
        };
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function csvMoment(mixed $moment): string
    {
        return DepartureSchedule::show($moment, 'Y-m-d H:i');
    }

    /**
     * @param  Collection<int, CashSession>  $open
     * @param  Collection<int, CashSession>  $closed
     * @param  array<int, array{purposes: array<string, float>, methods: array<string, float>, sellers: array<string, float>, operations: list<array{method: string, number: string, amount: float, purpose: string}>, commission: float, expected: float}>  $summaries
     * @return array{purposes: array<string, float>, methods: array<string, float>, sellers: array<string, float>, operations: array{}, commission: float, closed: array{expected: float, counted: float, difference: float}|null, open: array{expected: float}|null}|null
     */
    private function daily(Collection $open, Collection $closed, array $summaries, Carbon $start, Carbon $end): ?array
    {
        $openedToday = $open->filter(
            fn (CashSession $session): bool => $session->opened_at !== null
                && $session->opened_at->greaterThanOrEqualTo($start)
                && $session->opened_at->lessThanOrEqualTo($end),
        )->values();
        $sessions = $closed->concat($openedToday)->unique('id')->values();
        if ($sessions->isEmpty()) {
            return null;
        }

        $purposes = [];
        $methods = [];
        $sellers = [];
        $commission = 0;
        $closedExpected = 0;
        $counted = 0;
        $difference = 0;
        $openExpected = 0;
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
            $commission += (int) round($summary['commission'] * 100);
            if ($session->closed_at !== null) {
                $closedExpected += (int) round(((float) $session->expected_cash) * 100);
                $counted += (int) round(((float) $session->counted_cash) * 100);
                $difference += (int) round(((float) $session->difference) * 100);
            }
        }
        foreach ($openedToday as $session) {
            $openExpected += (int) round($summaries[$session->id]['expected'] * 100);
        }

        $money = fn (array $cents): array => array_map(fn (int $cents): float => round($cents / 100, 2), $cents);

        return [
            'purposes' => $money($purposes),
            'methods' => $money($methods),
            'sellers' => $money($sellers),
            'operations' => [],
            'commission' => round($commission / 100, 2),
            'closed' => $closed->isEmpty() ? null : [
                'expected' => round($closedExpected / 100, 2),
                'counted' => round($counted / 100, 2),
                'difference' => round($difference / 100, 2),
            ],
            'open' => $openedToday->isEmpty() ? null : [
                'expected' => round($openExpected / 100, 2),
            ],
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
                'site' => $shipment->receivedSite?->name ?: $shipment->site?->name ?: __('cash.no_site'),
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
