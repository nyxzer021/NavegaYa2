<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TicketRefund;
use App\Services\TicketRefunds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRefundController extends Controller
{
    public function index(Request $request): View
    {
        $refunds = Payment::query()
            ->with(['reservation.seats.ticket'])
            ->whereIn('status', ['refund_requested', 'pending_refund', 'refunded', 'failed'])
            ->latest()
            ->paginate(20);

        return view('admin.refunds.index', [
            'refunds' => $refunds,
            'pendingCount' => Payment::whereIn('status', ['refund_requested', 'pending_refund'])->count(),
            'refundedTotal' => (float) Payment::where('status', 'refunded')->sum('amount'),
            'failedCount' => Payment::where('status', 'failed')->count(),
            'ticketRefunds' => TicketRefund::query()
                ->with(['organization', 'reservation', 'payment'])
                ->latest()
                ->paginate(20, ['*'], 'solicitudes'),
            'canRefundToCard' => $request->user()?->roles()->where('code', 'super_admin')->exists() === true,
            'culqiRefundsOpen' => (bool) config('services.culqi.enabled') && filled(config('services.culqi.secret_key')),
            'mercadoPagoRefundsOpen' => filter_var(config('services.mercadopago.enabled'), FILTER_VALIDATE_BOOLEAN) && filled(config('services.mercadopago.access_token')),
        ]);
    }

    public function refundToCard(Request $request, TicketRefund $ticketRefund, TicketRefunds $refunds): RedirectResponse
    {
        $refunds->refundToCard($request, $ticketRefund);

        return back()->with('success', __('refunds.card_done'));
    }

    public function payByOtherMeans(Request $request, TicketRefund $ticketRefund, TicketRefunds $refunds): RedirectResponse
    {
        $data = $request->validate(['operation_number' => ['required', 'string', 'max:80']]);
        $refunds->payByOtherMeans($request, $ticketRefund, $data['operation_number']);

        return back()->with('success', __('refunds.paid_flash'));
    }
}
