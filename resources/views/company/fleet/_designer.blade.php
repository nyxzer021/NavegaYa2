@php
    $tools = [
        'seat' => ['Asiento', 'armchair', 'ny-tone-leaf'],
        'aisle' => ['Pasillo', 'eraser', 'ny-tone-violet'],
        'block' => ['Bloquear', 'lock', 'ny-tone-coral'],
    ];
    foreach (\App\Support\VesselLayout::ELEMENTS as $key => $element) {
        $tools[$key] = [$element['label'], $element['icon'], 'ny-tone-river'];
    }
    $icons = collect($tools)->mapWithKeys(fn ($tool, $key) => [$key => \Illuminate\Support\Facades\Blade::render('<x-ui.icon :name="$name" />', ['name' => $tool[1]])])->all();
    $designerConfig = [
        'deck' => $deck,
        'decks' => \App\Support\VesselLayout::DECKS,
        'state' => $layoutState,
        'capacity' => (int) $vessel->seat_capacity,
        'vests' => $vessel->life_vest_count,
        'placesPeople' => (int) $placesPeople,
        'maxRows' => \App\Support\VesselLayout::MAX_ROWS,
        'maxColumns' => \App\Support\VesselLayout::MAX_COLUMNS,
        'labels' => collect($tools)->map(fn ($tool) => $tool[0])->all(),
        'icons' => $icons,
        'locked' => (bool) $planLocked,
    ];
@endphp
<section class="ny-designer" x-data="vesselDesigner(@js($designerConfig))" aria-label="Diseñador del plano">
    <header class="ny-designer-head">
        <div>
            <h3>Diseñador de {{ strtolower(\App\Support\VesselLayout::DECKS[$deck]) }}</h3>
            <p x-show="!locked">Elige una herramienta y toca o arrastra sobre las casillas. La proa queda arriba.</p>
            <p x-show="locked" class="is-warn">El plano está en revisión o habilitado: solo puedes verlo.</p>
        </div>
        <div class="ny-designer-count" :class="{ 'is-over': overCapacity }">
            <strong x-text="seatCount"></strong> <span x-text="seatCount === 1 ? 'asiento en esta cubierta' : 'asientos en esta cubierta'"></span>
            <small x-text="`Total de plazas: ${totalPeople} de ${capacity}`"></small>
        </div>
    </header>

    <div class="ny-designer-tools" x-show="!locked && !preview" role="toolbar" aria-label="Herramientas">
        <template x-for="(label, key) in labels" :key="key">
            <button type="button" class="ny-tool" :class="{ 'is-on': tool === key }" @click="tool = key" :aria-pressed="tool === key">
                <span class="ny-mark" :class="toneFor(key)" x-html="icons[key]"></span>
                <span x-text="label"></span>
            </button>
        </template>
    </div>

    <div class="ny-designer-bar" x-show="!locked">
        <div class="ny-stepper" x-show="!preview">
            <span>Filas</span>
            <button type="button" @click="resize(rows - 1, columns)" aria-label="Quitar fila">−</button>
            <b x-text="rows"></b>
            <button type="button" @click="resize(rows + 1, columns)" aria-label="Agregar fila">+</button>
        </div>
        <div class="ny-stepper" x-show="!preview">
            <span>Columnas</span>
            <button type="button" @click="resize(rows, columns - 1)" aria-label="Quitar columna">−</button>
            <b x-text="columns"></b>
            <button type="button" @click="resize(rows, columns + 1)" aria-label="Agregar columna">+</button>
        </div>
        <details class="ny-designer-menu" x-show="!preview">
            <summary>Plantillas</summary>
            <div>
                <button type="button" @click="template(2, 2)">Lancha · 2 + pasillo + 2</button>
                <button type="button" @click="template(2, 1)">Rápido · 2 + pasillo + 1</button>
                <button type="button" @click="template(3, 3)">Ferry · 3 + pasillo + 3</button>
                <template x-for="(name, key) in decks" :key="key">
                    <button type="button" x-show="key !== deck" @click="copyFrom(key)" x-text="`Copiar desde ${name.toLowerCase()}`"></button>
                </template>
            </div>
        </details>
        <button type="button" class="ny-designer-preview" @click="preview = !preview" x-text="preview ? 'Volver a editar' : 'Ver como pasajero'"></button>
    </div>

    <p class="ny-designer-msg" x-show="message" x-text="message" role="status"></p>

    <div class="ny-stage">
        <div class="ny-hull" :style="`--ny-cols:${columns}`">
            <p class="ny-bow">Proa</p>
            <div class="ny-cabin ny-designer-grid" :class="{ 'is-preview': preview || locked }" @pointerup.window="painting = false" @pointerleave="painting = false">
                <template x-for="row in rows" :key="`r${row}`">
                    <div class="ny-row">
                        <span class="ny-rowno" x-text="rowLabel(row)"></span>
                        <template x-for="column in columns" :key="`c${row}-${column}`">
                            <button type="button" class="ny-cell" :class="cellClass(row, column)" :title="cellTitle(row, column)"
                                @pointerdown.prevent="start(row, column)" @pointerenter="paintOver(row, column)" :disabled="preview || locked">
                                <template x-if="typeAt(row, column) === 'seat'"><span x-text="codeAt(row, column)"></span></template>
                                <template x-if="isElement(row, column)"><span class="ny-cell-element" x-html="icons[typeAt(row, column)]"></span></template>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
            <p class="ny-stern">Popa</p>
        </div>
    </div>

    <div class="ny-designer-legend">
        <span><i class="ny-cell is-seat"></i>Asiento</span>
        <span><i class="ny-cell is-seat is-blocked"></i>Bloqueado</span>
        <span><i class="ny-cell is-seat is-sold"></i>Con ventas: no se puede quitar</span>
        <span><i class="ny-cell is-element"></i>No se vende</span>
    </div>

    @if($errors->has('layout'))<p class="ny-designer-msg is-error">{{ $errors->first('layout') }}</p>@endif
    <form method="POST" action="{{ route($seatRoutePrefix.'.vessels.layout.save', $vessel) }}" x-show="!locked" class="ny-designer-save" @submit="serialize($event)">
        @csrf @method('PUT')
        <input type="hidden" name="deck" :value="deck">
        <input type="hidden" name="rows" :value="rows">
        <input type="hidden" name="columns" :value="columns">
        <input type="hidden" name="cells" x-ref="cells">
        <button type="submit" :disabled="overCapacity" x-text="overCapacity ? 'Te pasas de la capacidad' : 'Guardar plano'"></button>
    </form>
