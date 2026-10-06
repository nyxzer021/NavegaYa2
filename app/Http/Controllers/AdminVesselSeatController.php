<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GenerateVesselSeatLayoutRequest;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminVesselSeatController extends Controller
{
    public function edit(Request $request, Vessel $vessel): View
    {
        $deck = $request->string('deck', 'principal')->value();
        abort_unless(in_array($deck, ['principal', 'superior'], true), 404);
        $vessel->load('organization');
        $seats = $vessel->seats()->where('deck', $deck)->orderBy('row_position')->orderBy('column_position')->get();
        $deckCounts = $vessel->seats()->selectRaw('deck, count(*) as total')->groupBy('deck')->pluck('total', 'deck');
        $isEditing = $request->boolean('edit');

        $otherDeckSeats = $deckCounts->except($deck)->sum();

        return view('admin.vessels.seats', [
            'vessel' => $vessel,
            'seats' => $seats,
            'deck' => $deck,
            'deckCounts' => $deckCounts,
            'isEditing' => $isEditing,
            'otherDeckSeats' => $otherDeckSeats,
        ]);
    }

    public function generate(GenerateVesselSeatLayoutRequest $request, Vessel $vessel): RedirectResponse
    {
        $data = $request->validated();
        $total = $data['rows'] * ($data['left_seats'] + $data['right_seats']);
        $existing = $vessel->seats()->where('deck', $data['deck'])->exists();

        if ($existing && ! ($data['replace_existing'] ?? false)) {
            return back()->withErrors(['rows' => 'Este plano ya existe. Usa Editar plano si deseas reemplazarlo.']);
        }
        $otherDeckSeats = $vessel->seats()->where('deck', '!=', $data['deck'])->count();
        $finalTotal = $otherDeckSeats + $total;
        if ($finalTotal > $vessel->seat_capacity) {
            return back()->withInput()->withErrors(['rows' => "Esta cubierta genera {$total} asientos y, junto con las otras cubiertas ({$otherDeckSeats}), sumaría {$finalTotal}. El máximo permitido es {$vessel->seat_capacity}."]);
        }

        DB::transaction(function () use ($vessel, $data) {
            $vessel->seats()->where('deck', $data['deck'])->delete();
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
            $vessel->update(['status' => 'ready']);
        });

        return redirect()->route('admin.vessels.seats.edit', ['vessel' => $vessel, 'deck' => $data['deck']])->with('success', "Plano de cubierta {$data['deck']} creado con {$total} asientos.");
    }

    public function destroyDeck(Request $request, Vessel $vessel): RedirectResponse
    {
        $deck = $request->validate(['deck' => ['required', 'in:principal,superior']])['deck'];
        $vessel->seats()->where('deck', $deck)->delete();
        if (! $vessel->seats()->exists()) {
            $vessel->update(['status' => 'draft']);
        }

        return redirect()->route('admin.vessels.seats.edit', ['vessel' => $vessel, 'deck' => $deck])->with('success', 'El plano de la cubierta fue eliminado.');
    }

    public function toggle(Vessel $vessel, VesselSeat $seat): RedirectResponse
    {
        abort_unless($seat->vessel_id === $vessel->id, 404);
        $seat->update(['is_available' => ! $seat->is_available]);

        return back()->with('success', "El asiento {$seat->code} fue ".($seat->is_available ? 'habilitado.' : 'bloqueado.'));
    }

    private function seatData(int $vesselId, int $row, int $column, string $letter, array $data): array
    {
        return ['vessel_id' => $vesselId, 'code' => $row.$letter, 'deck' => $data['deck'], 'seat_class' => $data['seat_class'], 'row_position' => $row, 'column_position' => $column, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()];
    }
}
