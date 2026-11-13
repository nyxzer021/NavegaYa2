@extends('layouts.admin')
@section('content')
<h1 class="ny-page-title mb-4">Incidencias</h1>
<p class="mb-4 text-sm text-slate-600">Solo lectura. Las fotos no están en la web pública.</p>
<section class="mb-6 rounded-2xl border bg-white p-4">
    <h2 class="text-lg font-black">Licencias vencidas</h2>
    @forelse($expiredLicenses as $member)
        <p class="mt-2 text-sm text-slate-700">{{ $member->organization?->commercial_name ?: $member->organization?->legal_name }} · {{ $member->name }} · {{ $member->document_number }} · {{ $member->phone ?: 'sin teléfono' }} · venció {{ $member->license_expires_on->format('d/m/Y') }}</p>
    @empty
        <p class="mt-2 text-sm text-slate-600">No hay licencias vencidas.</p>
    @endforelse
</section>
<section class="rounded-2xl border bg-white p-4">
    <h2 class="text-lg font-black">Últimas incidencias</h2>
    @forelse($incidents as $incident)
        <article class="mt-3 border-t border-slate-100 pt-3 text-sm">
            <strong>{{ $incident->organization?->commercial_name ?: $incident->organization?->legal_name }}</strong>
            <span class="text-slate-500"> · {{ $incident->kind }} · {{ $incident->severity }} · {{ $incident->action }}</span>
            <p class="mt-1">{{ $incident->description }}</p>
            @foreach($incident->photos as $photo)
                <a href="{{ route('admin.incidents.photos.show', $photo) }}" class="mr-3 font-bold text-emerald-800">{{ $photo->original_name ?: 'Foto' }}</a>
            @endforeach
        </article>
    @empty
        <p class="mt-2 text-sm text-slate-600">No hay incidencias registradas.</p>
    @endforelse
</section>
@endsection
