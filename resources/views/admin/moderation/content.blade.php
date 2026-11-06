@extends('layouts.admin')
@section('header')<h1 class="ny-page-title">Contenido por moderar</h1>@endsection
@section('content')
<div class="space-y-6">
    @if(session('success'))<p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    <section class="rounded-2xl border bg-white p-4">
        <h2 class="font-black">Fotos pendientes</h2>
        @forelse($photos as $photo)
            <form method="POST" action="{{ route('admin.content-moderation.photos.update', $photo) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                @csrf @method('PATCH')
                <span class="font-semibold">{{ $photo->organization?->commercial_name ?: 'Empresa' }}</span>
                <select name="status" class="rounded-lg border-slate-200"><option value="published">Publicar</option><option value="hidden">Ocultar</option></select>
                <input name="reason" required minlength="5" placeholder="Motivo" class="rounded-lg border-slate-200">
                <button class="rounded-lg bg-emerald-900 px-3 py-2 font-bold text-white">Guardar</button>
            </form>
        @empty
            <p class="mt-2 text-sm text-slate-500">No hay fotos pendientes.</p>
        @endforelse
    </section>
    <section class="rounded-2xl border bg-white p-4">
        <h2 class="font-black">Comentarios de fotos</h2>
        @forelse($comments as $comment)
            <form method="POST" action="{{ route('admin.content-moderation.comments.update', $comment) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                @csrf @method('PATCH')
                <span>{{ $comment->body }}</span>
                <select name="status" class="rounded-lg border-slate-200"><option value="published">Publicar</option><option value="hidden">Ocultar</option></select>
                <input name="reason" required minlength="5" placeholder="Motivo" class="rounded-lg border-slate-200">
                <button class="rounded-lg bg-emerald-900 px-3 py-2 font-bold text-white">Guardar</button>
            </form>
        @empty
            <p class="mt-2 text-sm text-slate-500">No hay comentarios pendientes.</p>
        @endforelse
    </section>
    <section class="rounded-2xl border bg-white p-4">
        <h2 class="font-black">Opiniones con estrellas pendientes</h2>
        @forelse($reviews as $review)
            <p class="mt-2 text-sm">{{ $review->organization?->commercial_name }} · {{ $review->rating }} estrellas · {{ $review->comment }}</p>
        @empty
            <p class="mt-2 text-sm text-slate-500">No hay opiniones pendientes.</p>
        @endforelse
    </section>
    <section class="rounded-2xl border bg-white p-4">
        <h2 class="font-black">Sanción de usuario</h2>
        <form method="POST" action="{{ route('admin.content-moderation.sanctions.store') }}" class="mt-3 grid gap-2 sm:grid-cols-2">
            @csrf
            <label class="text-sm font-semibold">Usuario<input name="user_id" required class="mt-1 w-full rounded-lg border-slate-200" placeholder="ID"></label>
            <label class="text-sm font-semibold">Tipo<select name="kind" class="mt-1 w-full rounded-lg border-slate-200"><option value="warning">Advertencia</option><option value="suspension">Suspensión</option><option value="ban">Bloqueo permanente</option></select></label>
            <label class="text-sm font-semibold">Hasta<input type="datetime-local" name="ends_at" class="mt-1 w-full rounded-lg border-slate-200"></label>
            <label class="text-sm font-semibold sm:col-span-2">Motivo<input name="reason" required minlength="5" class="mt-1 w-full rounded-lg border-slate-200"></label>
            <button class="rounded-lg bg-emerald-900 px-3 py-2 text-sm font-bold text-white">Registrar sanción</button>
        </form>
    </section>
</div>
@endsection
