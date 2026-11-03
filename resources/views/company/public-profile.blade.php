@extends('layouts.company')
@section('title', 'Ficha pública')
@section('page-title', 'Ficha pública')
@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h2 class="text-2xl font-black text-slate-950">{{ $organization->commercial_name ?: $organization->legal_name }}</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Esto es lo que verá el viajero en Empresas: descripción, horario, WhatsApp, embarcaciones y fotos. No depende de un plan publicitario.</p>
            </div>
            @if($organization->status === 'active')
                <a href="{{ route('companies.show', $organization) }}" class="inline-flex min-h-11 items-center rounded-xl border border-emerald-800 px-4 text-sm font-bold text-emerald-900">Ver ficha pública</a>
            @endif
        </div>
    </section>
    @if(session('success'))<p class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('company.profile.update') }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf @method('PATCH')
        <h3 class="text-lg font-black text-slate-950">Información</h3>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700">Nombre comercial<input name="commercial_name" value="{{ old('commercial_name', $organization->commercial_name) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Horario de atención<input name="service_hours" value="{{ old('service_hours', $organization->service_hours) }}" placeholder="Lun–Sáb 08:00–18:00" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">WhatsApp de boletería<input name="whatsapp" value="{{ old('whatsapp', $organization->whatsapp) }}" placeholder="900 000 000" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Teléfono de central<input name="phone" value="{{ old('phone', $organization->phone) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Mensaje inicial de WhatsApp<input name="whatsapp_message" value="{{ old('whatsapp_message', $organization->whatsapp_message) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="flex items-center gap-3 rounded-xl bg-emerald-50 p-3 text-sm text-slate-700 sm:col-span-2"><input type="checkbox" name="show_public_whatsapp" value="1" @checked(old('show_public_whatsapp', $organization->show_public_whatsapp))> Mostrar WhatsApp a los viajeros</label>
            <label class="text-sm font-semibold text-slate-700">Sitio web<input type="url" name="website" value="{{ old('website', $organization->website) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Dirección<input name="address" value="{{ old('address', $organization->address) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        </div>
        <label class="block text-sm font-semibold text-slate-700">Descripción<textarea name="public_description" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm">{{ old('public_description', $organization->public_description) }}</textarea></label>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700">Logo<input type="file" accept="image/*" name="logo" class="mt-1 block w-full text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Imagen de portada<input type="file" accept="image/*" name="cover" class="mt-1 block w-full text-sm"></label>
        </div>
        <p class="text-sm text-slate-500">Las embarcaciones y aeronaves se publican desde Flota y capacidad. Aquí solo completas la presentación.</p>
        <button class="inline-flex min-h-11 items-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Guardar ficha</button>
    </form>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Galería</h3>
        <p class="mt-1 text-sm text-slate-500">Estas fotos se ven en la ficha, junto con la portada y las fotos de la flota. Los viajeros registrados pueden comentarlas desde Empresas.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('company.profile.photos.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            @csrf
            <label class="text-sm font-semibold text-slate-700">Fotos<input type="file" name="photos[]" accept="image/*" multiple required class="mt-1 block w-full text-sm"></label>
            <button class="inline-flex min-h-11 items-center rounded-xl bg-[#062c21] px-5 text-sm font-bold text-white">Subir fotos</button>
        </form>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @forelse($organization->photos as $photo)
                <article class="overflow-hidden rounded-2xl border border-slate-200">
                    <img src="{{ $photo->path }}" alt="{{ $photo->caption ?: 'Foto de la empresa' }}" class="h-32 w-full object-cover">
                    <form method="POST" action="{{ route('company.profile.photos.destroy', $photo) }}" class="p-2">
                        @csrf @method('DELETE')
                        <button class="text-sm font-bold text-rose-700">Quitar</button>
                    </form>
                </article>
            @empty
                <p class="col-span-full text-sm text-slate-500">Todavía no hay fotos en la galería.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
