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
            'vessels' => Vessel::with('organization')
                ->when($request->filled('organization_id'), fn ($query) => $query->where('organization_id', $request->integer('organization_id')))
                ->orderBy('name')->paginate(15)->withQueryString(),
            'aircraft' => Aircraft::with('organization')->orderBy('name')->get(),
        ]);
    }

    public function verifyVessel(Request $request, Vessel $vessel): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:ready,maintenance,inactive']]);
        $vessel->update($data);

        return back()->with('success', 'Estado de cumplimiento de la unidad actualizado.');
    }
}
