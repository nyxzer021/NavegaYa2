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
            'vessels' => Vessel::with(['basePort', 'seats' => fn ($query) => $query->orderBy('deck')->orderBy('row_position')->orderBy('column_position')])->where('organization_id', $organization->id)->latest()->get(),
            'aircraft' => Aircraft::with(['seats' => fn ($query) => $query->orderBy('row_position')->orderBy('column_position')])->where('organization_id', $organization->id)->latest()->get(),
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
        ]);
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
        ]);
        Aircraft::create($data + ['organization_id' => $organization->id, 'status' => 'ready']);

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
            'maintenance_scheduled_on' => ['nullable', 'date'],
            'maintenance_kind' => ['nullable', 'required_with:maintenance_scheduled_on', Rule::in(['mantenimiento', 'reparacion'])],
        ], [
            'maintenance_kind.required_with' => 'Indica si es mantenimiento o reparación.',
        ]);

        $seatCount = $vessel->seats()->count();
        if ((int) $data['seat_capacity'] < $seatCount) {
            return back()->withInput()->withErrors([
                'seat_capacity' => "La capacidad no puede ser menor que los {$seatCount} asientos configurados. Ajusta primero el plano.",
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

        if (blank($data['maintenance_scheduled_on'] ?? null)) {
            $data['maintenance_scheduled_on'] = null;
            $data['maintenance_kind'] = null;
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

        $seatCount = $vessel->seats()->count();
        if ($seatCount !== $vessel->seat_capacity) {
            return back()->withErrors(['review' => "Completa el plano: hay {$seatCount} de {$vessel->seat_capacity} asientos configurados."]);
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

    public function updateTransport(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'transport' => ['required', Rule::in(['fluvial', 'aereo'])],
            'enabled' => ['required', 'boolean'],
        ]);

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

    private function organization(): Organization
    {
        return Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->whereIn('status', ['active', 'pending'])->firstOrFail();
    }
}
