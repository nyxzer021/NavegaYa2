<?php

namespace App\Http\Controllers;

use App\Models\DepartureIncident;
use App\Models\IncidentPhoto;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Support\AdminScope;
use App\Support\CrewRoster;
use App\Support\DepartureSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyIncidentController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();

        return view('company.incidents.index', [
            'organization' => $organization,
            'departures' => RouteDeparture::query()
                ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
                ->whereNotIn('status', ['completed'])
                ->with(['transportRoute.originPort', 'transportRoute.destinationPort'])
                ->orderByDesc('departure_at')
                ->limit(40)
                ->get(),
            'incidents' => DepartureIncident::query()
                ->where('organization_id', $organization->id)
                ->with(['routeDeparture.transportRoute.originPort', 'routeDeparture.transportRoute.destinationPort', 'photos'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'departure_id' => ['required', 'integer'],
            'kind' => ['required', 'in:delay,breakdown,weather,river_level,other'],
            'severity' => ['required', 'in:low,medium,high'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'action' => ['required', 'in:record,reschedule,cancel'],
            'departure_at' => ['nullable', 'required_if:action,reschedule', 'date'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'max:4096'],
        ]);
        $departure = RouteDeparture::query()
            ->whereKey($data['departure_id'])
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
            ->with('transportRoute')
            ->firstOrFail();
        abort_if(in_array($departure->status, ['completed', 'cancelled'], true), 422, 'Una salida finalizada ya no admite incidencias.');

        if ($data['action'] === 'reschedule') {
            $when = DepartureSchedule::parse($data['departure_at']);
            if ($departure->vessel && DepartureSchedule::conflicts($departure->vessel, $when, (int) $departure->transportRoute->estimated_duration_minutes, $departure->id)->isNotEmpty()) {
                throw ValidationException::withMessages(['departure_at' => 'Esa unidad ya tiene una salida que se cruza con este horario.']);
            }
            CrewRoster::guardMove($departure, $when);
        }

        $incident = DB::transaction(function () use ($data, $departure, $organization, $request) {
            if ($data['action'] === 'reschedule') {
                $when = DepartureSchedule::parse($data['departure_at']);
                $offset = $departure->departure_at->diffInMinutes($when, false);
                $departure->update([
                    'previous_departure_at' => $departure->departure_at,
                    'departure_at' => $when,
                    'boarding_starts_at' => $departure->boarding_starts_at?->copy()->addMinutes($offset),
                    'estimated_arrival_at' => $departure->estimated_arrival_at?->copy()->addMinutes($offset),
                    'status' => 'rescheduled',
                    'disruption_type' => 'reschedule',
                    'disruption_reason' => $data['description'],
                    'status_changed_by_user_id' => $request->user()->id,
                    'status_changed_at' => now(),
                ]);
                DepartureSchedule::notify($departure->fresh(), $data['description'], 'La salida quedó para '.DepartureSchedule::label($when));
            } elseif ($data['action'] === 'cancel') {
                $departure->update([
                    'status' => 'cancelled',
                    'is_published' => false,
                    'disruption_type' => 'cancel',
                    'disruption_reason' => $data['description'],
                    'status_changed_by_user_id' => $request->user()->id,
                    'status_changed_at' => now(),
                ]);
                DepartureSchedule::notify($departure->fresh(), $data['description'], 'La salida fue cancelada');
            }

            $incident = DepartureIncident::create([
                'organization_id' => $organization->id,
                'route_departure_id' => $departure->id,
                'reported_by_user_id' => $request->user()->id,
                'kind' => $data['kind'],
                'severity' => $data['severity'],
                'description' => $data['description'],
                'action' => $data['action'],
            ]);
            foreach ($request->file('photos', []) as $photo) {
                $path = $photo->store('incident-photos/'.$organization->id, 'local');
                $incident->photos()->create([
                    'path' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                ]);
            }

            return $incident;
        });

        return redirect()->route('company.incidents.index')->with('success', 'Incidencia registrada.'.($incident->action === 'record' ? '' : ' La salida quedó actualizada.'));
    }

    public function photo(IncidentPhoto $photo): StreamedResponse
    {
        $photo->load('incident');
        $this->authorizePhoto($photo);

        return Storage::disk('local')->response($photo->path, $photo->original_name ?: 'foto');
    }

    public static function authorizePhoto(IncidentPhoto $photo): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        if ($user->roles()->whereIn('code', ['super_admin', 'admin'])->exists()) {
            return;
        }
        $organizationId = AdminScope::organizationId($user);
        abort_unless($organizationId && (int) $photo->incident->organization_id === (int) $organizationId, 404);
        abort_unless($user->roles()->whereIn('code', ['company_admin', 'company_operator'])->exists(), 403);
    }

    private function organization(): Organization
    {
        abort_unless(auth()->user()->roles()->whereIn('code', ['company_admin', 'company_operator'])->exists(), 403);
        $id = AdminScope::organizationId(auth()->user());
        abort_unless($id, 403);

        return Organization::query()->whereKey($id)->firstOrFail();
    }
}
