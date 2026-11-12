<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Vessel;
use App\Support\AuditRecorder;
use App\Support\FleetMaintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminFleetComplianceController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.fleet-compliance.index', [
            'enabledSeatCapacity' => FleetMaintenance::enabledSeatCapacity(),
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
            'review_notes' => ['required', 'string', 'max:2000'],
        ]);
        abort_unless($vessel->status === 'pending_review', 422, 'La embarcación no está pendiente de revisión.');
        $status = match ($data['decision']) {
            'approve' => 'ready',
            'observe' => 'changes_requested',
            'reject' => 'rejected',
        };
        $vessel->update([
            'status' => $status,
            'review_notes' => $data['review_notes'],
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        AuditRecorder::record($request, 'fleet_unit_reviewed', $vessel, [
            'decision' => $data['decision'],
            'reason' => $data['review_notes'],
        ]);

        return back()->with('success', $status === 'ready' ? 'Embarcación aprobada y habilitada para programar salidas.' : 'Revisión registrada y visible para la empresa.');
    }

    public function verifyAircraft(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,observe,reject'],
            'review_notes' => ['required', 'string', 'max:2000'],
        ]);
        abort_unless($aircraft->status === 'pending_review', 422, 'La aeronave no está pendiente de revisión.');
        $status = match ($data['decision']) {
            'approve' => 'ready',
            'observe' => 'changes_requested',
            'reject' => 'rejected',
        };
        $aircraft->update([
            'status' => $status,
            'review_notes' => $data['review_notes'],
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);
        AuditRecorder::record($request, 'fleet_unit_reviewed', $aircraft, [
            'decision' => $data['decision'],
            'reason' => $data['review_notes'],
        ]);

        return back()->with('success', __('admin_portal.fleet.aircraft_reviewed', [], 'es'));
    }

    public function suspendVessel(Request $request, Vessel $vessel): RedirectResponse
    {
        return $this->suspend($request, $vessel);
    }

    public function suspendAircraft(Request $request, Aircraft $aircraft): RedirectResponse
    {
        return $this->suspend($request, $aircraft);
    }

    private function suspend(Request $request, Vessel|Aircraft $unit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $unit->update([
            'status' => 'suspended',
            'suspension_reason' => $data['reason'],
            'suspended_at' => now(),
            'suspended_by' => $request->user()->id,
        ]);
        AuditRecorder::record($request, 'fleet_unit_suspended', $unit, ['reason' => $data['reason']]);

        return back()->with('success', __('admin_portal.fleet.suspended', [], 'es'));
    }
}
