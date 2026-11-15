<?php

namespace App\Support;

use App\Models\ReservationSeat;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plano visual de una embarcación por cubierta.
 * Los asientos vendibles viven en vessel_seats (fila y columna); los elementos que no se venden viven en vessels.layout.
 */
class VesselLayout
{
    public const DECKS = ['principal' => 'Cubierta principal', 'superior' => 'Cubierta superior'];

    /**
     * Elementos del plano que no se venden.
     *
     * @var array<string, array{label: string, icon: string}>
     */
    public const ELEMENTS = [
        'helm' => ['label' => 'Cabina de mando', 'icon' => 'steering'],
        'bathroom' => ['label' => 'Baño', 'icon' => 'bath'],
        'stairs' => ['label' => 'Escalera', 'icon' => 'stairs'],
        'exit' => ['label' => 'Salida', 'icon' => 'door-exit'],
        'cargo' => ['label' => 'Carga', 'icon' => 'freight'],
        'hammock_zone' => ['label' => 'Zona de hamacas', 'icon' => 'hammock'],
        'cabin_zone' => ['label' => 'Camarotes', 'icon' => 'hotel'],
    ];

    public const MAX_ROWS = 40;

    public const MAX_COLUMNS = 12;

    /**
     * Cuadrícula de una cubierta lista para dibujar.
     *
     * @param  Collection<int, VesselSeat>|null  $seats  Asientos de la cubierta ya cargados (opcional).
     * @return array{rows: int, columns: int, grid: list<list<array{type: string, seat: VesselSeat|null}>>}
     */
    public static function grid(Vessel $vessel, string $deck, ?Collection $seats = null): array
    {
        $seats ??= $vessel->seats()->where('deck', $deck)->where('space_type', 'seat')->get();
        $seats = $seats->where('space_type', 'seat')->where('deck', $deck);
        $saved = data_get($vessel->layout, $deck, []);
        $elements = (array) ($saved['cells'] ?? []);
        $rows = max((int) ($saved['rows'] ?? 0), (int) $seats->max('row_position'), self::maxElementIndex($elements, 0));
        $columns = max((int) ($saved['columns'] ?? 0), (int) $seats->max('column_position'), self::maxElementIndex($elements, 1));
        $byPosition = $seats->keyBy(fn (VesselSeat $seat): string => $seat->row_position.'-'.$seat->column_position);

        $grid = [];
        for ($row = 1; $row <= $rows; $row++) {
            $line = [];
            for ($column = 1; $column <= $columns; $column++) {
                $key = $row.'-'.$column;
                $seat = $byPosition->get($key);
                $type = $seat ? 'seat' : (array_key_exists((string) ($elements[$key] ?? ''), self::ELEMENTS) ? $elements[$key] : 'aisle');
                $line[] = ['type' => $type, 'seat' => $seat];
            }
            $grid[] = $line;
        }

        return ['rows' => $rows, 'columns' => $columns, 'grid' => $grid];
    }

    /**
     * Datos del editor para ambas cubiertas.
     *
     * @return array<string, array{rows: int, columns: int, cells: array<string, array{t: string, b?: bool, code?: string, locked?: bool, sold?: bool}>}>
     */
    public static function editorState(Vessel $vessel): array
    {
        $seats = $vessel->seats()->where('space_type', 'seat')->get();
        $history = self::seatsWithHistory($vessel);
        $future = self::seatsWithFutureSales($vessel);
        $state = [];

        foreach (array_keys(self::DECKS) as $deck) {
            $layout = self::grid($vessel, $deck, $seats);
            $cells = [];
            foreach ($layout['grid'] as $rowIndex => $line) {
                foreach ($line as $columnIndex => $cell) {
                    if ($cell['type'] === 'aisle') {
                        continue;
                    }
                    $key = ($rowIndex + 1).'-'.($columnIndex + 1);
                    $cells[$key] = ['t' => $cell['type']];
                    if ($seat = $cell['seat']) {
                        $cells[$key] += ['b' => ! $seat->is_available, 'code' => $seat->code, 'locked' => in_array($seat->id, $history, true), 'sold' => in_array($seat->id, $future, true)];
                    }
                }
            }
            $state[$deck] = ['rows' => $layout['rows'], 'columns' => $layout['columns'], 'cells' => $cells];
        }

        return $state;
    }

