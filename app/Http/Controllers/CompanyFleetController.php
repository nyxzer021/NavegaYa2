<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\Vessel;
use App\Support\AdminScope;
use App\Support\CrewRoster;
use App\Support\FleetMaintenance;
use App\Support\FleetPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyFleetController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();
        $organization->loadMissing('vessels.basePort');

        return view('company.fleet.index', [
            'organization' => $organization,
            'vessels' => Vessel::with(['basePort', 'maintenancePeriods', 'seats' => fn ($query) => $query->orderBy('deck')->orderBy('row_position')->orderBy('column_position')])->where('organization_id', $organization->id)->latest()->get(),
            'aircraft' => Aircraft::with(['maintenancePeriods', 'seats' => fn ($query) => $query->orderBy('row_position')->orderBy('column_position')])->where('organization_id', $organization->id)->latest()->get(),
            'ports' => Port::with('district')->where('is_active', true)->orderBy('city')->orderBy('name')->get(),
        ]);
    }

    public function storeVessel(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        abort_unless(in_array($organization->modality ?: 'fluvial', ['fluvial', 'mixto'], true), 403, 'La empresa no tiene habilitada la operación fluvial.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'registration_number' => ['required', 'string', 'max:80', 'unique:vessels,registration_number'],
            'vessel_type' => ['required', Rule::in(['lancha', 'motonave', 'rapido', 'ferry', 'otro'])],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:2000'], 'base_port_id' => ['nullable', 'exists:ports,id'],
            'length_m' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'engine_description' => ['nullable', 'string', 'max:180'],
            'life_vest_count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cargo_capacity_tons' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'dicapi_certificate_number' => ['nullable', 'string', 'max:120'],
            'insurance_policy' => ['nullable', 'string', 'max:120'],
            'insurance_expires_at' => ['nullable', 'date'],
            'inspection_expires_at' => ['nullable', 'date'],
        ]);
        if ($request->filled('life_vest_count') && (int) $data['life_vest_count'] < (int) $data['seat_capacity']) {
            return back()->withInput()->withErrors(['life_vest_count' => __('company_portal.fleet.vests_short', [], 'es')]);
        }
        $vessel = Vessel::create($data + ['organization_id' => $organization->id, 'status' => 'draft']);

        return redirect()->route('company.vessels.seats.edit', $vessel)
            ->with('success', 'Embarcación registrada. Ahora configura la distribución de sus asientos.');
    }

    public function storeAircraft(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        abort_unless(in_array($organization->modality ?: 'fluvial', ['aereo', 'mixto'], true), 403, 'La empresa no tiene habilitada la operación aérea.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'registration_number' => ['required', 'string', 'max:80', 'unique:aircraft,registration_number'],
            'model' => ['nullable', 'string', 'max:120'], 'seat_capacity' => ['required', 'integer', 'min:1', 'max:900'],
            'aircraft_kind' => ['nullable', Rule::in(['light_aircraft', 'seaplane'])],
            'max_takeoff_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'useful_load_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_baggage_kg_per_passenger' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'dgac_certificate_number' => ['nullable', 'string', 'max:120'],
            'airworthiness_certificate_number' => ['nullable', 'string', 'max:120'],
            'airworthiness_expires_at' => ['nullable', 'date'],
            'aviation_policy' => ['nullable', 'string', 'max:120'],
            'aviation_policy_expires_at' => ['nullable', 'date'],
        ]);
        Aircraft::create($data + ['organization_id' => $organization->id, 'status' => 'draft']);

        return back()->with('success', 'Aeronave registrada correctamente.');
    }

    public function updateVessel(Request $request, Vessel $vessel): RedirectResponse
    {
        $organization = $this->organization();
        abort_unless($vessel->organization_id === $organization->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'registration_number' => ['required', 'string', 'max:80', Rule::unique('vessels', 'registration_number')->ignore($vessel->id)],
            'vessel_type' => ['required', Rule::in(['lancha', 'motonave', 'rapido', 'ferry', 'otro'])],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:2000'],
            'base_port_id' => ['nullable', 'exists:ports,id'],
            'length_m' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'engine_description' => ['nullable', 'string', 'max:180'],
            'life_vest_count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cargo_capacity_tons' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'dicapi_certificate_number' => ['nullable', 'string', 'max:120'],
            'insurance_policy' => ['nullable', 'string', 'max:120'],
            'insurance_expires_at' => ['nullable', 'date'],
            'inspection_expires_at' => ['nullable', 'date'],
        ]);

        $people = FleetPlan::drawn($vessel);
        if ($request->filled('life_vest_count') && (int) $data['life_vest_count'] < $people) {
            return back()->withInput()->withErrors([
                'life_vest_count' => __('company_portal.fleet.vests_short', [], 'es'),
            ]);
        }
        if ((int) $data['seat_capacity'] < $people) {
            return back()->withInput()->withErrors([
                'seat_capacity' => __('company_portal.fleet.capacity_short', ['count' => $people], 'es'),
            ]);
        }

        $capacityIsReduced = (int) $data['seat_capacity'] < $vessel->seat_capacity;
        $hasActiveReservations = $capacityIsReduced && $vessel->departures()
            ->where('departure_at', '>=', now())
            ->whereHas('reservations', fn ($query) => $query->whereNotIn('status', ['cancelled', 'expired']))
            ->exists();
        if ($hasActiveReservations) {
            return back()->withInput()->withErrors([
                'seat_capacity' => 'No puedes reducir la capacidad mientras existan reservas vigentes en salidas futuras.',
            ]);
        }

        $requiresReview = collect(['registration_number', 'vessel_type', 'seat_capacity'])
            ->contains(fn ($field) => (string) $vessel->{$field} !== (string) $data[$field]);
        $wasOperational = in_array($vessel->status, ['ready', 'pending_review'], true);
        $changes = $data;
        if ($requiresReview && $wasOperational) {
            $changes += [
                'status' => 'draft',
                'review_notes' => null,
                'submitted_for_review_at' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ];
        }

        $vessel->update($changes);

        $message = $requiresReview && $wasOperational
            ? 'Datos actualizados. Como cambiaste información técnica, debes completar el plano y enviar nuevamente la embarcación a revisión.'
            : 'Datos de la embarcación actualizados correctamente.';

        return redirect()->route('company.fleet.index')->with('success', $message);
    }

    public function submitVesselForReview(Vessel $vessel): RedirectResponse
    {
        $organization = $this->organization();
        abort_unless($vessel->organization_id === $organization->id, 403);

        $people = FleetPlan::drawn($vessel);
        if ($people !== (int) $vessel->seat_capacity) {
            return back()->withErrors(['review' => __('company_portal.fleet.plan_incomplete', ['ready' => $people, 'capacity' => $vessel->seat_capacity], 'es')]);
        }
        if ($vessel->life_vest_count === null || $vessel->life_vest_count < $people) {
            return back()->withErrors(['review' => __('company_portal.fleet.vests_short', [], 'es')]);
        }

        abort_unless(in_array($vessel->status, ['draft', 'changes_requested', 'rejected'], true), 422);
        $vessel->update([
            'status' => 'pending_review',
            'review_notes' => null,
            'submitted_for_review_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);

        return back()->with('success', 'Embarcación enviada a revisión. NavegaYA notificará aquí el resultado.');
    }

    public function storeVesselMaintenance(Request $request, Vessel $vessel): RedirectResponse
    {
        $this->owns($vessel);

        return $this->storeMaintenance($request, $vessel);
    }

    public function storeAircraftMaintenance(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $this->owns($aircraft);

        return $this->storeMaintenance($request, $aircraft);
    }

    public function updateAircraft(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $organization = $this->owns($aircraft);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'registration_number' => ['required', 'string', 'max:80', Rule::unique('aircraft', 'registration_number')->ignore($aircraft->id)],
            'model' => ['nullable', 'string', 'max:120'],
            'aircraft_kind' => ['required', Rule::in(['light_aircraft', 'seaplane'])],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:900'],
            'max_takeoff_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'useful_load_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_baggage_kg_per_passenger' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'dgac_certificate_number' => ['nullable', 'string', 'max:120'],
            'airworthiness_certificate_number' => ['nullable', 'string', 'max:120'],
            'airworthiness_expires_at' => ['nullable', 'date'],
            'aviation_policy' => ['nullable', 'string', 'max:120'],
            'aviation_policy_expires_at' => ['nullable', 'date'],
        ]);
        $seatCount = $aircraft->seats()->count();
        if ((int) $data['seat_capacity'] < $seatCount) {
            return back()->withInput()->withErrors(['seat_capacity' => __('company_portal.fleet.capacity_short', ['count' => $seatCount], 'es')]);
        }
        $aircraft->update($data);

        return back()->with('success', __('company_portal.fleet.aircraft_saved', [], 'es'));
    }

    public function submitAircraftForReview(Aircraft $aircraft): RedirectResponse
    {
        $this->owns($aircraft);
        $seatCount = $aircraft->seats()->count();
        if ($seatCount !== $aircraft->seat_capacity) {
            return back()->withErrors(['review' => __('company_portal.fleet.plan_incomplete', ['ready' => $seatCount, 'capacity' => $aircraft->seat_capacity], 'es')]);
        }
        abort_unless(in_array($aircraft->status, ['draft', 'changes_requested', 'rejected'], true), 422);
        $aircraft->update([
            'status' => 'pending_review',
            'review_notes' => null,
            'submitted_for_review_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);

        return back()->with('success', __('company_portal.fleet.aircraft_sent', [], 'es'));
    }

    public function updateTransport(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'transport' => ['required', Rule::in(['fluvial', 'aereo'])],
            'enabled' => ['required', 'boolean'],
        ]);

        if ($organization->modalities()->exists() && $request->boolean('enabled')) {
            $allowed = $data['transport'] === 'aereo'
                ? ($organization->hasModality('aereo') || $organization->hasModality('aereo_fluvial'))
                : $organization->hasModality('fluvial');
            if (! $allowed) {
                return back()->withErrors(['transport' => 'Esa modalidad todavía no está aprobada. Solicítala en Datos de la empresa.']);
            }
        }

        $hasRiver = in_array($organization->modality ?: 'fluvial', ['fluvial', 'mixto'], true);
        $hasAir = in_array($organization->modality, ['aereo', 'mixto'], true);
        if ($data['transport'] === 'fluvial') {
            $hasRiver = $request->boolean('enabled');
        } else {
            $hasAir = $request->boolean('enabled');
        }

        if (! $hasRiver && ! $hasAir) {
            return back()->withErrors(['transport' => 'La empresa necesita al menos un tipo de transporte.']);
        }
        if (! $hasRiver && $organization->vessels()->exists()) {
            return back()->withErrors(['transport' => 'No puedes quitar las lanchas mientras haya embarcaciones registradas.']);
        }
        if (! $hasAir && $organization->aircraft()->exists()) {
            return back()->withErrors(['transport' => 'No puedes quitar las avionetas mientras haya aeronaves registradas.']);
        }

        $organization->update([
            'modality' => $hasRiver && $hasAir ? 'mixto' : ($hasAir ? 'aereo' : 'fluvial'),
        ]);

        $message = match ($organization->modality) {
            'mixto' => 'Quedaron habilitadas las lanchas y las avionetas.',
            'aereo' => 'La operación quedó en avionetas.',
            default => 'La operación quedó en lanchas y motonaves.',
        };

        return back()->with('success', $message);
    }

    private function storeMaintenance(Request $request, Model $unit): RedirectResponse
    {
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'kind' => ['required', Rule::in(['maintenance', 'repair'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'resolutions' => ['nullable', 'array'],
        ], [
            'ends_on.after_or_equal' => __('company_portal.fleet.maintenance_dates', [], 'es'),
        ]);

        $conflicts = FleetMaintenance::overlappingDepartures($unit, $data['starts_on'], $data['ends_on']);
        $organizationId = (int) $unit->organization_id;

        try {
            DB::transaction(function () use ($unit, $data, $conflicts, $organizationId): void {
                if ($conflicts->isNotEmpty()) {
                    $this->resolveConflicts($conflicts, $data['resolutions'] ?? [], $organizationId, $unit, $data['starts_on'], $data['ends_on']);
                }
                $unit->maintenancePeriods()->create([
                    'starts_on' => $data['starts_on'],
                    'ends_on' => $data['ends_on'],
                    'kind' => $data['kind'],
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors())->with('maintenance_conflicts', $conflicts->map(fn ($departure) => [
                'id' => $departure->id,
                'label' => $this->departureLabel($departure),
                'at' => $departure->departure_at->format('d/m/Y H:i'),
            ])->all())->with('maintenance_unit_id', $unit->id);
        }

        return back()->with('success', __('company_portal.fleet.maintenance_saved', [], 'es'));
    }

    /**
     * @param  Collection<int, RouteDeparture|AirDeparture>  $conflicts
     * @param  array<int|string, mixed>  $resolutions
     */
    private function resolveConflicts($conflicts, array $resolutions, int $organizationId, Model $unit, string $startsOn, string $endsOn): void
    {
        $periodStart = Carbon::parse($startsOn)->startOfDay();
        $periodEnd = Carbon::parse($endsOn)->endOfDay();

        foreach ($conflicts as $departure) {
            $row = $resolutions[$departure->id] ?? null;
            $reason = is_array($row) ? trim((string) ($row['reason'] ?? '')) : '';
            $action = is_array($row) ? ($row['action'] ?? '') : '';
            if ($reason === '' || ! in_array($action, ['reschedule', 'change_unit'], true)) {
                throw ValidationException::withMessages([
                    'maintenance' => __('company_portal.fleet.maintenance_blocked', [], 'es'),
                ]);
            }
            if ($action === 'reschedule') {
                if (blank($row['departure_at'] ?? null)) {
                    throw ValidationException::withMessages([
                        'maintenance' => __('company_portal.fleet.maintenance_blocked', [], 'es'),
                    ]);
                }
                $departureAt = Carbon::parse((string) $row['departure_at']);
                if ($departureAt->between($periodStart, $periodEnd)) {
                    throw ValidationException::withMessages([
                        'maintenance' => __('company_portal.fleet.maintenance_still_inside', [], 'es'),
                    ]);
                }
                CrewRoster::guardMove($departure, $departureAt);
                $changes = ['departure_at' => $departureAt];
                if ($departure instanceof RouteDeparture) {
                    $changes += [
                        'previous_departure_at' => $departure->departure_at,
                        'disruption_type' => 'rescheduled',
                        'disruption_reason' => $reason,
                        'status_changed_by_user_id' => auth()->id(),
                        'status_changed_at' => now(),
                    ];
                }
                $departure->update($changes);

                continue;
            }

            $replacementId = (int) ($row['replacement_id'] ?? 0);
            $replacement = $unit instanceof Vessel
                ? Vessel::query()->whereKey($replacementId)->where('organization_id', $organizationId)->where('status', 'ready')->whereKeyNot($unit->id)->first()
                : Aircraft::query()->whereKey($replacementId)->where('organization_id', $organizationId)->where('status', 'ready')->whereKeyNot($unit->id)->first();
            if (! $replacement) {
                throw ValidationException::withMessages([
                    'maintenance' => __('company_portal.fleet.replacement_invalid', [], 'es'),
                ]);
            }
            $changes = [$unit instanceof Vessel ? 'vessel_id' : 'aircraft_id' => $replacement->id];
            if ($departure instanceof RouteDeparture) {
                $changes += [
                    'disruption_type' => 'change_vessel',
                    'disruption_reason' => $reason,
                    'status_changed_by_user_id' => auth()->id(),
                    'status_changed_at' => now(),
                ];
            }
            $departure->update($changes);
        }
    }

    private function departureLabel(Model $departure): string
    {
        if ($departure instanceof RouteDeparture) {
            $origin = $departure->transportRoute?->originPort?->city;
            $destination = $departure->transportRoute?->destinationPort?->city;

            return trim(($origin && $destination ? $origin.' → '.$destination : $departure->code).' · '.$departure->departure_at->format('d/m/Y H:i'));
        }

        $route = $departure->airRoute;

        return trim(($route ? $route->origin_city.' → '.$route->destination_city : $departure->id).' · '.$departure->departure_at->format('d/m/Y H:i'));
    }

    private function owns(Model $unit): Organization
    {
        $organization = $this->organization();
        abort_unless((int) $unit->organization_id === (int) $organization->id, 403);

        return $organization;
    }

    private function organization(): Organization
    {
        return Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->whereIn('status', ['active', 'pending'])->firstOrFail();
    }
}
