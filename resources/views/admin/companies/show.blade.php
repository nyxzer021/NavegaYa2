@extends('layouts.admin')

@section('content')
<h1 class="ny-page-title mb-4">Dossier del operador</h1>
<p class="mb-4 flex flex-wrap gap-4"><a href="{{ route('companies.show', $organization) }}" class="font-bold text-emerald-800">Ver la página pública</a><a href="{{ route('admin.company-policies.index') }}" class="font-bold text-emerald-800">Políticas de empresas</a></p>
<section class="mb-6 rounded-2xl border bg-white p-4">
    <h2 class="text-lg font-black">Política de viaje de la empresa</h2>
    @if($travelVersions->isNotEmpty())
        <ul class="mt-3 space-y-1 text-sm text-slate-700">
            @foreach($travelVersions as $version)
                <li>Versión {{ $version->version }}@if($version->published_at) · {{ $version->published_at->format('d/m/Y H:i') }}@endif @if($version->reason) · {{ $version->reason }}@endif</li>
            @endforeach
        </ul>
    @else
        <p class="mt-2 text-sm text-slate-600">Esta empresa todavía no tiene una política publicada.</p>
    @endif
    <form method="POST" action="{{ route('admin.companies.travel-policy.update', $organization) }}" class="mt-4 grid gap-3">
        @csrf
        <label class="text-sm font-semibold">Nueva versión<textarea name="body_html" rows="6" required class="mt-1 w-full rounded-lg border-slate-200">{{ old('body_html') }}</textarea></label>
        <label class="text-sm font-semibold">Motivo<input name="reason" required minlength="5" class="mt-1 w-full rounded-lg border-slate-200" value="{{ old('reason') }}"></label>
        <button class="h-10 w-fit rounded-lg bg-emerald-900 px-4 text-sm font-bold text-white">Publicar corrección</button>
    </form>
