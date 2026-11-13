<?php

namespace App\Http\Controllers;

use App\Models\CrewMember;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Support\AdminScope;
use App\Support\CrewRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyCrewController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();
        $roles = $this->rolesFor($organization);

        return view('company.crew.index', [
            'organization' => $organization,
            'roles' => $roles,
            'seesContact' => CrewRoster::seesContact(auth()->user()),
            'members' => CrewMember::query()->where('organization_id', $organization->id)->orderBy('name')->get(),
            'departures' => RouteDeparture::query()
                ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
                ->where('departure_at', '>=', now()->subHour())
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'assignments.member'])
                ->orderBy('departure_at')
                ->limit(30)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'document_number' => ['required', 'string', 'max:20', Rule::unique('crew_members', 'document_number')->where('organization_id', $organization->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->rolesFor($organization))],
            'license_number' => ['nullable', 'string', 'max:40'],
            'license_expires_on' => ['nullable', 'date'],
        ]);
        CrewMember::create($data + ['organization_id' => $organization->id, 'status' => 'active']);

        return redirect()->route('company.crew.index')->with('success', 'Persona registrada.');
    }

    public function destroy(CrewMember $crewMember): RedirectResponse
    {
        $organization = $this->organization();
        abort_unless((int) $crewMember->organization_id === (int) $organization->id, 404);
        $crewMember->delete();

        return redirect()->route('company.crew.index')->with('success', 'Persona retirada de la tripulación.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'departure_id' => ['required', 'integer'],
            'captain_id' => ['nullable', 'integer'],
            'crew_ids' => ['nullable', 'array'],
            'crew_ids.*' => ['integer'],
        ]);
        $departure = RouteDeparture::query()
            ->whereKey($data['departure_id'])
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
            ->firstOrFail();
        CrewRoster::sync($departure, $data['captain_id'] ?? null, $data['crew_ids'] ?? []);

        return redirect()->route('company.crew.index')->with('success', 'Tripulación asignada a la salida.');
    }

    private function organization(): Organization
    {
        abort_unless(auth()->user()->roles()->whereIn('code', ['company_admin', 'company_operator'])->exists(), 403);
        $id = AdminScope::organizationId(auth()->user());
        abort_unless($id, 403);

        return Organization::query()->whereKey($id)->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function rolesFor(Organization $organization): array
    {
        return match ($organization->modality) {
            'aereo' => ['pilot', 'copilot'],
            'mixto' => CrewMember::ROLES,
            default => ['captain', 'engineer', 'sailor'],
        };
    }
}
