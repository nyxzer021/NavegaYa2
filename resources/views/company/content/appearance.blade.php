@extends('layouts.company')
@section('title', 'Apariencia')
@section('content')
<div class="p-4 sm:p-6">
    <p class="text-sm text-slate-600">Estos colores se aplican solo a la página pública de esta empresa.</p>
    @if(session('success'))<p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    <form method="POST" action="{{ route('company.appearance.update') }}" class="mt-5 grid max-w-xl gap-3">
        @csrf @method('PATCH')
        <label class="text-sm font-semibold">Color principal<input type="color" name="public_primary_color" value="{{ old('public_primary_color', $organization->public_primary_color ?: '#064E3B') }}" class="mt-1 block h-11 w-full"></label>
        <label class="text-sm font-semibold">Color de acento<input type="color" name="public_accent_color" value="{{ old('public_accent_color', $organization->public_accent_color ?: '#F4B820') }}" class="mt-1 block h-11 w-full"></label>
        <button class="rounded-xl bg-[#064E3B] px-4 py-3 text-sm font-bold text-white">Guardar colores</button>
    </form>
    <form method="POST" action="{{ route('company.appearance.update') }}" class="mt-3">
        @csrf @method('PATCH')
        <input type="hidden" name="reset" value="1">
        <button class="text-sm font-bold text-emerald-800">Restablecer colores de NavegaYA</button>
    </form>
</div>
@endsection
