@extends('layouts.admin')
@section('header')
    <h1 class="ny-page-title">Modalidades por aprobar</h1>
@endsection
@section('content')
<div class="mx-auto max-w-5xl space-y-4">
    @if(session('success'))<p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ $errors->first() }}</p>@endif
    @forelse($modalities as $modality)
        <article class="rounded-2xl border bg-white p-4">
            <h2 class="font-semibold">{{ $modality->organization?->commercial_name ?: $modality->organization?->legal_name }} · {{ $modality->label() }}</h2>
            <p class="mt-1 text-sm text-slate-600">Permiso {{ $modality->permit_number ?: 'sin número' }} · vence {{ $modality->permit_expires_on?->format('d/m/Y') ?: 'sin fecha' }}</p>
            <form method="POST" action="{{ route('admin.companies.modalities.review', [$modality->organization, $modality]) }}" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]">
                @csrf @method('PATCH')
                <select name="decision" class="rounded-lg border px-3 py-2 text-sm" required>
                    <option value="approved">Aprobar</option>
                    <option value="rejected">Rechazar</option>
                </select>
                <input name="reason" required placeholder="Motivo" class="rounded-lg border px-3 py-2 text-sm">
                <button class="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">Guardar decisión</button>
            </form>
        </article>
    @empty
        <p class="text-sm text-slate-600">No hay modalidades esperando decisión.</p>
    @endforelse
</div>
@endsection
