@extends('layouts.admin')
@section('content')
<h1 class="ny-page-title mb-4">Políticas de empresas</h1>
<div class="space-y-3 md:hidden">
    @forelse($documents as $document)
        @php($current = $document->versions->first())
        <article class="rounded-2xl border bg-white p-4 text-sm">
            <strong class="block">{{ $document->organization?->commercial_name ?: $document->organization?->legal_name ?: 'Empresa' }}</strong>
            <p class="mt-1 text-slate-600">Versión {{ $current?->version ?: '—' }} · {{ $current?->published_at?->format('d/m/Y H:i') ?: '—' }}</p>
            @if($current?->reason)<p class="mt-1">{{ $current->reason }}</p>@endif
            @if($document->organization)
                <a href="{{ route('admin.companies.show', $document->organization) }}" class="mt-3 inline-flex font-bold text-emerald-800">Abrir ficha</a>
            @endif
        </article>
    @empty
        <p class="rounded-2xl border bg-white px-4 py-8 text-center text-sm text-slate-500">Ninguna empresa publicó todavía su política de viaje.</p>
    @endforelse
</div>
<div class="hidden overflow-x-auto rounded-2xl border bg-white md:block">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Empresa</th>
                <th class="px-4 py-3">Versión vigente</th>
                <th class="px-4 py-3">Publicada</th>
                <th class="px-4 py-3">Motivo</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($documents as $document)
                @php($current = $document->versions->first())
                <tr class="border-t">
                    <td class="px-4 py-3 font-semibold">{{ $document->organization?->commercial_name ?: $document->organization?->legal_name ?: 'Empresa' }}</td>
                    <td class="px-4 py-3">{{ $current?->version ?: '—' }}</td>
                    <td class="px-4 py-3">{{ $current?->published_at?->format('d/m/Y H:i') ?: '—' }}</td>
                    <td class="max-w-xs px-4 py-3">{{ $current?->reason ?: '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($document->organization)
                            <a href="{{ route('admin.companies.show', $document->organization) }}" class="font-bold text-emerald-800">Abrir ficha</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Ninguna empresa publicó todavía su política de viaje.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
