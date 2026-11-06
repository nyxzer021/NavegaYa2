@extends('layouts.admin')
@section('header')<h1 class="ny-page-title">Políticas base</h1>@endsection
@section('content')
<div class="space-y-4">
    @if(session('success'))<p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    <form method="POST" action="{{ route('admin.base-policies.update') }}" class="space-y-3 rounded-2xl border bg-white p-4">
        @csrf
        <label class="block text-sm font-semibold">Resumen<input name="summary" value="{{ old('summary', $current?->summary) }}" class="mt-1 w-full rounded-lg border-slate-200"></label>
        <label class="block text-sm font-semibold">Texto<textarea name="body_html" rows="12" required class="mt-1 w-full rounded-lg border-slate-200">{{ old('body_html', $current?->body_html) }}</textarea></label>
        <label class="block text-sm font-semibold">Motivo del cambio<input name="reason" required minlength="5" class="mt-1 w-full rounded-lg border-slate-200"></label>
        <button class="rounded-xl bg-emerald-900 px-4 py-3 text-sm font-bold text-white">Publicar versión</button>
    </form>
    <ul class="space-y-2 text-sm">
        @foreach($versions as $version)
            <li class="rounded-xl border bg-white p-3">Versión {{ $version->version }} · {{ $version->reason }}</li>
        @endforeach
    </ul>
</div>
@endsection
