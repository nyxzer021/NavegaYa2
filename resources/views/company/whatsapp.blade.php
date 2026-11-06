@extends('layouts.company')
@section('title', __('company_portal.menu.whatsapp'))
@section('page-title', __('company_portal.menu.whatsapp'))
@section('content')
@php
    $ticket = old('whatsapp', \App\Support\PhoneNumber::display($organization->whatsapp) ? substr(\App\Support\PhoneNumber::peru($organization->whatsapp) ?? '', 2) : $organization->whatsapp);
    $cargo = old('whatsapp_cargo', $organization->whatsapp_cargo ? substr((string) \App\Support\PhoneNumber::peru($organization->whatsapp_cargo), 2) : '');
    $previewNumber = \App\Support\PhoneNumber::peru($ticket);
    $previewCargo = \App\Support\PhoneNumber::peru($cargo) ?: $previewNumber;
    $previewMessage = old('whatsapp_message', $organization->whatsapp_message) ?: 'Hola, deseo información sobre sus viajes.';
@endphp
<div class="mx-auto max-w-3xl space-y-4">
    @if(session('success'))<x-ui.card><p class="text-sm font-semibold text-emerald-800">{{ session('success') }}</p></x-ui.card>@endif
    <x-ui.card>
        <form method="POST" action="{{ route('company.whatsapp.update') }}" class="space-y-3" id="company-whatsapp-form">
            @csrf @method('PATCH')
            <label class="ny-ui-field"><span>WhatsApp de boletería</span>
                <span class="flex items-center gap-2"><span class="text-sm font-semibold">+51</span><input name="whatsapp" inputmode="numeric" value="{{ $ticket }}" required data-whatsapp-ticket></span>
            </label>
            @error('whatsapp')<p class="text-sm text-rose-800">{{ $message }}</p>@enderror
            <label class="ny-ui-field"><span>WhatsApp de carga</span>
                <span class="flex items-center gap-2"><span class="text-sm font-semibold">+51</span><input name="whatsapp_cargo" inputmode="numeric" value="{{ $cargo }}" data-whatsapp-cargo></span>
                <small class="text-xs text-[#607069]">Si lo dejas vacío, la cotización de carga usa el número de boletería.</small>
            </label>
            @error('whatsapp_cargo')<p class="text-sm text-rose-800">{{ $message }}</p>@enderror
            <label class="ny-ui-field"><span>Mensaje inicial</span><textarea name="whatsapp_message" rows="3">{{ old('whatsapp_message', $organization->whatsapp_message) }}</textarea></label>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input name="whatsapp_hours_from" label="Atiende desde" type="time" value="{{ old('whatsapp_hours_from', $organization->whatsapp_hours_from) }}" />
                <x-ui.input name="whatsapp_hours_until" label="Atiende hasta" type="time" value="{{ old('whatsapp_hours_until', $organization->whatsapp_hours_until) }}" />
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_public_whatsapp" value="1" @checked(old('show_public_whatsapp', $organization->show_public_whatsapp))> Mostrar en la página y en las salidas</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="whatsapp_show_outside_hours" value="1" @checked(old('whatsapp_show_outside_hours', $organization->whatsapp_show_outside_hours))> Mostrar fuera de horario con el aviso de respuesta</label>
            <x-ui.button type="submit" variant="primary">Guardar WhatsApp</x-ui.button>
        </form>
        @if($lastChange)
            <p class="mt-4 text-sm text-[#607069]">Último cambio: {{ $lastChange->user?->name ?: 'Usuario' }}, {{ $lastChange->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.</p>
        @endif
        @if($organization->whatsapp_hidden_by_admin)
            <p class="mt-2 text-sm text-rose-800">NavegaYA ocultó el botón público. {{ $organization->whatsapp_hidden_reason }}</p>
        @endif
    </x-ui.card>
    <x-ui.card>
        <h2 class="text-base font-semibold">Vista previa</h2>
        <p class="mt-2 text-sm" data-preview-label>{{ $previewNumber ? \App\Support\PhoneNumber::display($previewNumber) : 'El número todavía no es válido.' }}</p>
        <a data-preview-link class="mt-3 inline-flex" target="_blank" rel="noopener" href="{{ $previewNumber ? 'https://wa.me/'.$previewNumber.'?text='.urlencode($previewMessage) : '#' }}">Probar enlace de boletería</a>
        <p class="mt-2 text-xs text-[#607069]" data-preview-cargo>Carga: {{ $previewCargo ? 'https://wa.me/'.$previewCargo : 'sin número' }}</p>
    </x-ui.card>
</div>
<script>
(function () {
    var form = document.getElementById('company-whatsapp-form');
    if (!form) return;
    var ticket = form.querySelector('[data-whatsapp-ticket]');
    var cargo = form.querySelector('[data-whatsapp-cargo]');
    var link = document.querySelector('[data-preview-link]');
    var label = document.querySelector('[data-preview-label]');
    var cargoLine = document.querySelector('[data-preview-cargo]');
    function digits(value) {
        var clean = String(value || '').replace(/\D/g, '');
        if (clean.indexOf('51') === 0 && clean.length === 11) clean = clean.slice(2);
        return /^9\d{8}$/.test(clean) ? '51' + clean : '';
    }
    function paint() {
        var number = digits(ticket.value);
        var cargoNumber = digits(cargo.value) || number;
        var message = form.querySelector('[name=whatsapp_message]').value || 'Hola, deseo información sobre sus viajes.';
        label.textContent = number ? '+51 ' + number.slice(2, 5) + ' ' + number.slice(5, 8) + ' ' + number.slice(8) : 'El número todavía no es válido.';
        link.href = number ? 'https://wa.me/' + number + '?text=' + encodeURIComponent(message) : '#';
        cargoLine.textContent = 'Carga: ' + (cargoNumber ? 'https://wa.me/' + cargoNumber : 'sin número');
    }
    form.addEventListener('input', paint);
})();
</script>
@endsection
