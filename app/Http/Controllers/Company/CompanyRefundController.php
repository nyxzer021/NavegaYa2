<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TicketRefund;
use App\Services\CompanyCollections;
use App\Services\TicketRefunds;
use App\Support\CompanyTeam;
use App\Support\RefundRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CompanyRefundController extends Controller
{
    public function __construct(
        private readonly TicketRefunds $refunds,
        private readonly CompanyCollections $money,
    ) {}

    public function index(Request $request): View
    {
        $organization = CompanyTeam::organization($request);
        $status = (string) $request->query('estado', '');
        $rows = TicketRefund::query()
            ->where('organization_id', $organization->id)
            ->with(['reservation', 'payment'])
            ->latest()
            ->get();
        $visible = in_array($status, ['requested', 'approved', 'rejected', 'paid'], true)
            ? $rows->where('status', $status)->values()
            : $rows;

        return view('company.refunds.index', [
            'organization' => $organization,
            'refunds' => $visible,
            'status' => $status,
            'money' => $this->money,
            'requested' => $rows->where('status', 'requested')->count(),
            'approvedCents' => $this->sum($rows, 'approved'),
            'paidCents' => $this->sum($rows, 'paid'),
            'rejected' => $rows->where('status', 'rejected')->count(),
            'cancelRule' => RefundRule::CANCEL_RULE,
            'regretRule' => RefundRule::REGRET_RULE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = CompanyTeam::organization($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $reservation = Reservation::query()->where('code', strtoupper(trim($data['code'])))->first();
        abort_unless($reservation !== null && $this->belongs($reservation, $organization->id), 404);
        if (! in_array($reservation->sales_channel, ['counter', 'agency'], true)) {
            return back()->withErrors(['code' => __('refunds.dock_only')])->withInput();
        }
        $this->refunds->request($request, $reservation, 'company', RefundRule::PASSENGER, $data['amount'] ?? null, $data['reason']);

        return back()->with('success', __('refunds.requested'));
    }

    public function approve(Request $request, TicketRefund $ticketRefund): RedirectResponse
    {
        $this->owned($request, $ticketRefund);
        $data = $request->validate(['amount' => ['nullable', 'numeric', 'min:0']]);
        $this->refunds->approve($request, $ticketRefund, $data['amount'] ?? null);

        return back()->with('success', __('refunds.approved_flash'));
    }

    public function reject(Request $request, TicketRefund $ticketRefund): RedirectResponse
    {
        $this->owned($request, $ticketRefund);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->refunds->reject($request, $ticketRefund, $data['reason']);

        return back()->with('success', __('refunds.rejected_flash'));
    }

    public function pay(Request $request, TicketRefund $ticketRefund): RedirectResponse
    {
        $this->owned($request, $ticketRefund);
        $data = $request->validate(['operation_number' => ['nullable', 'string', 'max:80']]);
        $this->refunds->pay($request, $ticketRefund, $data['operation_number'] ?? null);

        return back()->with('success', __('refunds.paid_flash'));
    }

    private function owned(Request $request, TicketRefund $refund): void
    {
        abort_unless((int) $refund->organization_id === (int) CompanyTeam::organization($request)->id, 404);
    }

    private function belongs(Reservation $reservation, int $organizationId): bool
    {
        $reservation->loadMissing('departure.transportRoute', 'airDeparture.airRoute');
        $owner = $reservation->departure?->transportRoute?->organization_id
            ?? $reservation->airDeparture?->airRoute?->organization_id;

        return (int) $owner === $organizationId;
    }

    /**
     * @param  Collection<int, TicketRefund>  $rows
     */
    private function sum($rows, string $status): int
    {
        return (int) $rows->where('status', $status)->sum(fn (TicketRefund $refund): int => $this->money->cents((string) $refund->amount));
    }
}
