<x-app-layout><x-slot name="header"><h2 class="ny-page-title">Registrar embarcación</h2></x-slot>
@php
    $isEdit = isset($vessel);
    $field = fn (string $key, mixed $fallback = null) => old($key, $isEdit ? $vessel->{$key} : $fallback);
    $dateField = fn (string $key) => old($key, $isEdit && $vessel->{$key} ? $vessel->{$key}->format('Y-m-d') : '');
@endphp
<style>
.ny-form-wrap{max-width:980px;margin:0 auto}.ny-form-card{margin-top:18px;padding:30px;border:1px solid #dce7e4;border-radius:20px;background:#fff}.ny-form-card h1{margin:0;color:#152e2b;font-size:27px}.ny-form-card>p{color:#64748b}.ny-section{margin-top:28px;padding-top:20px;border-top:1px solid #e7efec}.ny-section h2{margin:0 0 4px;font-size:17px;color:#12372d}.ny-section p{margin:0;color:#64748b;font-size:13px}.ny-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:19px}.ny-fields label{display:block;color:#334155;font-size:13px;font-weight:700}.ny-fields input,.ny-fields select,.ny-fields textarea{box-sizing:border-box;width:100%;margin-top:7px;padding:11px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#1e293b}.ny-fields textarea{min-height:95px}.ny-hint{display:block;margin-top:5px;font-size:11px;color:#64748b;font-weight:400}.ny-save{margin-top:25px;border:0;border-radius:10px;background:#12372d;padding:12px 18px;color:#fff;font-weight:800;cursor:pointer}.ny-cancel{margin-left:8px;color:#176d60;font-weight:800;text-decoration:none}.ny-note{padding:12px 14px;border-radius:10px;background:#f0f8f5;color:#356056;font-size:13px}@media(max-width:650px){.ny-fields{grid-template-columns:1fr}.ny-form-card{padding:20px}}
</style>
<div class="ny-form-wrap">
    <a href="{{ route('admin.vessels.index') }}" style="color:#176d60;font-size:13px;font-weight:800;text-decoration:none">← Volver a embarcaciones</a>
    @if(session('success'))<p style="margin-top:18px;padding:14px 18px;border-radius:12px;background:#e8fbf2;color:#087057;font-weight:700">✓ {{ session('success') }}</p>@endif
    <form method="POST" action="{{ $isEdit ? route('admin.vessels.update', $vessel) : route('admin.vessels.store') }}" class="ny-form-card">
        @csrf
        @if($isEdit) @method('PATCH') @endif
        <h1>{{ $isEdit ? 'Ficha de la embarcación' : 'Nueva embarcación' }}</h1>
        <p>{{ $isEdit ? 'Actualiza la información registrada. La matrícula identifica formalmente a la nave; no se necesita una placa adicional.' : 'Registra la nave y sus datos técnicos antes de configurar su mapa de asientos.' }}</p>

        <section class="ny-section" style="margin-top:20px;padding-top:0;border-top:0"><h2>Identificación y operación</h2><p>Datos que permiten identificar a la embarcación y asociarla con su operador.</p><div class="ny-fields">
            <label>Empresa de transporte<select name="organization_id" required><option value="">Selecciona una empresa</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($field('organization_id') == $organization->id)>{{ $organization->commercial_name ?: $organization->legal_name }}</option>@endforeach</select></label>
            <label>Puerto base<select name="base_port_id"><option value="">Sin asignar por ahora</option>@foreach($ports as $port)<option value="{{ $port->id }}" @selected($field('base_port_id') == $port->id)>{{ $port->name }} · {{ $port->city }}</option>@endforeach</select><span class="ny-hint">Se registra en Puertos y destinos.</span></label>
            <label>Nombre de la embarcación<input name="name" required value="{{ $field('name') }}" placeholder="Ej. M/F Río Amazonas"></label>
            <label>Matrícula / registro oficial<input name="registration_number" required value="{{ $field('registration_number') }}" placeholder="Ej. PA-12345-MF"><span class="ny-hint">Equivale a la placa o identificación oficial de la nave.</span></label>
            <label>Tipo<select name="vessel_type" required><option value="">Selecciona un tipo</option>@foreach(['lancha'=>'Lancha','motonave'=>'Motonave','rapido'=>'Rápido','ferry'=>'Ferry','otro'=>'Otro'] as $value => $label)<option value="{{ $value }}" @selected($field('vessel_type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Año de fabricación<input type="number" name="manufacture_year" min="1900" max="{{ now()->year + 1 }}" value="{{ $field('manufacture_year') }}" placeholder="Ej. 2022"></label>
        </div></section>

        <section class="ny-section"><h2>Capacidad y dimensiones</h2><p>El número de asientos corresponde a pasajeros que pueden reservar. La tripulación es el personal que opera la nave.</p><div class="ny-fields">
            <label>Número máximo de asientos<input type="number" name="seat_capacity" required min="1" value="{{ $field('seat_capacity') }}" placeholder="Ej. 80"></label>
            <label>Tripulación estimada<input type="number" name="crew_capacity" min="0" value="{{ $field('crew_capacity', 0) }}"><span class="ny-hint">Capitán, motoristas, personal de atención, etc.</span></label>
            <label>Peso bruto / tonelaje (t)<input type="number" step="0.01" min="0" name="gross_tonnage" value="{{ $field('gross_tonnage') }}" placeholder="Ej. 45.50"></label>
            <label>Material del casco<input name="hull_material" value="{{ $field('hull_material') }}" placeholder="Ej. aluminio, fibra de vidrio"></label>
            <label>Eslora / largo (m)<input type="number" step="0.01" min="0.01" name="length_m" value="{{ $field('length_m') }}"></label>
            <label>Manga / ancho (m)<input type="number" step="0.01" min="0.01" name="beam_m" value="{{ $field('beam_m') }}"></label>
            <label>Calado (m)<input type="number" step="0.01" min="0.01" name="draft_m" value="{{ $field('draft_m') }}"></label>
            <label>Motor / propulsión<input name="engine_description" value="{{ $field('engine_description') }}" placeholder="Ej. 2 motores Yamaha 250 HP"></label>
        </div></section>

        <section class="ny-section"><h2>Seguridad y documentos</h2><p>Estos campos ayudan a controlar la vigencia operativa. Más adelante podrán generar alertas antes de vencer.</p><div class="ny-fields">
            <label>Póliza de seguro<input name="insurance_policy" value="{{ $field('insurance_policy') }}" placeholder="Número de póliza"></label>
            <label>Vence seguro<input type="date" name="insurance_expires_at" value="{{ $dateField('insurance_expires_at') }}"></label>
            <label>Vence certificado de inspección<input type="date" name="inspection_expires_at" value="{{ $dateField('inspection_expires_at') }}"></label>
            <div class="ny-note">Las fechas permiten identificar naves cuyos documentos deben renovarse antes de ponerlas a la venta.</div>
            <label style="grid-column:1/-1">Descripción<textarea name="description" placeholder="Servicios, condiciones, equipamiento de seguridad u observaciones relevantes">{{ $field('description') }}</textarea></label>
        </div></section>

        @if($errors->any())<div style="margin-top:18px;padding:14px;border-radius:10px;background:#fff1f2;color:#be123c"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <button class="ny-save">{{ $isEdit ? 'Guardar cambios' : 'Guardar embarcación' }}</button>
        @if($isEdit)<a class="ny-cancel" href="{{ route('admin.vessels.seats.edit', $vessel) }}">Configurar plano de asientos</a>@endif
        <a class="ny-cancel" href="{{ route('admin.vessels.index') }}">Cancelar</a>
    </form>
</div>

</x-app-layout>