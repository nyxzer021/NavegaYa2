<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreVesselRequest;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Vessel;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminVesselController extends Controller
{
    public function index(): View
    {
        $vessels = Vessel::query()->with(['organization', 'basePort'])->withCount('seats')->when(AdminScope::organizationId(auth()->user()), fn ($query, $id) => $query->where('organization_id', $id))->latest()->paginate(12);

        return view('admin.vessels.index', compact('vessels'));
    }

    public function create(): View
    {
        return view('admin.vessels.create', $this->formData());
    }

    public function store(StoreVesselRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            $data['organization_id'] = $organizationId;
        }
        $vessel = Vessel::create($data + ['status' => 'draft']);

        return redirect()->route('admin.vessels.edit', $vessel)->with('success', 'Embarcación registrada. Completa o revisa su ficha técnica y luego configura el plano de asientos.');
    }

    public function edit(Vessel $vessel): View
    {
        return view('admin.vessels.edit', $this->formData(['vessel' => $vessel]));
    }

    public function update(StoreVesselRequest $request, Vessel $vessel): RedirectResponse
    {
        $data = $request->validated();
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            $data['organization_id'] = $organizationId;
        }
        $vessel->update($data);

        return redirect()->route('admin.vessels.edit', $vessel)->with('success', 'Ficha de la embarcación actualizada correctamente.');
    }

    private function formData(array $extra = []): array
    {
        return $extra + [
            'organizations' => Organization::query()->where('type', 'transport_company')->where('status', 'active')->when(AdminScope::organizationId(auth()->user()), fn ($query, $id) => $query->whereKey($id))->orderBy('legal_name')->get(),
            'ports' => Port::query()->where('is_active', true)->orderBy('city')->orderBy('name')->get(),
        ];
    }
}
