<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Vessel;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyFleetController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();
        $organization->loadMissing('vessels.basePort');

        return view('company.fleet.index', [
            'organization' => $organization,
            'vessels' => Vessel::with(['basePort'])->where('organization_id', $organization->id)->latest()->get(),
            'aircraft' => Aircraft::where('organization_id', $organization->id)->latest()->get(),
            'ports' => Port::where('is_active', true)->orderBy('city')->orderBy('name')->get(),
        ]);
    }

    public function storeVessel(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'registration_number' => ['required', 'string', 'max:80', 'unique:vessels,registration_number'],
            'vessel_type' => ['required', Rule::in(['lancha', 'motonave', 'rapido', 'ferry', 'otro'])],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:2000'], 'base_port_id' => ['nullable', 'exists:ports,id'],
        ]);
        Vessel::create($data + ['organization_id' => $organization->id, 'status' => 'draft']);

        return back()->with('success', 'Embarcación registrada. Completa sus permisos y plano de asientos antes de operar.');
    }

    public function storeAircraft(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'registration_number' => ['required', 'string', 'max:80', 'unique:aircraft,registration_number'],
            'model' => ['nullable', 'string', 'max:120'], 'seat_capacity' => ['required', 'integer', 'min:1', 'max:900'],
        ]);
        Aircraft::create($data + ['organization_id' => $organization->id, 'status' => 'ready']);

        return back()->with('success', 'Aeronave registrada correctamente.');
    }

    private function organization(): Organization
    {
        return Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->whereIn('status', ['active', 'pending'])->firstOrFail();
    }
}
