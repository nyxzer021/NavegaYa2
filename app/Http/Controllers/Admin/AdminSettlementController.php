<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Settlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminSettlementController extends Controller
{
    public function index(): View
    {
        return view('admin.settlements.index', [
            'organizations' => Organization::query()->where('type', 'transport_company')->orderBy('commercial_name')->get(),
            'settlements' => Settlement::with('organization')->latest()->paginate(15),
            'grossVolume' => (float) Payment::where('status', 'paid')->sum('amount'),
            'netCommission' => (float) Payment::where('status', 'paid')->sum('commission_amount'),
            'pendingPayout' => (float) Organization::sum('pending_payout_balance'),
        ]);
    }

    public function storeSettlement(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'reference' => ['required', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($organization, $data): void {
            $netAmount = (float) $organization->pending_payout_balance;
            abort_if($netAmount <= 0, 422, 'La empresa no tiene saldo pendiente de liquidación.');

            Settlement::create($data + [
                'organization_id' => $organization->id,
                'gross_amount' => $netAmount,
                'commission_amount' => 0,
                'net_amount' => $netAmount,
                'status' => 'transferred',
                'transferred_at' => now(),
            ]);
            $organization->update(['pending_payout_balance' => 0]);
        });

        return back()->with('success', 'Liquidación registrada y saldo pendiente conciliado.');
    }
}
