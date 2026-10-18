<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Vessel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminFleetComplianceController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.fleet-compliance.index', [
            'vessels' => Vessel::with(['organization', 'seats'])
                ->when($request->filled('organization_id'), fn ($query) => $query->where('organization_id', $request->integer('organization_id')))
                ->orderByRaw("case when status = 'pending_review' then 0 else 1 end")
                ->orderByDesc('submitted_for_review_at')->orderBy('name')->paginate(15)->withQueryString(),
            'aircraft' => Aircraft::with('organization')->orderBy('name')->get(),
        ]);
    }

    public function verifyVessel(Request $request, Vessel $vessel): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,observe,reject'],
            'review_notes' => ['nullable', 'string', 'max:2000', 'required_unless:decision,approve'],
        ]);
        abort_unless($vessel->status === 'pending_review', 422, 'La embarcación no está pendiente de revisión.');
        $status = match ($data['decision']) {
            'approve' => 'ready',
            'observe' => 'changes_requested',
            'reject' => 'rejected',
        };
        $vessel->update([
            'status' => $status,
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return back()->with('success', $status === 'ready' ? 'Embarcación aprobada y habilitada para programar salidas.' : 'Revisión registrada y visible para la empresa.');
    }
}
