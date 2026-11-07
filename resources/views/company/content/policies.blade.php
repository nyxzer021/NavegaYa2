@extends('layouts.company')
@section('title', 'Políticas de viaje')
@section('content')
<style>
    trix-toolbar { max-width: 100%; }
    trix-toolbar .trix-button-row { flex-wrap: wrap; }
    trix-toolbar .trix-button-group { margin-bottom: 4px; }
    trix-editor { max-width: 100%; overflow-wrap: anywhere; }
</style>
<div class="p-4 sm:p-6">
    @if(session('success'))<p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    @if($notice)
        <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950">NavegaYA actualizó la política. Motivo: {{ $notice->changes['reason'] ?? 'sin motivo' }}. Lo hizo {{ $notice->changes['edited_by'] ?? 'un administrador' }}.</p>
    @endif
    <form method="POST" action="{{ route('company.policies.update') }}" class="mt-5 space-y-3">
        @csrf
        <link rel="stylesheet" href="https://unpkg.com/trix@2.1.15/dist/trix.css">
        <input id="policy-body" type="hidden" name="body_html" value="{{ old('body_html', $current?->body_html) }}">
        <trix-editor input="policy-body" class="min-h-64 rounded-xl border bg-white"></trix-editor>
        <script src="https://unpkg.com/trix@2.1.15/dist/trix.umd.min.js"></script>
        <div class="grid gap-3 sm:grid-cols-4">
            <label class="text-xs font-bold">Mano (kg)<input type="number" name="baggage_hand_kg" min="0" value="{{ old('baggage_hand_kg', $organization->baggage_hand_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['hand'] }}"></label>
            <label class="text-xs font-bold">Bodega (kg)<input type="number" name="baggage_hold_kg" min="0" value="{{ old('baggage_hold_kg', $organization->baggage_hold_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['hold'] }}"></label>
            <label class="text-xs font-bold">Aéreo (kg)<input type="number" name="baggage_air_kg" min="0" value="{{ old('baggage_air_kg', $organization->baggage_air_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['air'] }}"></label>
            <label class="text-xs font-bold">Carga desde (kg)<input type="number" name="cargo_threshold_kg" min="0" value="{{ old('cargo_threshold_kg', $organization->cargo_threshold_kg) }}" class="mt-1 w-full rounded-lg border-slate-200" placeholder="{{ $baggage['cargo'] }}"></label>
        </div>
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
