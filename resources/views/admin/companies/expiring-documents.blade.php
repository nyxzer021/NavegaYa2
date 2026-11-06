@extends('layouts.admin')
@section('header')
    <h1 class="ny-page-title">Documentos por vencer</h1>
@endsection
@section('content')
<div class="mx-auto max-w-5xl space-y-3">
    <p class="text-sm text-slate-600">Vencen en los próximos 30 días.</p>
    @foreach($documents as $document)
        <article class="rounded-2xl border bg-white p-4 text-sm">
            <a class="font-semibold text-emerald-800" href="{{ route('admin.companies.show', $document->organization) }}#documentos">{{ $document->organization?->commercial_name ?: $document->organization?->legal_name }}</a>
            <p>{{ $document->type }} · {{ $document->reference_number ?: 'sin número' }} · {{ $document->expires_on?->format('d/m/Y') }}</p>
        </article>
    @endforeach
    @foreach($modalities as $modality)
        <article class="rounded-2xl border bg-white p-4 text-sm">
            <a class="font-semibold text-emerald-800" href="{{ route('admin.companies.show', $modality->organization) }}#modalidades">{{ $modality->organization?->commercial_name ?: $modality->organization?->legal_name }}</a>
            <p>{{ $modality->label() }} · permiso {{ $modality->permit_expires_on?->format('d/m/Y') ?: 'sin fecha' }} · AOC {{ $modality->aoc_expires_on?->format('d/m/Y') ?: 'sin fecha' }}</p>
        </article>
    @endforeach
    @if($documents->isEmpty() && $modalities->isEmpty())
        <p class="text-sm text-slate-600">No hay documentos por vencer.</p>
    @endif
</div>
@endsection
