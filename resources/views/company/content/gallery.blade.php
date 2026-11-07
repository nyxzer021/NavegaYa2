@extends('layouts.company')
@section('title', 'Galería y opiniones')
@section('content')
<div class="p-4 sm:p-6">
    <p class="text-sm text-slate-600">El logo y las portadas los cambia solo el administrador de la empresa. Máximo dos portadas, en WebP de 1920×480.</p>
    @if(session('success'))<p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('company.profile.banners.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2">
        @csrf
        <label class="text-sm font-semibold">Portada<input type="file" name="banner" accept="image/webp,image/jpeg,image/png" required class="mt-1 block w-full"></label>
        <label class="text-sm font-semibold">Punto focal horizontal<input type="number" name="focal_x" min="0" max="100" value="50" class="mt-1 w-full rounded-lg border-slate-200"></label>
        <label class="text-sm font-semibold">Punto focal vertical<input type="number" name="focal_y" min="0" max="100" value="50" class="mt-1 w-full rounded-lg border-slate-200"></label>
        <button class="h-11 rounded-xl bg-[#064E3B] px-4 text-sm font-bold text-white">Publicar portada</button>
    </form>
    <ul class="mt-4 space-y-2">
        @foreach($organization->banners as $banner)
            <li class="flex items-center justify-between rounded-xl border bg-white p-3 text-sm">
                <span>Portada {{ $banner->position }} · foco {{ $banner->focal_x }}, {{ $banner->focal_y }}</span>
                <form method="POST" action="{{ route('company.profile.banners.destroy', $banner) }}">@csrf @method('DELETE')<button class="font-bold text-rose-700">Quitar</button></form>
            </li>
        @endforeach
    </ul>
    <form method="POST" action="{{ route('company.gallery.albums.store') }}" class="mt-6 flex flex-wrap gap-2">
        @csrf
        <input name="title" required maxlength="80" placeholder="Nombre del álbum" class="flex-1 rounded-lg border-slate-200">
        <button class="rounded-xl bg-[#064E3B] px-4 text-sm font-bold text-white">Crear álbum</button>
    </form>
    <ul class="mt-3 text-sm">@foreach($organization->albums as $album)<li>{{ $album->title }}</li>@endforeach</ul>
    <p class="mt-6 text-sm">Las fotos nuevas quedan pendientes hasta que NavegaYA las publique. El logo se cambia en la ficha pública.</p>
</div>
@endsection