</section>

@once
<script>
function vesselDesigner(config) {
    return {
        ...config,
        tool: 'seat',
        preview: false,
        painting: false,
        message: '',
        rows: config.state[config.deck].rows || 10,
        columns: config.state[config.deck].columns || 5,
        cells: JSON.parse(JSON.stringify(config.state[config.deck].cells || {})),
        key(row, column) { return `${row}-${column}`; },
        typeAt(row, column) { return this.cells[this.key(row, column)]?.t || 'aisle'; },
        isElement(row, column) { const type = this.typeAt(row, column); return type !== 'seat' && type !== 'aisle'; },
        toneFor(key) { return { seat: 'ny-tone-leaf', aisle: 'ny-tone-violet', block: 'ny-tone-coral' }[key] || 'ny-tone-river'; },
        get seatCount() { return Object.values(this.cells).filter(cell => cell.t === 'seat').length; },
        get otherDeckSeats() {
            return Object.entries(this.state).filter(([key]) => key !== this.deck)
                .reduce((sum, [, deck]) => sum + Object.values(deck.cells || {}).filter(cell => cell.t === 'seat').length, 0);
        },
        get totalPeople() { return this.seatCount + this.otherDeckSeats + this.placesPeople; },
        get overCapacity() { return this.totalPeople > this.capacity || (this.vests !== null && this.totalPeople > this.vests); },
        seatRows() {
            const rows = new Set();
            Object.entries(this.cells).forEach(([key, cell]) => { if (cell.t === 'seat') rows.add(Number(key.split('-')[0])); });
            return [...rows].sort((a, b) => a - b);
        },
        rowLabel(row) { const index = this.seatRows().indexOf(row); return index === -1 ? '' : index + 1; },
        codeAt(row, column) {
            const cell = this.cells[this.key(row, column)];
            if (cell?.locked && cell.code) return cell.code;
            const number = this.rowLabel(row);
            let letter = 0;
            for (let c = 1; c < column; c++) { if (this.typeAt(row, c) === 'seat') letter++; }
            return `${number}${'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[letter] || '?'}`;
        },
        cellClass(row, column) {
            const cell = this.cells[this.key(row, column)];
            if (!cell || cell.t === 'aisle') return 'is-aisle';
            if (cell.t === 'seat') return ['is-seat', cell.b ? 'is-blocked' : '', cell.locked ? 'is-sold' : ''].join(' ');
            return 'is-element';
        },
        cellTitle(row, column) {
            const cell = this.cells[this.key(row, column)];
            if (!cell || cell.t === 'aisle') return 'Pasillo';
            if (cell.t === 'seat') return `Asiento ${this.codeAt(row, column)}${cell.b ? ' · bloqueado' : ''}${cell.locked ? ' · con ventas' : ''}`;
            return this.labels[cell.t];
        },
        start(row, column) { this.painting = true; this.blockStart = null; this.message = ''; this.paint(row, column); },
        paintOver(row, column) { if (this.painting) this.paint(row, column); },
        paint(row, column) {
            if (this.locked || this.preview) return;
            const key = this.key(row, column), cell = this.cells[key];
            if (this.tool === 'block') {
                if (cell?.t === 'seat') cell.b = !this.paintingBlockValue(key);
                return;
            }
            if (cell?.locked && this.tool !== 'seat') {
                this.message = `El asiento ${cell.code} tiene ventas: no se puede quitar, solo bloquear.`;
                return;
            }
            if (this.tool === 'aisle') { delete this.cells[key]; return; }
            if (this.tool === 'seat') { if (cell?.t !== 'seat') this.cells[key] = { t: 'seat', b: false }; return; }
            this.cells[key] = { t: this.tool };
        },
        blockStart: null,
        paintingBlockValue(key) {
            if (this.blockStart === null) this.blockStart = !!this.cells[key]?.b;
            return this.blockStart;
        },
        resize(rows, columns) {
            rows = Math.max(1, Math.min(this.maxRows, rows));
            columns = Math.max(1, Math.min(this.maxColumns, columns));
            const lost = Object.entries(this.cells).filter(([key, cell]) => {
                const [r, c] = key.split('-').map(Number);
                return (r > rows || c > columns) && cell.locked;
            });
            if (lost.length) { this.message = 'Hay asientos con ventas en esa fila o columna: no se puede quitar.'; return; }
            Object.keys(this.cells).forEach(key => {
                const [r, c] = key.split('-').map(Number);
                if (r > rows || c > columns) delete this.cells[key];
            });
            this.rows = rows; this.columns = columns;
        },
        keepLocked() {
            return Object.fromEntries(Object.entries(this.cells).filter(([, cell]) => cell.locked));
        },
        template(left, right) {
            const locked = this.keepLocked();
            const columns = left + 1 + right;
            const free = Math.max(0, this.capacity - this.otherDeckSeats - this.placesPeople);
            const rows = Math.max(1, Math.min(this.maxRows, Math.floor(free / (left + right)) || 1));
            const cells = {};
            cells[this.key(1, Math.ceil(columns / 2))] = { t: 'helm' };
            for (let row = 2; row <= rows + 1 && row <= this.maxRows; row++) {
                for (let column = 1; column <= columns; column++) {
                    if (column !== left + 1) cells[this.key(row, column)] = { t: 'seat', b: false };
                }
            }
            this.rows = Math.min(this.maxRows, rows + 1);
            this.columns = Math.max(columns, ...Object.keys(locked).map(key => Number(key.split('-')[1])));
            this.cells = { ...cells, ...locked };
            this.message = 'Plantilla aplicada. Ajusta lo que necesites y guarda.';
        },
        copyFrom(deck) {
            const source = this.state[deck];
            const locked = this.keepLocked();
            const cells = {};
            Object.entries(source.cells || {}).forEach(([key, cell]) => { cells[key] = cell.t === 'seat' ? { t: 'seat', b: !!cell.b } : { t: cell.t }; });
            this.rows = source.rows || this.rows;
            this.columns = source.columns || this.columns;
            this.cells = { ...cells, ...locked };
            this.message = `Copiado desde ${this.decks[deck].toLowerCase()}. Revisa y guarda.`;
        },
        serialize(event) {
            if (this.overCapacity) { event.preventDefault(); return; }
            this.$refs.cells.value = JSON.stringify(Object.entries(this.cells).map(([key, cell]) => {
                const [r, c] = key.split('-').map(Number);
                return { r, c, t: cell.t, b: !!cell.b };
            }));
        },
    };
}
</script>
@endonce
