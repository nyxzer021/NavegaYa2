<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\View\View;

class AdminRefundController extends Controller
{
    public function index(): View
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
        ]);
    }
}
