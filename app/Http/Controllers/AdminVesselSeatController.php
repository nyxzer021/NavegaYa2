<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GenerateVesselSeatLayoutRequest;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Support\AdminScope;
use App\Support\FleetPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminVesselSeatController extends Controller
{
    public function edit(Request $request, Vessel $vessel): View
    {
        $this->authorizeVessel($vessel);
        $deck = $request->string('deck', 'principal')->value();
        abort_unless(in_array($deck, ['principal', 'superior'], true), 404);
        $vessel->load('organization');
        $seats = $vessel->seats()->where('deck', $deck)->where('space_type', 'seat')->orderBy('row_position')->orderBy('column_position')->get();
        $deckCounts = $vessel->seats()->where('space_type', 'seat')->selectRaw('deck, count(*) as total')->groupBy('deck')->pluck('total', 'deck');
        $isEditing = $request->boolean('edit');
        $places = $vessel->seats()->whereIn('space_type', ['hammock', 'cabin_bed'])->orderBy('code')->get();
        $otherDeckSeats = $vessel->seats()->get()
            ->reject(fn (VesselSeat $seat): bool => $seat->deck === $deck && $seat->space_type === 'seat')
            ->sum(fn (VesselSeat $seat): int => FleetPlan::occupants($seat));
        $planLocked = $this->routePrefix($request) === 'company' && in_array($vessel->status, ['pending_review', 'ready'], true);

        $routePrefix = $this->routePrefix($request);

        return view($routePrefix === 'company' ? 'company.fleet.seats' : 'admin.vessels.seats', [
            'organization' => $vessel->organization,
            'vessel' => $vessel,
            'seats' => $seats,
            'deck' => $deck,
            'deckCounts' => $deckCounts,
            'isEditing' => $isEditing,
            'otherDeckSeats' => $otherDeckSeats,
            'places' => $places,
            'planLocked' => $planLocked,
            'seatRoutePrefix' => $routePrefix,
        ]);
    }

    public function generate(GenerateVesselSeatLayoutRequest $request, Vessel $vessel): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        $data = $request->validated();
        $routePrefix = $this->routePrefix($request);
        abort_if($routePrefix === 'company' && in_array($vessel->status, ['pending_review', 'ready'], true), 422, 'No puedes modificar el plano mientras está en revisión o habilitado.');
        $total = $data['rows'] * ($data['left_seats'] + $data['right_seats']);
        $existing = $vessel->seats()->where('deck', $data['deck'])->where('space_type', 'seat')->exists();

        if ($existing && ! ($data['replace_existing'] ?? false)) {
            return back()->withErrors(['rows' => 'Este plano ya existe. Usa Editar plano si deseas reemplazarlo.']);
        }
        $otherPeople = $vessel->seats()->get()
            ->reject(fn (VesselSeat $seat): bool => $seat->deck === $data['deck'] && $seat->space_type === 'seat')
            ->sum(fn (VesselSeat $seat): int => FleetPlan::occupants($seat));
        $finalTotal = $otherPeople + $total;
        if ($finalTotal > $vessel->seat_capacity) {
            return back()->withInput()->withErrors(['rows' => "Esta cubierta genera {$total} asientos y, junto con el resto del plano ({$otherPeople}), sumaría {$finalTotal}. El máximo permitido es {$vessel->seat_capacity}."]);
        }

        DB::transaction(function () use ($vessel, $data, $routePrefix) {
            $vessel->seats()->where('deck', $data['deck'])->where('space_type', 'seat')->delete();
            $letters = range('A', 'L');
            $seats = [];
            for ($row = 1; $row <= $data['rows']; $row++) {
                for ($column = 1; $column <= $data['left_seats']; $column++) {
                    $seats[] = $this->seatData($vessel->id, $row, $column, $letters[$column - 1], $data);
                }
                for ($column = 1; $column <= $data['right_seats']; $column++) {
                    $position = $data['left_seats'] + 1 + $column;
                    $letter = $letters[$data['left_seats'] + $column - 1];
                    $seats[] = $this->seatData($vessel->id, $row, $position, $letter, $data);
                }
            }
            VesselSeat::insert($seats);
            if ($routePrefix === 'admin') {
                $vessel->update(['status' => 'ready']);
            }
        });

        $message = "Plano de cubierta {$data['deck']} creado con {$total} asientos.";
        if ($routePrefix === 'company' && $vessel->status === 'draft') {
            $message .= ' La embarcación permanece pendiente de habilitación por NavegaYA.';
        }

        return redirect()->route($routePrefix.'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => $data['deck']])->with('success', $message);
    }

    public function destroyDeck(Request $request, Vessel $vessel): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        abort_if($this->routePrefix($request) === 'company' && in_array($vessel->status, ['pending_review', 'ready'], true), 422, 'No puedes modificar el plano mientras está en revisión o habilitado.');
        $deck = $request->validate(['deck' => ['required', 'in:principal,superior']])['deck'];
        $vessel->seats()->where('deck', $deck)->where('space_type', 'seat')->delete();
        if (! $vessel->seats()->exists()) {
            $vessel->update(['status' => 'draft']);
        }

        return redirect()->route($this->routePrefix($request).'.vessels.seats.edit', ['vessel' => $vessel, 'deck' => $deck])->with('success', 'El plano de la cubierta fue eliminado.');
    }

    public function toggle(Request $request, Vessel $vessel, VesselSeat $seat): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        abort_if($this->routePrefix($request) === 'company' && in_array($vessel->status, ['pending_review', 'ready'], true), 422, 'No puedes modificar el plano mientras está en revisión o habilitado.');
        abort_unless($seat->vessel_id === $vessel->id, 404);
        $seat->update(['is_available' => ! $seat->is_available]);

        return back()->with('success', "El asiento {$seat->code} fue ".($seat->is_available ? 'habilitado.' : 'bloqueado.'));
    }

    public function storeHammocks(Request $request, Vessel $vessel): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        $this->abortIfPlanLocked($request, $vessel);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $created = DB::transaction(function () use ($vessel, $data): int {
            Vessel::query()->whereKey($vessel->id)->lockForUpdate()->first();
            $quantity = (int) $data['quantity'];
            $this->guardCapacity($vessel, $quantity);

            $next = $this->nextHammockNumber($vessel);
            for ($index = 0; $index < $quantity; $index++) {
                $number = $next + $index;
                $vessel->seats()->create([
                    'code' => 'H-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'deck' => 'principal',
                    'seat_class' => 'hammock',
                    'space_type' => 'hammock',
                    'is_available' => true,
                ]);
            }

            return $quantity;
        });

        return back()->with('success', "Zona de hamacas creada: {$created} ".($created === 1 ? 'hamaca' : 'hamacas').'.');
    }

    public function storeCabin(Request $request, Vessel $vessel): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        $this->abortIfPlanLocked($request, $vessel);
        $data = $request->validate([
            'cabin_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'bed_count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $code = strtoupper($data['cabin_code']);

        DB::transaction(function () use ($vessel, $data, $code): void {
            Vessel::query()->whereKey($vessel->id)->lockForUpdate()->first();
            if ($vessel->seats()->where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'cabin_code' => 'Ese código ya está en el plano.',
                ]);
            }
            $this->guardCapacity($vessel, (int) $data['bed_count']);
            $vessel->seats()->create([
                'code' => $code,
                'cabin_code' => $code,
                'deck' => 'principal',
                'seat_class' => 'cabin',
                'space_type' => 'cabin_bed',
                'cabin_sold_as' => 'whole',
                'bed_count' => (int) $data['bed_count'],
                'is_available' => true,
            ]);
        });

        return back()->with('success', "Camarote {$code} agregado. Cuenta {$data['bed_count']} ".((int) $data['bed_count'] === 1 ? 'cama' : 'camas').' para la capacidad y los chalecos.');
    }

    private function guardCapacity(Vessel $vessel, int $addedPeople): void
    {
        $vessel->unsetRelation('seats');
        $people = FleetPlan::people($vessel) + $addedPeople;
        if ($people > (int) $vessel->seat_capacity) {
            throw ValidationException::withMessages([
                'quantity' => "Esa combinación suma {$people} personas y la capacidad es {$vessel->seat_capacity}.",
            ]);
        }
        if ($vessel->life_vest_count !== null && $people > (int) $vessel->life_vest_count) {
            throw ValidationException::withMessages([
                'quantity' => __('company_portal.fleet.vests_short', [], 'es'),
            ]);
        }
    }

    private function nextHammockNumber(Vessel $vessel): int
    {
        $next = 1;
        foreach ($vessel->seats()->where('space_type', 'hammock')->pluck('code') as $code) {
            if (preg_match('/^H-(\d+)$/', (string) $code, $matches) === 1) {
                $next = max($next, ((int) $matches[1]) + 1);
            }
        }

        return $next;
    }

    private function abortIfPlanLocked(Request $request, Vessel $vessel): void
    {
        abort_if($this->routePrefix($request) === 'company' && in_array($vessel->status, ['pending_review', 'ready'], true), 422, 'No puedes modificar el plano mientras está en revisión o habilitado.');
    }

    private function seatData(int $vesselId, int $row, int $column, string $letter, array $data): array
    {
        return ['vessel_id' => $vesselId, 'code' => $row.$letter, 'deck' => $data['deck'], 'seat_class' => $data['seat_class'], 'space_type' => 'seat', 'row_position' => $row, 'column_position' => $column, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()];
    }

    private function authorizeVessel(Vessel $vessel): void
    {
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            abort_unless($vessel->organization_id === $organizationId, 403);
        }
    }

    private function routePrefix(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'company.') ? 'company' : 'admin';
    }
}