    /**
     * Guarda el plano de una cubierta. Conserva los asientos que siguen en su lugar (y sus ventas),
     * crea los nuevos y quita los que salen del plano solo si nunca se vendieron.
     *
     * @param  list<array{r: int, c: int, t: string, b?: bool}>  $cells
     */
    public static function save(Vessel $vessel, string $deck, int $rows, int $columns, array $cells): int
    {
        return DB::transaction(function () use ($vessel, $deck, $rows, $columns, $cells): int {
            Vessel::query()->whereKey($vessel->id)->lockForUpdate()->first();

            $seatCells = [];
            $elements = [];
            foreach ($cells as $cell) {
                $row = (int) $cell['r'];
                $column = (int) $cell['c'];
                if ($row < 1 || $row > $rows || $column < 1 || $column > $columns) {
                    continue;
                }
                $key = $row.'-'.$column;
                if ($cell['t'] === 'seat') {
                    $seatCells[$key] = ['row' => $row, 'column' => $column, 'blocked' => (bool) ($cell['b'] ?? false)];
                } elseif (array_key_exists($cell['t'], self::ELEMENTS)) {
                    $elements[$key] = $cell['t'];
                }
            }

            $existing = $vessel->seats()->where('deck', $deck)->where('space_type', 'seat')->get()
                ->keyBy(fn (VesselSeat $seat): string => $seat->row_position.'-'.$seat->column_position);
            $history = self::seatsWithHistory($vessel);
            $future = self::seatsWithFutureSales($vessel);

            $removed = $existing->reject(fn (VesselSeat $seat, string $key): bool => isset($seatCells[$key]));
            $blockedByFuture = $removed->filter(fn (VesselSeat $seat): bool => in_array($seat->id, $future, true));
            if ($blockedByFuture->isNotEmpty()) {
                throw ValidationException::withMessages(['layout' => 'No puedes quitar '.self::codes($blockedByFuture).': tienen pasajes vendidos para salidas futuras. Puedes bloquearlos.']);
            }
            $blockedByHistory = $removed->filter(fn (VesselSeat $seat): bool => in_array($seat->id, $history, true));
            if ($blockedByHistory->isNotEmpty()) {
                throw ValidationException::withMessages(['layout' => 'No puedes quitar '.self::codes($blockedByHistory).': tienen historial de ventas. Puedes bloquearlos.']);
            }

            $otherPeople = (int) $vessel->seats()->get()
                ->reject(fn (VesselSeat $seat): bool => $seat->deck === $deck && $seat->space_type === 'seat')
                ->sum(fn (VesselSeat $seat): int => FleetPlan::occupants($seat));
            $people = $otherPeople + count($seatCells);
            if ($people > (int) $vessel->seat_capacity) {
                throw ValidationException::withMessages(['layout' => "El plano suma {$people} plazas y la capacidad homologada es {$vessel->seat_capacity}."]);
            }
            if ($vessel->life_vest_count !== null && $people > (int) $vessel->life_vest_count) {
                throw ValidationException::withMessages(['layout' => "El plano suma {$people} plazas y solo hay {$vessel->life_vest_count} chalecos."]);
            }

            $vessel->seats()->whereIn('id', $removed->pluck('id'))->delete();

            $codes = self::codesFor($seatCells, $existing, $history);
            foreach ($seatCells as $key => $cell) {
                $attributes = ['code' => $codes[$key], 'is_available' => ! $cell['blocked']];
                if ($seat = $existing->get($key)) {
                    $seat->update($attributes);
                } else {
                    $vessel->seats()->create($attributes + [
                        'deck' => $deck, 'seat_class' => 'standard', 'space_type' => 'seat',
                        'row_position' => $cell['row'], 'column_position' => $cell['column'],
                    ]);
                }
            }

            $layout = $vessel->fresh()->layout ?? [];
            $layout[$deck] = ['rows' => $rows, 'columns' => $columns, 'cells' => $elements];
            $vessel->forceFill(['layout' => $layout])->save();

            return count($seatCells);
        });
    }

    /**
     * Nombre de cada asiento: número de fila con asientos y letra según el orden en la fila.
     * Los asientos con ventas conservan su nombre para que coincida con los boletos emitidos.
     *
     * @param  array<string, array{row: int, column: int, blocked: bool}>  $seatCells
     * @param  Collection<string, VesselSeat>  $existing
     * @param  list<int>  $history
     * @return array<string, string>
     */
    private static function codesFor(array $seatCells, Collection $existing, array $history): array
    {
        $kept = [];
        foreach ($seatCells as $key => $cell) {
            $seat = $existing->get($key);
            if ($seat && in_array($seat->id, $history, true)) {
                $kept[$key] = $seat->code;
            }
        }
        $taken = array_flip(array_values($kept));
        $letters = range('A', 'Z');
        $codes = $kept;
        $rowsWithSeats = collect($seatCells)->pluck('row')->unique()->sort()->values();

        foreach ($rowsWithSeats as $index => $row) {
            $number = $index + 1;
            $letter = 0;
            $rowCells = collect($seatCells)->filter(fn (array $cell): bool => $cell['row'] === $row)->sortBy('column');
            foreach ($rowCells as $key => $cell) {
                if (isset($kept[$key])) {
                    $letter++;

                    continue;
                }
                do {
                    $code = $number.($letters[$letter] ?? 'Z'.$letter);
                    $letter++;
                } while (isset($taken[$code]));
                $taken[$code] = true;
                $codes[$key] = $code;
            }
        }

        return $codes;
    }

    /**
     * @return list<int>
     */
    public static function seatsWithHistory(Vessel $vessel): array
    {
        return ReservationSeat::query()
            ->whereIn('vessel_seat_id', $vessel->seats()->select('id'))
            ->distinct()->pluck('vessel_seat_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return list<int>
     */
    public static function seatsWithFutureSales(Vessel $vessel): array
    {
        return ReservationSeat::query()
            ->whereIn('vessel_seat_id', $vessel->seats()->select('id'))
            ->whereHas('reservation', fn ($query) => $query->whereNotIn('status', ['cancelled', 'expired', 'refunded']))
            ->whereHas('departure', fn ($query) => $query->where('departure_at', '>=', now())->where('status', '!=', 'cancelled'))
            ->distinct()->pluck('vessel_seat_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  array<string, string>  $elements
     */
    private static function maxElementIndex(array $elements, int $part): int
    {
        return (int) collect(array_keys($elements))->map(fn (string $key): int => (int) (explode('-', $key)[$part] ?? 0))->max();
    }

    /**
     * @param  Collection<string, VesselSeat>  $seats
     */
    private static function codes(Collection $seats): string
    {
        return $seats->pluck('code')->sort()->join(', ', ' y ');
    }
}
