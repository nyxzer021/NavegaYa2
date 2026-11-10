@extends('layouts.company')
@section('title', 'Galería y opiniones')
@section('content')
<style>
    .focal-stage{position:relative;overflow:hidden;border-radius:12px;background:#062C21;cursor:crosshair}
    .focal-stage img{display:block;width:100%;height:180px;object-fit:contain}
    .focal-marker{position:absolute;width:18px;height:18px;border:3px solid #fff;border-radius:999px;background:#F4B820;box-shadow:0 0 0 2px #064E3B;transform:translate(-50%,-50%);pointer-events:none}
    .focal-desktop{aspect-ratio:2.4/1;overflow:hidden;border-radius:12px;background:#062C21}
    .focal-desktop img,.focal-mobile img{width:100%;height:100%;object-fit:cover}
    .focal-mobile{width:120px;aspect-ratio:3/4;overflow:hidden;border-radius:12px;background:#062C21}
</style>
<div class="p-4 sm:p-6">
    <p class="text-sm text-slate-600">El logo y las portadas los cambia solo el administrador de la empresa. Máximo dos portadas, en WebP de 1920×480.</p>
    @if(session('success'))<p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('company.profile.banners.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-3 rounded-xl border bg-white p-4" data-focal-form>
        @csrf
        <label class="text-sm font-semibold">Portada<input type="file" name="banner" accept="image/webp,image/jpeg,image/png" required class="mt-1 block w-full" data-focal-file></label>
        <p class="text-sm text-slate-600">{{ trans('company_portal.focus.hint', [], 'es') }}</p>
        <div class="focal-stage" data-focal-stage hidden>
            <img alt="Vista previa de la portada" data-focal-source>
            <span class="focal-marker" data-focal-marker style="left:50%;top:50%"></span>
        </div>
        <input type="hidden" name="focal_x" value="50" data-focal-x>
        <input type="hidden" name="focal_y" value="50" data-focal-y>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <p class="mb-1 text-xs font-bold">{{ trans('company_portal.focus.desktop', [], 'es') }}</p>
                <div class="focal-desktop w-64"><img alt="" data-focal-wide></div>
            </div>
            <div>
                <p class="mb-1 text-xs font-bold">{{ trans('company_portal.focus.mobile', [], 'es') }}</p>
                <div class="focal-mobile"><img alt="" data-focal-tall></div>
            </div>
        </div>
        <button class="h-11 w-fit rounded-xl bg-[#064E3B] px-4 text-sm font-bold text-white">Publicar portada</button>
    </form>
    <ul class="mt-4 space-y-3">
        @foreach($organization->banners as $banner)
            <li class="rounded-xl border bg-white p-3 text-sm">
                <div class="mb-2 flex items-center justify-between">
                    <span>Portada {{ $banner->position }}</span>
                    <form method="POST" action="{{ route('company.profile.banners.destroy', $banner) }}">@csrf @method('DELETE')<button class="font-bold text-rose-700">Quitar</button></form>
                </div>
                <form method="POST" action="{{ route('company.profile.banners.focus', $banner) }}" data-focal-form>
                    @csrf @method('PATCH')
                    <div class="focal-stage" data-focal-stage>
                        <img src="{{ $banner->path }}" alt="Portada {{ $banner->position }}" data-focal-source>
                        <span class="focal-marker" data-focal-marker style="left:{{ $banner->focal_x ?? 50 }}%;top:{{ $banner->focal_y ?? 50 }}%"></span>
                    </div>
                    <input type="hidden" name="focal_x" value="{{ $banner->focal_x ?? 50 }}" data-focal-x>
                    <input type="hidden" name="focal_y" value="{{ $banner->focal_y ?? 50 }}" data-focal-y>
                    <div class="mt-3 flex flex-wrap items-end gap-4">
                        <div>
                            <p class="mb-1 text-xs font-bold">{{ trans('company_portal.focus.desktop', [], 'es') }}</p>
                            <div class="focal-desktop w-64"><img src="{{ $banner->path }}" alt="" data-focal-wide style="object-position:{{ $banner->focal_x ?? 50 }}% {{ $banner->focal_y ?? 50 }}%"></div>
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-bold">{{ trans('company_portal.focus.mobile', [], 'es') }}</p>
                            <div class="focal-mobile"><img src="{{ $banner->path }}" alt="" data-focal-tall style="object-position:{{ $banner->focal_x ?? 50 }}% {{ $banner->focal_y ?? 50 }}%"></div>
                        </div>
                    </div>
                    <button class="mt-3 font-bold text-emerald-800">{{ trans('company_portal.focus.save', [], 'es') }}</button>
                </form>
            </li>
        @endforeach
    </ul>
<script>
    (function () {
        function place(form, x, y) {
            form.querySelector('[data-focal-x]').value = x.toFixed(2);
            form.querySelector('[data-focal-y]').value = y.toFixed(2);
            var marker = form.querySelector('[data-focal-marker]');
            marker.style.left = x + '%';
            marker.style.top = y + '%';
            form.querySelectorAll('[data-focal-wide], [data-focal-tall]').forEach(function (image) {
                image.style.objectPosition = x + '% ' + y + '%';
            });
        }
        document.querySelectorAll('[data-focal-form]').forEach(function (form) {
            var stage = form.querySelector('[data-focal-stage]');
            stage.addEventListener('click', function (event) {
                var rect = stage.getBoundingClientRect();
                var x = Math.min(100, Math.max(0, ((event.clientX - rect.left) / rect.width) * 100));
                var y = Math.min(100, Math.max(0, ((event.clientY - rect.top) / rect.height) * 100));
                place(form, x, y);
            });
            var file = form.querySelector('[data-focal-file]');
            if (!file) return;
            file.addEventListener('change', function () {
                var chosen = file.files && file.files[0];
                if (!chosen) return;
                var url = URL.createObjectURL(chosen);
                form.querySelectorAll('[data-focal-source], [data-focal-wide], [data-focal-tall]').forEach(function (image) { image.src = url; });
                stage.hidden = false;
                place(form, 50, 50);
            });
        });
    }());
</script>
    <form method="POST" action="{{ route('company.gallery.albums.store') }}" class="mt-6 flex flex-wrap gap-2">
        @csrf
        <input name="title" required maxlength="80" placeholder="Nombre del álbum" class="flex-1 rounded-lg border-slate-200">
        <button class="rounded-xl bg-[#064E3B] px-4 text-sm font-bold text-white">Crear álbum</button>
    </form>
    <ul class="mt-3 text-sm">@foreach($organization->albums as $album)<li>{{ $album->title }}</li>@endforeach</ul>
    <p class="mt-6 text-sm">Las fotos nuevas quedan pendientes hasta que NavegaYA las publique. En la página pública, el menú de cada foto publicada permite ponerla de portada o de logo.</p>
    <a href="{{ route('companies.show', $organization) }}#galeria" class="mt-3 inline-flex text-sm font-bold text-emerald-800">Ver la página pública</a>
    <ul class="mt-4 space-y-3">
        @foreach($organization->photos as $photo)
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-3 text-sm">
                <span>{{ $photo->caption ?: 'Foto '.$photo->id }} · {{ $photo->status === 'published' ? 'Publicada' : 'Pendiente' }}</span>
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-lg border px-2 py-1 font-bold" aria-label="Acciones de la foto">⋯</summary>
                    <form method="POST" action="{{ route('company.gallery.photos.caption', $photo) }}" class="absolute right-0 z-10 mt-1 w-64 rounded-xl border bg-white p-3 shadow">
                        @csrf
                        @method('PATCH')
                        <label class="block text-xs font-semibold">Editar título
                            <input name="caption" maxlength="120" value="{{ $photo->caption }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        </label>
                        <button class="mt-2 font-bold text-emerald-800">Guardar título</button>
                    </form>
                </details>
                @if($photo->status === 'published')
                    <span class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('company.gallery.photos.cover', $photo) }}">
                            @csrf
                            @if($organization->banners->count() >= 2)
                                @foreach($organization->banners as $banner)
                                    <label class="mr-2 text-xs"><input type="radio" name="replace_id" value="{{ $banner->id }}" required> Reemplazar portada {{ $banner->position }}</label>
                                @endforeach
                            @endif
                            <button class="font-bold text-emerald-800">Establecer como portada</button>
                        </form>
                        <form method="POST" action="{{ route('company.gallery.photos.logo', $photo) }}">@csrf<button class="font-bold text-emerald-800">Establecer como logo/perfil</button></form>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endsection