</section>
@php
    $name = $organization->commercial_name ?: $organization->legal_name;
    $activeFleet = $organization->vessels->where('status', 'ready')->count()
        + $organization->aircraft->where('status', 'ready')->count();
    $totalCapacity = $organization->vessels->sum('seat_capacity')
        + $organization->aircraft->sum('seat_capacity');

    $status = match($organization->status) {
        'active' => ['Activo', 'bg-emerald-100 text-emerald-800'],
        'suspended' => ['Suspendido', 'bg-rose-100 text-rose-800'],
        default => ['Pendiente', 'bg-amber-100 text-amber-800'],
    };

    $modalityLabel = match($organization->modality) {
        'aereo' => '✈ Aéreo',
        'mixto' => '🚤 + ✈ Mixto',
        default => '🚤 Fluvial',
    };

    $operationalLabel = fn ($value) => match($value) {
        'ready' => ['Habilitada', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'maintenance' => ['Observada', 'bg-amber-50 text-amber-800 border-amber-200'],
        default => ['Suspendida', 'bg-rose-50 text-rose-700 border-rose-200'],
    };

    $vesselsCompliant = $organization->vessels->isNotEmpty()
        && $organization->vessels->every(fn ($unit) =>
            $unit->status === 'ready'
            && filled($unit->dicapi_certificate_number)
            && $unit->inspection_expires_at
            && ! $unit->inspection_expires_at->isPast()
        );

    $aircraftCompliant = $organization->aircraft->isNotEmpty()
        && $organization->aircraft->every(fn ($unit) =>
            $unit->status === 'ready'
            && filled($unit->dgac_certificate_number)
            && $unit->airworthiness_expires_at
            && ! $unit->airworthiness_expires_at->isPast()
        );
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <a href="{{ route('admin.companies.index') }}"
           class="inline-flex items-center gap-1 text-xs font-bold text-emerald-800 transition hover:text-emerald-950">
            ← Volver a Empresas transportistas
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="relative overflow-hidden rounded-3xl bg-[#062c21] p-6 text-white shadow-xl">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="mb-2 text-[10px] font-black uppercase tracking-[.18em] text-emerald-300">Dossier del operador</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-[10px] font-black {{ $status[1] }}">{{ $status[0] }}</span>
                    <span class="rounded-full border border-cyan-400/25 bg-cyan-500/15 px-3 py-1 text-[10px] font-bold text-cyan-200">{{ $modalityLabel }}</span>
                </div>
                <h1 class="mt-3 text-2xl font-black">{{ $name }}</h1>
                <p class="mt-1 font-mono text-xs text-emerald-100/70">{{ $organization->legal_name }} · RUC {{ $organization->ruc }}</p>
                <p class="mt-3 max-w-2xl text-xs leading-relaxed text-emerald-100/70">
                    {{ $organization->public_description ?: 'Operador afiliado a NavegaYA. Sus unidades están sujetas a supervisión técnica y regulatoria.' }}
                </p>
            </div>

            <a href="{{ route('admin.companies.edit', $organization) }}"
               class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-black text-slate-950 shadow-md transition hover:bg-amber-400">
                Editar operador
            </a>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-3 border-t border-emerald-900/70 pt-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-black/20 p-3 text-center">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-300/80">Flota habilitada</span>
                <strong class="mt-1 block text-lg font-black">{{ $activeFleet }}</strong>
            </div>
            <div class="rounded-2xl bg-black/20 p-3 text-center">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-300/80">Capacidad homologada</span>
                <strong class="mt-1 block text-lg font-black">{{ $totalCapacity }} asientos</strong>
            </div>
            <div class="rounded-2xl bg-black/20 p-3 text-center">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-300/80">Cargo de servicio al viajero</span>
                <strong class="mt-1 block text-lg font-black text-amber-300">{{ number_format((float) $organization->commission_rate, 2) }}%</strong>
                <span class="mt-0.5 block text-[9px] font-bold uppercase text-emerald-100/60">{{ match($organization->commercial_plan ?? 'standard') { 'initial' => 'Nivel inicial', 'strategic' => 'Nivel estratégico', 'custom' => 'Acuerdo personalizado', default => 'Nivel estándar' } }}{{ $organization->agreement_number ? ' · '.$organization->agreement_number : '' }}</span>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-black text-slate-900">Puerto base</h2>
        <p class="mt-1 text-xs text-slate-500">Hoy: {{ $organization->basePort ? $organization->basePort->name.' · '.$organization->basePort->city : 'Puerto base por definir' }}. El cambio pide un motivo y queda auditado.</p>
        <form method="POST" action="{{ route('admin.companies.base-port.update', $organization) }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            @method('PATCH')
            <label class="text-xs font-bold text-slate-600">Puerto del catálogo
                <select name="base_port_id" required class="mt-1 block h-11 min-w-64 rounded-xl border-slate-200 bg-slate-50 text-sm">
                    <option value="">Elige un puerto activo</option>
                    @foreach($ports as $port)
                        <option value="{{ $port->id }}" @selected((string) old('base_port_id', $organization->base_port_id) === (string) $port->id)>{{ $port->name }} · {{ $port->city }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Motivo
                <input name="reason" required minlength="5" maxlength="500" value="{{ old('reason') }}" class="mt-1 block h-11 w-64 rounded-xl border-slate-200 bg-slate-50 text-sm">
            </label>
            <button class="h-11 rounded-xl bg-[#062c21] px-4 text-xs font-black text-white">Guardar puerto base</button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-black text-slate-900">Datos de la empresa</h2>
        <p class="mt-1 text-xs text-slate-500">Corregir estos datos pide un motivo y queda auditado. El RUC solo se cambia desde aquí. La empresa ve el cambio, la fecha y quién lo hizo.</p>
        @if($errors->any())<p class="mt-3 text-xs font-semibold text-rose-800">{{ $errors->first() }}</p>@endif
        <form method="POST" action="{{ route('admin.companies.identity.update', $organization) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            @method('PATCH')
            <label class="text-xs font-bold text-slate-600">RUC<input name="ruc" required maxlength="11" value="{{ old('ruc', $organization->ruc) }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">Razón social<input name="legal_name" required value="{{ old('legal_name', $organization->legal_name) }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">Nombre comercial<input name="commercial_name" value="{{ old('commercial_name', $organization->commercial_name) }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600 sm:col-span-2">Dirección fiscal<input name="address" required value="{{ old('address', $organization->address) }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">Representante legal<input name="contact_name" required value="{{ old('contact_name', $organization->contact_name) }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">DNI del representante<input name="representative_document_number" value="{{ old('representative_document_number', $organization->representative_document_number) }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">Ficha RUC<input type="file" name="ruc_document" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm"></label>
            <label class="text-xs font-bold text-slate-600">Vencimiento de la ficha RUC<input type="date" name="ruc_expires_on" value="{{ old('ruc_expires_on') }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600">Documento del representante<input type="file" name="representative_document" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm"></label>
            <label class="text-xs font-bold text-slate-600">Vencimiento del documento<input type="date" name="representative_expires_on" value="{{ old('representative_expires_on') }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <label class="text-xs font-bold text-slate-600 sm:col-span-2">Motivo<input name="reason" required minlength="5" maxlength="500" value="{{ old('reason') }}" class="mt-1 block h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            <button class="h-11 w-fit rounded-xl bg-[#062c21] px-4 text-xs font-black text-white">Guardar corrección</button>
        </form>
    </section>

    <div class="space-y-5">
            @if(in_array($organization->modality, ['fluvial', 'mixto']))
                <section class="overflow-hidden rounded-2xl border border-emerald-200/70 bg-white shadow-sm">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                        <div>
                            <h2 class="font-black text-slate-900">🚤 Supervisión de embarcaciones fluviales</h2>
                            <p class="text-[10px] text-slate-400">Homologación de capacidad, inspección y certificados DICAPI</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <span class="rounded-lg border border-emerald-200 bg-white px-2.5 py-1 text-xs font-bold text-emerald-800">
                                {{ $organization->vessels->count() }} unidades registradas
                            </span>
                            @if($vesselsCompliant)
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">✓ Cumplimiento DICAPI al día</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">⚠ Revisión DICAPI pendiente</span>
                            @endif
                        </div>
                    </header>

                    @if($organization->vessels->isEmpty())
                        <div class="m-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                            <div class="text-2xl">🚤</div>
                            <h3 class="mt-2 text-sm font-black text-slate-800">Sin unidades para auditar</h3>
                            <p class="mx-auto mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">
                                El operador aún no ha registrado unidades desde su portal de administración. Cuando cargue sus naves, aparecerán aquí para su homologación y verificación técnica.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1120px] text-left text-xs">
                                <thead class="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th class="p-3">Matrícula oficial</th>
                                        <th class="p-3">Nombre / unidad</th>
                                        <th class="p-3">Capacidad homologada</th>
                                        <th class="p-3">Inspección técnica</th>
                                        <th class="p-3">Estado en plataforma</th>
                                        <th class="p-3 text-right">Auditoría / acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($organization->vessels as $vessel)
                                        @php
                                            $inspectionValid = $vessel->inspection_expires_at && ! $vessel->inspection_expires_at->isPast();
                                            [$unitStatus, $unitStatusClass] = $operationalLabel($vessel->status);
                                        @endphp
                                        <tr class="align-top">
                                            <td class="p-3 font-mono font-black text-slate-900">{{ $vessel->registration_number }}</td>
                                            <td class="p-3">
                                                <strong class="text-slate-900">{{ $vessel->name }}</strong>
                                                <span class="block text-[9px] text-slate-400">{{ $vessel->vessel_type ?: 'Tipo por registrar' }}</span>
                                            </td>
                                            <td class="p-3">
                                                <strong>{{ $vessel->seat_capacity }} pasajeros</strong>
                                                <span class="block text-[9px] text-slate-400">{{ $vessel->life_vest_count ?? 0 }} chalecos declarados</span>
                                            </td>
                                            <td class="p-3">
                                                <span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $inspectionValid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                                                    {{ $inspectionValid ? 'Vigente' : 'Pendiente / vencida' }}
                                                </span>
                                                <span class="mt-1 block text-[9px] text-slate-400">
                                                    {{ $vessel->inspection_expires_at?->format('d/m/Y') ?: 'Sin fecha registrada' }}
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[9px] font-bold {{ $unitStatusClass }}">{{ $unitStatus }}</span>
                                            </td>
                                            <td class="p-3">
                                                <div class="flex items-start justify-end gap-2">
                                                    <details class="relative">
                                                        <summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-3 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50">
                                                            📄 Ver documento DICAPI
                                                        </summary>
                                                        <div class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                                                            <span class="text-[9px] font-bold uppercase text-slate-400">Certificado DICAPI</span>
                                                            <strong class="mt-1 block font-mono text-xs text-slate-900">{{ $vessel->dicapi_certificate_number ?: 'Número no registrado' }}</strong>
                                                            <p class="mt-2 text-[10px] text-slate-500">Vencimiento de inspección: {{ $vessel->inspection_expires_at?->format('d/m/Y') ?: 'pendiente' }}</p>
                                                            <p class="mt-1 text-[10px] text-slate-400">El operador todavía no ha cargado un archivo documental descargable.</p>
                                                        </div>
                                                    </details>
                                                    @if($vessel->status === 'ready')<form method="POST" action="{{ route('admin.fleet.vessels.status', $vessel) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="maintenance">
                                                        <button class="rounded-lg bg-rose-50 px-3 py-2 text-[10px] font-black text-rose-700 hover:bg-rose-100">Suspender operación</button>
                                                    </form>@else<a href="{{ route('admin.fleet-compliance.index') }}" class="rounded-lg bg-amber-100 px-3 py-2 text-[10px] font-black text-amber-900">Revisar solicitud →</a>@endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            @if(in_array($organization->modality, ['aereo', 'mixto']))
                <section class="overflow-hidden rounded-2xl border border-cyan-200/70 bg-white shadow-sm">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                        <div>
                            <h2 class="font-black text-slate-900">✈️ Supervisión de aeronaves regionales</h2>
                            <p class="text-[10px] text-slate-400">Homologación de capacidad, aeronavegabilidad y certificados DGAC</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <span class="rounded-lg border border-cyan-200 bg-white px-2.5 py-1 text-xs font-bold text-cyan-800">
                                {{ $organization->aircraft->count() }} aeronaves registradas
                            </span>
                            @if($aircraftCompliant)
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">✓ Cumplimiento DGAC al día</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">⚠ Revisión DGAC pendiente</span>
                            @endif
                        </div>
                    </header>

                    @if($organization->aircraft->isEmpty())
                        <div class="m-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                            <div class="text-2xl">✈️</div>
                            <h3 class="mt-2 text-sm font-black text-slate-800">Sin unidades para auditar</h3>
                            <p class="mx-auto mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">
                                El operador aún no ha registrado unidades desde su portal de administración. Cuando cargue sus aeronaves, aparecerán aquí para su homologación y verificación técnica.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1120px] text-left text-xs">
                                <thead class="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th class="p-3">Matrícula oficial</th>
                                        <th class="p-3">Nombre / unidad</th>
                                        <th class="p-3">Capacidad homologada</th>
                                        <th class="p-3">Inspección técnica</th>
                                        <th class="p-3">Estado en plataforma</th>
                                        <th class="p-3 text-right">Auditoría / acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($organization->aircraft as $aircraft)
                                        @php
                                            $inspectionValid = $aircraft->airworthiness_expires_at && ! $aircraft->airworthiness_expires_at->isPast();
                                            [$unitStatus, $unitStatusClass] = $operationalLabel($aircraft->status);
                                        @endphp
                                        <tr class="align-top">
                                            <td class="p-3 font-mono font-black text-slate-900">{{ $aircraft->registration_number }}</td>
                                            <td class="p-3">
                                                <strong class="text-slate-900">{{ $aircraft->name }}</strong>
                                                <span class="block text-[9px] text-slate-400">{{ $aircraft->model ?: 'Modelo por registrar' }} · {{ $aircraft->manufacturer ?: 'Fabricante pendiente' }}</span>
                                            </td>
                                            <td class="p-3"><strong>{{ $aircraft->seat_capacity }} pasajeros</strong></td>
                                            <td class="p-3">
                                                <span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $inspectionValid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                                                    {{ $inspectionValid ? 'Aeronavegabilidad vigente' : 'Pendiente / vencida' }}
                                                </span>
                                                <span class="mt-1 block text-[9px] text-slate-400">
                                                    {{ $aircraft->airworthiness_expires_at?->format('d/m/Y') ?: 'Sin fecha registrada' }}
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[9px] font-bold {{ $unitStatusClass }}">{{ $unitStatus }}</span>
                                            </td>
                                            <td class="p-3">
                                                <div class="flex items-start justify-end gap-2">
                                                    <details class="relative">
                                                        <summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-3 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50">
                                                            📄 Ver documento DGAC
                                                        </summary>
                                                        <div class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                                                            <span class="text-[9px] font-bold uppercase text-slate-400">Certificación DGAC</span>
                                                            <strong class="mt-1 block font-mono text-xs text-slate-900">{{ $aircraft->dgac_certificate_number ?: 'Número no registrado' }}</strong>
                                                            <p class="mt-2 text-[10px] text-slate-500">CDA: {{ $aircraft->airworthiness_certificate_number ?: 'pendiente' }}</p>
                                                            <p class="mt-1 text-[10px] text-slate-500">Vencimiento: {{ $aircraft->airworthiness_expires_at?->format('d/m/Y') ?: 'pendiente' }}</p>
                                                            <p class="mt-1 text-[10px] text-slate-400">El operador todavía no ha cargado un archivo documental descargable.</p>
                                                        </div>
                                                    </details>
                                                    <form method="POST" action="{{ route('admin.fleet.aircraft.status', $aircraft) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $aircraft->status === 'ready' ? 'maintenance' : 'ready' }}">
                                                        <button class="rounded-lg px-3 py-2 text-[10px] font-black {{ $aircraft->status === 'ready' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                                            {{ $aircraft->status === 'ready' ? 'Suspender / observar' : 'Habilitar para venta' }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif
        </div>
        @include('admin.companies.operation')
</div>
@endsection



