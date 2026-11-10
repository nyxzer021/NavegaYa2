@extends('layouts.company')
@section('title', 'Políticas de viaje')
@section('content')
<style>
    trix-toolbar { max-width: 100%; }
    trix-toolbar .trix-button-row { flex-wrap: wrap; }
    trix-toolbar .trix-button-group { margin-bottom: 4px; }
    trix-editor { max-width: 100%; overflow-wrap: anywhere; }
    trix-editor mark { background: #FDE68A; border-radius: 3px; }
</style>
<div class="p-4 sm:p-6">
    @if(session('success'))<p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    @error('body_html')<p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ $message }}</p>@enderror
    @if($current && \App\Support\PolicyHtml::hasPlaceholders($current->body_html))
        <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">{{ trans('company_portal.policy.incomplete', [], 'es') }}</p>
    @endif
    @if($notice)
        <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950">NavegaYA actualizó la política. Motivo: {{ $notice->changes['reason'] ?? 'sin motivo' }}. Lo hizo {{ $notice->changes['edited_by'] ?? 'un administrador' }}.</p>
    @endif
    <form method="POST" action="{{ route('company.policies.update') }}" class="mt-5 space-y-3">
        @csrf
        <link rel="stylesheet" href="https://unpkg.com/trix@2.1.15/dist/trix.css">
        @php
            $policyBody = old('body_html', $current?->body_html);
            if ($errors->has('body_html') && is_string($policyBody)) {
                $policyBody = \App\Support\PolicyHtml::highlightPlaceholders($policyBody);
            }
        @endphp
        <input id="policy-body" type="hidden" name="body_html" value="{{ $policyBody }}">
        <trix-editor input="policy-body" class="min-h-64 rounded-xl border bg-white"></trix-editor>
        <script>
            document.addEventListener('trix-before-initialize', function () {
                if (!window.Trix) return;
                Trix.config.textAttributes.placeholderMark = {
                    tagName: 'mark',
                    inheritable: true,
                    parser: function (element) { return element.tagName === 'MARK'; }
                };
            });
        </script>
        <script src="https://unpkg.com/trix@2.1.15/dist/trix.umd.min.js"></script>
        <div class="grid gap-3 sm:grid-cols-4">
            <label class="text-xs font-bold">Mano (kg)<input type="number" name="baggage_hand_kg" min="0" value="{{ old('baggage_hand_kg', $organization->baggage_hand_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['hand'] }}"></label>
            <label class="text-xs font-bold">Bodega (kg)<input type="number" name="baggage_hold_kg" min="0" value="{{ old('baggage_hold_kg', $organization->baggage_hold_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['hold'] }}"></label>
            <label class="text-xs font-bold">Aéreo (kg)<input type="number" name="baggage_air_kg" min="0" value="{{ old('baggage_air_kg', $organization->baggage_air_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['air'] }}"></label>
            <label class="text-xs font-bold">Carga desde (kg)<input type="number" name="cargo_threshold_kg" min="0" value="{{ old('cargo_threshold_kg', $organization->cargo_threshold_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['cargo'] }}"></label>
        </div>
        @php
            $offersRiver = in_array($organization->modality ?: 'fluvial', ['fluvial', 'mixto'], true);
            $offersAir = in_array($organization->modality, ['aereo', 'mixto'], true);
        @endphp
        <div id="baggage-preview" class="rounded-xl bg-slate-50 p-3 text-sm text-slate-800" data-river="{{ $offersRiver ? '1' : '0' }}" data-air="{{ $offersAir ? '1' : '0' }}" data-river-text="{{ trans('public.baggage_river', ['hand' => ':hand', 'hold' => ':hold', 'cargo' => ':cargo'], 'es') }}" data-air-text="{{ trans('public.baggage_air', ['air' => ':air'], 'es') }}">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ trans('company_portal.baggage_preview', [], 'es') }}</p>
            @foreach(\App\Support\BaggageAllowance::lines($organization) as $line)
                <p class="mt-1" data-baggage-line>{{ $line }}</p>
            @endforeach
        </div>
        <script>
            (function () {
                var box = document.getElementById('baggage-preview');
                if (!box) return;
                var fields = {
                    hand: document.querySelector('[name=baggage_hand_kg]'),
                    hold: document.querySelector('[name=baggage_hold_kg]'),
                    air: document.querySelector('[name=baggage_air_kg]'),
                    cargo: document.querySelector('[name=cargo_threshold_kg]')
                };
                function amount(field) {
                    if (!field) return '';
                    return field.value !== '' ? field.value : (field.placeholder || '');
                }
                function paint() {
                    var values = { hand: amount(fields.hand), hold: amount(fields.hold), air: amount(fields.air), cargo: amount(fields.cargo) };
                    var lines = [];
                    if (box.getAttribute('data-river') === '1') lines.push(box.getAttribute('data-river-text').replace(':hand', values.hand).replace(':hold', values.hold).replace(':cargo', values.cargo));
                    if (box.getAttribute('data-air') === '1') lines.push(box.getAttribute('data-air-text').replace(':air', values.air));
                    box.querySelectorAll('[data-baggage-line]').forEach(function (node) { node.remove(); });
                    lines.forEach(function (line) {
                        var paragraph = document.createElement('p');
                        paragraph.className = 'mt-1';
                        paragraph.setAttribute('data-baggage-line', '');
                        paragraph.textContent = line;
                        box.appendChild(paragraph);
                    });
                }
                Object.keys(fields).forEach(function (key) {
                    if (fields[key]) fields[key].addEventListener('input', paint);
                });
            }());
        </script>
        <button class="rounded-xl bg-[#064E3B] px-4 py-3 text-sm font-bold text-white">Publicar versión</button>
    </form>
    <h2 class="mt-8 text-lg font-black">Versiones</h2>
    <ul class="mt-3 space-y-2">
        @foreach($versions as $version)
            <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-white p-3 text-sm">
                <span>Versión {{ $version->version }} · {{ $version->published_at?->format('d/m/Y H:i') }}@if($version->reason) · {{ $version->reason }}@endif</span>
                <form method="POST" action="{{ route('company.policies.restore', $version) }}">@csrf<button class="font-bold text-emerald-800">Restaurar</button></form>
            </li>
        @endforeach
    </ul>
</div>
@endsection
