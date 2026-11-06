@extends('layouts.company')
@section('title', __('company_portal.menu.company_details'))
@section('page-title', __('company_portal.menu.company_details'))
@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    @if(session('success'))<x-ui.card><p class="text-sm font-semibold text-emerald-800">{{ session('success') }}</p></x-ui.card>@endif
    @if($errors->any())<x-ui.card><p class="text-sm font-semibold text-rose-800">{{ $errors->first() }}</p></x-ui.card>@endif

    <x-ui.card>
        <form method="POST" action="{{ route('company.details.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PATCH')
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="ny-ui-field"><span>RUC</span><input value="{{ $organization->ruc }}" readonly></label>
                <x-ui.input name="legal_name" label="Razón social" value="{{ old('legal_name', $organization->legal_name) }}" required />
                <x-ui.input name="commercial_name" label="Nombre comercial" value="{{ old('commercial_name', $organization->commercial_name) }}" />
                <label class="ny-ui-field"><span>Puerto o aeródromo base</span>
                    <select name="base_port_id">
                        <option value="">Sin puerto elegido</option>
                        @foreach($ports as $port)
                            <option value="{{ $port->id }}" @selected((string) old('base_port_id', $organization->base_port_id) === (string) $port->id)>{{ $port->name }} · {{ $port->city }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('messages.index', ['asunto' => 'Solicitud de puerto base']) }}" class="mt-2 inline-block text-sm font-semibold text-emerald-800">¿No está tu puerto? Solicítalo a NavegaYA</a>
                </label>
                <x-ui.input name="address" label="Dirección fiscal" value="{{ old('address', $organization->address) }}" required class="sm:col-span-2" />
                <x-ui.input name="base_city" label="Ciudad" value="{{ old('base_city', $organization->base_city) }}" required />
                <x-ui.input name="contact_name" label="Representante legal" value="{{ old('contact_name', $organization->contact_name) }}" required />
                <x-ui.input name="representative_document_number" label="DNI del representante" value="{{ old('representative_document_number', $organization->representative_document_number) }}" />
                <x-ui.input name="representative_email" label="Correo del representante" type="email" value="{{ old('representative_email', $organization->representative_email) }}" />
                <x-ui.input name="representative_phone" label="Teléfono del representante" value="{{ old('representative_phone', $organization->representative_phone) }}" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="ny-ui-field"><span>Ficha RUC</span><input type="file" name="ruc_document" accept=".pdf,.jpg,.jpeg,.png"></label>
                <x-ui.input name="ruc_expires_on" label="Vencimiento de la ficha RUC" type="date" value="{{ old('ruc_expires_on') }}" />
                <x-ui.input name="ruc_reference" label="Número de la ficha RUC" value="{{ old('ruc_reference') }}" />
                <label class="ny-ui-field"><span>Documento del representante</span><input type="file" name="representative_document" accept=".pdf,.jpg,.jpeg,.png"></label>
                <x-ui.input name="representative_expires_on" label="Vencimiento del documento" type="date" value="{{ old('representative_expires_on') }}" />
                <x-ui.input name="representative_reference" label="Número del documento" value="{{ old('representative_reference') }}" />
            </div>
            <x-ui.button type="submit" variant="primary">Guardar datos</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card>
        <h2 class="text-base font-semibold">Modalidades</h2>
        <ul class="mt-3 space-y-2">
            @forelse($organization->modalities as $modality)
                <li class="rounded-lg border border-[#E6ECE9] px-3 py-2 text-sm">
                    <strong>{{ $modality->label() }}</strong>
                    <span class="ml-2">{{ ['requested' => 'Solicitada', 'approved' => 'Aprobada', 'rejected' => 'Rechazada'][$modality->status] ?? $modality->status }}</span>
                    @if($modality->permit_number)<span class="ml-2 text-[#607069]">{{ $modality->permit_number }}</span>@endif
                    @if($modality->status === 'rejected' && $modality->rejection_reason)
                        <p class="mt-1 text-rose-800">{{ $modality->rejection_reason }}</p>
                    @endif
                </li>
            @empty
                <li class="text-sm text-[#607069]">Todavía no hay modalidades registradas. La operación actual figura como {{ $organization->modality === 'aereo' ? 'aérea' : ($organization->modality === 'mixto' ? 'mixta' : 'fluvial') }}.</li>
            @endforelse
        </ul>
    </x-ui.card>

    <x-ui.card>
        <h2 class="text-base font-semibold">Solicitar nueva modalidad</h2>
        <form method="POST" action="{{ route('company.modalities.store') }}" enctype="multipart/form-data" class="mt-3 space-y-3" data-modality-request>
            @csrf
            <label class="ny-ui-field"><span>Modalidad</span>
                <select name="code" required>
                    <option value="fluvial">Fluvial</option>
                    <option value="aereo">Aéreo</option>
                    <option value="aereo_fluvial">Aéreo fluvial</option>
                </select>
            </label>
            <label class="ny-ui-field" data-fluvial-only><span>Permiso de operación</span>
                <select name="permit_scope">
                    <option value="regional">Regional</option>
                    <option value="national">Nacional</option>
                </select>
            </label>
            <x-ui.input name="permit_number" label="Número de permiso" required />
            <x-ui.input name="permit_expires_on" label="Vencimiento del permiso" type="date" required />
            <label class="ny-ui-field"><span>Archivo del permiso</span><input type="file" name="permit" accept=".pdf,.jpg,.jpeg,.png" required></label>
            <div data-air-only class="space-y-3">
                <x-ui.input name="aoc_number" label="Número de AOC" />
                <x-ui.input name="aoc_expires_on" label="Vencimiento del AOC" type="date" />
                <label class="ny-ui-field"><span>Archivo del AOC</span><input type="file" name="aoc" accept=".pdf,.jpg,.jpeg,.png"></label>
            </div>
            <x-ui.button type="submit" variant="primary">Enviar solicitud</x-ui.button>
        </form>
    </x-ui.card>
</div>
<script>
document.querySelectorAll('[data-modality-request]').forEach(function (form) {
    var select = form.querySelector('[name=code]');
    function paint() {
        var air = select.value !== 'fluvial';
        form.querySelector('[data-fluvial-only]').hidden = air;
        form.querySelector('[data-air-only]').hidden = !air;
    }
    select.addEventListener('change', paint);
    paint();
});
</script>
@endsection
