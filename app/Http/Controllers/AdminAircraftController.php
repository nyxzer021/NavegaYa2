<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\Organization;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAircraftController extends Controller
{
    private function organizations()
    {
        $scope = AdminScope::organizationId(auth()->user());

        return Organization::where('type', 'transport_company')
            ->where('status', 'active')
            ->when($scope, fn ($query) => $query->whereKey($scope))
            ->orderBy('legal_name')
            ->get();
    }

    public function index(): View
    {
        $scope = AdminScope::organizationId(auth()->user());

        return view('admin.aircraft.index', [
            'aircraft' => Aircraft::with('organization')
                ->when($scope, fn ($query) => $query->where('organization_id', $scope))
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $organizations = $this->organizations();
        $organization = $organizations->firstWhere('id', $request->integer('organization_id'))
            ?? ($organizations->count() === 1 ? $organizations->first() : null);

        abort_unless($organization, 404, 'Selecciona una empresa transportista válida desde su expediente.');

        return view('admin.aircraft.form', [
            'aircraft' => new Aircraft,
            'organization' => $organization,
            'creating' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $scope = AdminScope::organizationId(auth()->user());

        if ($scope) {
            $data['organization_id'] = $scope;
        }

        abort_unless($this->organizations()->contains('id', (int) $data['organization_id']), 403);

        $aircraft = Aircraft::create($data);

        return redirect()
            ->route('admin.companies.show', ['organization' => $aircraft->organization_id, 'tab' => 'flota'])
            ->with('success', 'Aeronave registrada correctamente.');
    }

    public function edit(Aircraft $aircraft): View
    {
        $this->authorizeAircraft($aircraft);
        $aircraft->load('organization');

        return view('admin.aircraft.form', [
            'aircraft' => $aircraft,
            'organization' => $aircraft->organization,
            'creating' => false,
        ]);
    }

    public function update(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $this->authorizeAircraft($aircraft);
        $data = $this->validated($request, $aircraft);

        if (AdminScope::organizationId(auth()->user())) {
            unset($data['organization_id']);
        } else {
            abort_unless($this->organizations()->contains('id', (int) $data['organization_id']), 403);
        }

        $aircraft->update($data);

        return redirect()
            ->route('admin.companies.show', ['organization' => $aircraft->organization_id, 'tab' => 'flota'])
            ->with('success', 'Aeronave actualizada correctamente.');
    }

    public function seats(Aircraft $aircraft): View
    {
        $this->authorizeAircraft($aircraft);

        return view('admin.aircraft.seats', compact('aircraft'));
    }

    public function generateSeats(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $this->authorizeAircraft($aircraft);

        $data = $request->validate([
            'rows' => ['required', 'integer', 'min:1', 'max:60'],
            'columns' => ['required', 'integer', 'min:1', 'max:8'],
            'cabin' => ['nullable', 'string', 'max:40'],
        ]);

        $aircraft->seats()->delete();

        for ($row = 1; $row <= $data['rows']; $row++) {
            for ($column = 1; $column <= $data['columns']; $column++) {
                $aircraft->seats()->create([
                    'cabin' => $data['cabin'] ?: 'Económica',
                    'code' => $row.chr(64 + $column),
                    'row_position' => $row,
                    'column_position' => $column,
                ]);
            }
        }

        return back()->with('success', 'Plano de asientos generado.');
    }

    private function authorizeAircraft(Aircraft $aircraft): void
    {
        if ($scope = AdminScope::organizationId(auth()->user())) {
            abort_unless($aircraft->organization_id === $scope, 403);
        }
    }

    private function validated(Request $request, ?Aircraft $aircraft = null): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:120'],
            'registration_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('aircraft', 'registration_number')->ignore($aircraft?->id),
            ],
            'model' => ['nullable', 'string', 'max:120'],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:900'],
            'status' => ['required', Rule::in(['ready', 'maintenance', 'inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'cover_image_path' => ['nullable', 'url', 'max:2048'],
        ]);
    }
}
