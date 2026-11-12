<?php

namespace App\Http\Controllers;

use App\Models\AirRoute;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\TransportRoute;
use App\Support\AdminScope;
use App\Support\RouteStops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyRouteController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();
        $modalities = $this->routeModalities($organization);

        return view('company.routes.index', [
            'organization' => $organization,
            'modalities' => $modalities,
            'officialRoutes' => MasterRoute::query()
                ->with(['originPort', 'destinationPort'])
                ->where('status', 'active')
                ->whereIn('modality', $modalities)
                ->orderBy('origin_city')
                ->orderBy('destination_city')
                ->get(),
            'riverRoutes' => $organization->transportRoutes()->with(['originPort', 'destinationPort', 'masterRoute', 'stops.port'])->orderBy('code')->get(),
            'airRoutes' => $organization->airRoutes()->with(['masterRoute', 'stops.port'])->orderBy('code')->get(),
            'requests' => MasterRoute::query()
                ->with(['originPort', 'destinationPort'])
                ->where('requested_organization_id', $organization->id)
                ->whereIn('status', ['pending', 'rejected'])
                ->orderByDesc('updated_at')
                ->get(),
            'ports' => $organization->catalogPorts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'master_route_id' => ['required', 'integer'],
            'duration_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'stops' => ['nullable', 'array', 'max:12'],
            'stops.*.port_id' => ['nullable', 'integer'],
            'stops.*.hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'stops.*.minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
        ]);
        $duration = $this->duration($data['duration_hours'], $data['duration_minutes']);
        $master = MasterRoute::query()
            ->where('status', 'active')
            ->whereIn('modality', $this->routeModalities($organization))
            ->find($data['master_route_id']);
        if (! $master) {
            throw ValidationException::withMessages([
                'master_route_id' => 'Elige un tramo oficial aprobado.',
            ]);
        }

        $alreadyUsed = $master->modality === 'aereo'
            ? $organization->airRoutes()->where('master_route_id', $master->id)->exists()
            : $organization->transportRoutes()->where('master_route_id', $master->id)->exists();
        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'master_route_id' => 'Esa ruta ya está registrada.',
            ]);
        }

        DB::transaction(function () use ($organization, $master, $duration, $data): void {
            $route = $master->modality === 'aereo'
                ? AirRoute::create([
                    'organization_id' => $organization->id,
                    'master_route_id' => $master->id,
                    'origin_city' => $master->origin_city,
                    'destination_city' => $master->destination_city,
                    'estimated_duration_minutes' => $duration,
                    'status' => 'active',
                ])
                : TransportRoute::create([
                    'organization_id' => $organization->id,
                    'master_route_id' => $master->id,
                    'origin_port_id' => $master->origin_port_id,
                    'destination_port_id' => $master->destination_port_id,
                    'estimated_duration_minutes' => $duration,
                    'status' => 'active',
                ]);

            RouteStops::replace(
                $route,
                $data['stops'] ?? [],
                $duration,
                (int) $master->origin_port_id,
                (int) $master->destination_port_id,
                $master->modality,
            );
        });

        return back()->with('success', 'Ruta registrada. El pasaje se vende de origen a destino.');
    }

    public function requestSegment(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'origin_port_id' => ['required', 'integer', 'different:destination_port_id'],
            'destination_port_id' => ['required', 'integer', 'different:origin_port_id'],
            'duration_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:59'],
        ]);
        $origin = Port::query()->find($data['origin_port_id']);
        $destination = Port::query()->find($data['destination_port_id']);
        if (! $origin || ! $destination || ! $organization->acceptsCatalogPort($origin) || ! $organization->acceptsCatalogPort($destination)) {
            throw ValidationException::withMessages([
                'origin_port_id' => 'Elige un origen y un destino de tu modalidad.',
            ]);
        }
        if ($origin->publicSide() !== $destination->publicSide()) {
            throw ValidationException::withMessages([
                'destination_port_id' => 'El origen y el destino deben ser de la misma modalidad.',
            ]);
        }
        if (mb_strtolower(trim($origin->city)) === mb_strtolower(trim($destination->city))) {
            throw ValidationException::withMessages([
                'destination_port_id' => 'La ciudad de destino debe ser diferente a la ciudad de origen.',
            ]);
        }

        $duration = $this->duration($data['duration_hours'], $data['duration_minutes']);
        $existing = MasterRoute::query()
            ->where('modality', $origin->publicSide())
            ->where('origin_port_id', $origin->id)
            ->where('destination_port_id', $destination->id)
            ->first();

        if ($existing && $existing->status !== 'rejected') {
            throw ValidationException::withMessages([
                'origin_port_id' => match ($existing->status) {
                    'active' => 'Ese tramo ya está aprobado. Selecciónalo en la lista.',
                    'pending' => 'Ese tramo ya está en revisión.',
                    default => 'Ese tramo ya existe y no está disponible para una nueva solicitud.',
                },
            ]);
        }

        $attributes = [
            'modality' => $origin->publicSide(),
            'origin_city' => $origin->city,
            'destination_city' => $destination->city,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_duration_minutes' => $duration,
            'estimated_duration_text' => MasterRoute::formatDuration($duration),
            'duration_needs_review' => false,
            'status' => 'pending',
            'requested_organization_id' => $organization->id,
            'rejection_reason' => null,
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            MasterRoute::create($attributes + ['code' => 'SOL-'.Str::upper(Str::random(8))]);
        }

        return back()->with('success', 'Solicitud enviada. El tramo queda pendiente de aprobación.');
    }

    private function duration(int $hours, int $minutes): int
    {
        $total = ($hours * 60) + $minutes;
        if ($total < 1 || $total > 43200) {
            throw ValidationException::withMessages([
                'duration_hours' => 'Indica una duración entre 1 minuto y 720 horas.',
            ]);
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    private function routeModalities(Organization $organization): array
    {
        $codes = $organization->approvedModalityCodes();
        $modalities = [];
        if (in_array('fluvial', $codes, true) || in_array('aereo_fluvial', $codes, true)) {
            $modalities[] = 'fluvial';
        }
        if (in_array('aereo', $codes, true)) {
            $modalities[] = 'aereo';
        }

        return $modalities === [] ? ['fluvial'] : $modalities;
    }

    private function organization(): Organization
    {
        return Organization::query()
            ->whereKey(AdminScope::organizationId(auth()->user()))
            ->where('type', 'transport_company')
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();
    }
}
