@extends('layouts.company')
@section('content')
<div class="p-4 sm:p-6">
    <h1 class="text-2xl font-black">Secciones de la página</h1>
    <p class="mt-2 text-sm text-slate-600">Salidas y tarifas e Información no se pueden apagar. El resto se puede renombrar, ordenar y ocultar.</p>
    @if(session('success'))<p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    <form method="POST" action="{{ route('company.page-sections.update') }}" class="mt-5 space-y-3">
        @csrf @method('PATCH')
        @foreach($sections as $section)
            <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-white p-3">
                <input type="hidden" name="keys[]" value="{{ $section->key }}">
                <label class="min-w-48 flex-1 text-sm font-semibold">Nombre
                    <input name="labels[{{ $section->key }}]" value="{{ $section->label }}" class="mt-1 w-full rounded-lg border-slate-200" required>
                </label>
                <label class="text-sm font-semibold"><input type="checkbox" name="enabled[{{ $section->key }}]" value="1" @checked($section->is_enabled || $section->isMandatory()) @disabled($section->isMandatory())> Visible</label>
            </div>
        @endforeach
        <button class="rounded-xl bg-[#064E3B] px-4 py-3 text-sm font-bold text-white">Guardar secciones</button>
    </form>
</div>
@endsection
