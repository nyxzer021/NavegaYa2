@extends('layouts.company')
@section('title', 'Página de la empresa')
@section('page-title', 'Página de la empresa')
@section('content')
<style>
    .ficha-menu-preview{display:flex;gap:.35rem;max-width:100%;overflow-x:auto;padding:.4rem;border:2px solid var(--menu-stroke,#fbbf24);border-radius:999px;background:var(--menu-bar,#062c21)}
    .ficha-menu-preview span{display:inline-flex;min-height:2.4rem;align-items:center;white-space:nowrap;border:2px solid transparent;border-radius:999px;padding:.3rem .85rem;font-size:.85rem;font-weight:800}
    .ficha-file{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;margin-top:.55rem}
    .ficha-file input{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
    .ficha-file span{display:inline-flex;min-height:2.75rem;align-items:center;border-radius:999px;background:#062c21;padding:0 1rem;font-size:.85rem;font-weight:800;color:#fff;cursor:pointer}
    .ficha-file em{font-style:normal;font-size:.8rem;font-weight:600;color:#64748b}
</style>
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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-black text-slate-950">Información</h3>
            <button class="inline-flex min-h-11 items-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Guardar ficha</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-sm font-semibold text-slate-700">Nombre comercial<input name="commercial_name" value="{{ old('commercial_name', $organization->commercial_name) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Horario de atención<input name="service_hours" value="{{ old('service_hours', $organization->service_hours) }}" placeholder="Lun–Sáb 08:00–18:00" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">WhatsApp de boletería<input name="whatsapp" value="{{ old('whatsapp', $organization->whatsapp) }}" placeholder="900 000 000" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Teléfono de central<input name="phone" value="{{ old('phone', $organization->phone) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Mensaje inicial de WhatsApp<input name="whatsapp_message" value="{{ old('whatsapp_message', $organization->whatsapp_message) }}" placeholder="Hola, vi su perfil en NavegaYA y quisiera información sobre sus viajes." class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="flex items-center gap-3 rounded-xl bg-emerald-50 p-3 text-sm text-slate-700 sm:col-span-2"><input type="checkbox" name="show_public_whatsapp" value="1" @checked(old('show_public_whatsapp', $organization->show_public_whatsapp))> Mostrar WhatsApp a los viajeros</label>
            <label class="text-sm font-semibold text-slate-700">Sitio web<input type="url" name="website" value="{{ old('website', $organization->website) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Dirección<input name="address" value="{{ old('address', $organization->address) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
        </div>
        <label class="block text-sm font-semibold text-slate-700">Descripción<textarea name="public_description" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm">{{ old('public_description', $organization->public_description) }}</textarea></label>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="text-sm font-semibold text-slate-700">Logo
                @if($organization->logo_path)<img src="{{ $organization->logo_path }}" alt="Logo actual" class="mt-2 h-20 w-20 rounded-xl border border-slate-200 bg-white object-contain p-1">@endif
                <label class="ficha-file"><input type="file" accept="image/jpeg,image/png,image/webp" name="logo"><span>Elegir logo</span><em data-file-name>Ningún archivo elegido</em></label>
                <span class="mt-1 block text-xs font-normal text-slate-500">Se muestra completo, sin recorte. JPG o PNG, hasta 5 MB.</span>
            </div>
            <div class="text-sm font-semibold text-slate-700">Imagen de portada
                @if($organization->cover_image_path)<img src="{{ $organization->cover_image_path }}" alt="Portada actual" class="mt-2 h-20 w-full rounded-xl border border-slate-200 object-cover">@endif
                <label class="ficha-file"><input type="file" accept="image/jpeg,image/png,image/webp" name="cover"><span>Elegir portada</span><em data-file-name>Ningún archivo elegido</em></label>
                <span class="mt-1 block text-xs font-normal text-slate-500">Foto horizontal de la embarcación o el río. Cubre la cabecera.</span>
            </div>
        </div>
        <p class="text-sm text-slate-500">Las embarcaciones y aeronaves se publican desde Flota y capacidad. Aquí solo completas la presentación.</p>

        <section class="space-y-4 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
            <div>
                <h3 class="text-lg font-black text-slate-950">Colores del menú</h3>
                <p class="mt-1 text-sm text-slate-500">Cada pestaña muestra el color con el que se verá activa en la ficha. El menú de arriba del sitio no cambia.</p>
            </div>
            <div id="ficha-menu-preview" class="ficha-menu-preview" aria-hidden="true">
                @foreach($colorFields as $group)
                    @if($group['key'])
                        <span data-preview="{{ $group['key'] }}">{{ $group['title'] }}</span>
                    @endif
                @endforeach
            </div>
            <button type="button" id="restore-brand-colors" class="inline-flex min-h-11 items-center rounded-xl border border-emerald-800 px-4 text-sm font-bold text-emerald-900">Volver a los colores de NavegaYA</button>
            <div class="grid gap-4">
                @foreach($colorFields as $group)
                    <fieldset class="rounded-2xl border border-slate-200 bg-white p-4">
                        <legend class="px-1 text-sm font-black text-slate-950">{{ $group['title'] }}</legend>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            @foreach($group['fields'] as $name => $label)
                                @php
                                    $colorValue = old($name, \App\Support\CompanyPublicPresentation::valueFor($presentation, $name));
                                @endphp
                                <label class="text-sm font-semibold text-slate-700">{{ $label }}
                                    <span class="mt-1 flex items-center gap-2">
                                        <input type="color" data-color-for="{{ $name }}" value="{{ $colorValue }}" class="h-11 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1">
                                        <input data-hex name="{{ $name }}" value="{{ $colorValue }}" maxlength="7" spellcheck="false" class="h-11 w-full rounded-xl border-slate-200 font-mono text-sm">
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <section class="space-y-3 rounded-2xl border border-slate-200 p-4">
            <h3 class="text-lg font-black text-slate-950">Encomiendas</h3>
            <p class="text-sm text-slate-500">Precio de referencia para menos de 25 kg. Desde 25 kg el viajero ve el aviso y el botón Cotizar carga abre el WhatsApp de la empresa.</p>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-sm font-semibold text-slate-700">Pequeño<input type="number" name="cargo_pequeno" min="0" max="9999" step="0.01" value="{{ old('cargo_pequeno', $presentation['cargo']['pequeno']) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
                <label class="text-sm font-semibold text-slate-700">Mediano<input type="number" name="cargo_mediano" min="0" max="9999" step="0.01" value="{{ old('cargo_mediano', $presentation['cargo']['mediano']) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
                <label class="text-sm font-semibold text-slate-700">Grande<input type="number" name="cargo_grande" min="0" max="9999" step="0.01" value="{{ old('cargo_grande', $presentation['cargo']['grande']) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
            </div>
            <label class="block text-sm font-semibold text-slate-700">Aviso desde 25 kg
                <input name="cargo_port_note" maxlength="180" value="{{ old('cargo_port_note', $presentation['cargo']['port_note']) }}" placeholder="Desde 25 kg se cotiza en el puerto de embarque." class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm">
                <span class="mt-1 block text-xs font-normal text-slate-500">Si lo dejas vacío, se usa el nombre del muelle.</span>
            </label>
        </section>

        <button class="inline-flex min-h-11 items-center rounded-xl bg-amber-400 px-5 text-sm font-black text-emerald-950">Guardar ficha</button>
    </form>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Galería</h3>
        <p class="mt-1 text-sm text-slate-500">Estas fotos se ven en la ficha, junto con la portada y las fotos de la flota. Los viajeros registrados pueden comentarlas desde Empresas.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('company.profile.photos.store') }}" class="mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            @csrf
            <label class="ficha-file text-sm font-semibold text-slate-700">Fotos<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required><span>Elegir fotos</span><em data-file-name>Ningún archivo elegido</em></label>
            <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#062c21] px-5 text-sm font-bold text-white">Subir fotos</button>
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
<script>
    (function () {
        document.querySelectorAll('.ficha-file input').forEach(function (input) {
            input.addEventListener('change', function () {
                var label = input.parentElement.querySelector('[data-file-name]');
                if (!label) return;
                var names = Array.prototype.map.call(input.files || [], function (file) { return file.name; });
                label.textContent = names.length ? names.join(', ') : 'Ningún archivo elegido';
            });
        });
        var preview = document.getElementById('ficha-menu-preview');
        var restore = document.getElementById('restore-brand-colors');
        if (!preview || !restore) return;
        var brand = @json($brandColors);
        function field(name) {
            return document.querySelector('[data-hex][name="' + name + '"]');
        }
        function read(name) {
            var input = field(name);
            var value = input ? input.value.trim() : '';
            return /^#[0-9A-Fa-f]{6}$/.test(value) ? value : brand[name];
        }
        function paintPreview() {
            preview.style.setProperty('--menu-bar', read('menu_bar'));
            preview.style.setProperty('--menu-stroke', read('menu_stroke'));
            preview.querySelectorAll('[data-preview]').forEach(function (tab) {
                var key = tab.getAttribute('data-preview');
                tab.style.background = read('tab_' + key + '_bg');
                tab.style.borderColor = read('tab_' + key + '_border');
                tab.style.color = read('tab_' + key + '_text');
            });
        }
        function write(name, value) {
            var input = field(name);
            var picker = document.querySelector('[data-color-for="' + name + '"]');
            if (input) input.value = value;
            if (picker) picker.value = value;
        }
        document.querySelectorAll('[data-color-for]').forEach(function (picker) {
            picker.addEventListener('input', function () {
                write(picker.getAttribute('data-color-for'), picker.value);
                paintPreview();
            });
        });
        document.querySelectorAll('[data-hex]').forEach(function (input) {
            input.addEventListener('input', function () {
                var picker = document.querySelector('[data-color-for="' + input.name + '"]');
                if (picker && /^#[0-9A-Fa-f]{6}$/.test(input.value.trim())) picker.value = input.value.trim();
                paintPreview();
            });
        });
        restore.addEventListener('click', function () {
            Object.keys(brand).forEach(function (name) { write(name, brand[name]); });
            paintPreview();
        });
        paintPreview();
    }());
</script>
@endsection
