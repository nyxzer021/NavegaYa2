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
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Esto es lo que verá el viajero en Empresas: nombre, descripción, horario y logo. El WhatsApp, los colores y las fotos se editan en su propia pantalla.</p>
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
            <p class="rounded-xl bg-emerald-50 p-3 text-sm text-slate-700 sm:col-span-2"><a href="{{ route('company.whatsapp.index') }}" class="font-bold text-emerald-900">{{ trans('company_portal.ficha.whatsapp_elsewhere', [], 'es') }}</a></p>
            <label class="text-sm font-semibold text-slate-700">Teléfono de central<input name="phone" value="{{ old('phone', $organization->phone) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm"></label>
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
            <p class="self-end text-sm font-semibold text-emerald-900"><a href="{{ route('company.gallery.index') }}">{{ trans('company_portal.ficha.gallery_elsewhere', [], 'es') }}</a></p>
        </div>
        <p class="text-sm text-slate-500">Las embarcaciones y aeronaves se publican desde Flota y capacidad. Aquí solo completas la presentación.</p>

        <section class="space-y-3 rounded-2xl border border-slate-200 p-4">
            <h3 class="text-lg font-black text-slate-950">{{ trans('company_portal.ficha.cargo_title', [], 'es') }}</h3>
            <p class="text-sm text-slate-500">{{ trans('company_portal.ficha.cargo_hint', [], 'es') }}</p>
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
    }());
</script>
@endsection
