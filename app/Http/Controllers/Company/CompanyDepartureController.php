<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyDepartureController as BaseCompanyDepartureController;
use App\Http\Requests\Admin\StoreRouteDepartureRequest;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyDepartureController extends BaseCompanyDepartureController
{
    public function store(StoreRouteDepartureRequest $request): RedirectResponse
    {
        $this->authorizeManager();
        $organization = $this->organization();
        $data = $request->validated();
        $masterRoute = MasterRoute::query()
            ->where('status', 'active')
            ->where('modality', 'fluvial')
            ->findOrFail($data['master_route_id']);
        $vessel = Vessel::query()->findOrFail($data['vessel_id']);
        abort_unless($vessel->organization_id === $organization->id, 403);

        $route = TransportRoute::firstOrCreate(
            ['organization_id' => $organization->id, 'master_route_id' => $masterRoute->id],
            [
                'origin_port_id' => $masterRoute->origin_port_id,
                'destination_port_id' => $masterRoute->destination_port_id,
                'estimated_duration_minutes' => $this->durationMinutes($masterRoute->estimated_duration_text),
                'status' => 'active',
                'description' => 'Ruta operativa basada en el tramo maestro '.$masterRoute->code,
            ]
        );

        unset($data['master_route_id']);
        RouteDeparture::create($data + [
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'cargo_enabled' => $request->boolean('cargo_enabled'),
            'included_baggage_kg' => 25,
            'cargo_payment_location' => 'terminal',
            'status' => 'scheduled',
        ]);

        return redirect()->route('company.departures.index')->with('success', 'Salida programada correctamente.');
    }

    public function manifest(RouteDeparture $departure): View
    {
        $organization = $this->organization();
        abort_unless($departure->transportRoute()->where('organization_id', $organization->id)->exists(), 403);

        $departure->load(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization']);
        $organization->loadMissing('vessels.basePort');
        $passengers = $departure->reservations()->whereNotIn('status', ['expired', 'cancelled'])
            ->with(['seats.seat', 'seats.ticket'])->get()->flatMap->seats
            ->sortBy(fn ($seat) => ($seat->seat?->row_position ?? 0) * 100 + ($seat->seat?->column_position ?? 0))->values();

        return view('company.manifests.show', [
            'organization' => $organization,
            'departure' => $departure,
            'passengers' => $passengers,
            'destination' => $departure->transportRoute->destinationPort->city,
        ]);
    }

    private function organization(): Organization
    {
        return Organization::query()
            ->whereKey(AdminScope::organizationId(auth()->user()))
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();
    }

    private function authorizeManager(): void
    {
        abort_unless(auth()->user()->roles()->where('code', 'company_admin')->exists(), 403);
    }

    private function durationMinutes(?string $duration): ?int
    {
        if (! $duration) {
            return null;
        }

        preg_match('/(?:(\d+)\s*h)?\s*(?:(\d+)\s*m(?:in)?)?/i', $duration, $matches);
        $minutes = ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0);

        return $minutes > 0 ? $minutes : null;
    }
}
