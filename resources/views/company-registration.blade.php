<x-public-layout title="Afiliación de empresas · NavegaYA">
<div class="bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
<div class="mx-auto max-w-3xl">
@if(session('status'))<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
<p class="text-[11px] font-bold uppercase tracking-[.18em] text-emerald-700">Operadores de Loreto</p>
<h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Afiliar tu empresa</h1>
<p class="mt-2 max-w-xl text-sm leading-6 text-slate-600">Solo para empresas que publican rutas y venden pasajes. Revisamos los documentos y, cuando quede aprobado, creas la contraseña del panel.</p>
<ol class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
<li class="rounded-full bg-emerald-800 px-3 py-1 text-white">1. Solicitud</li>
<li class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">2. Correo</li>
<li class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">3. Revisión</li>
<li class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">4. Contraseña</li>
</ol>

<section id="formulario-transportista" class="mt-6 scroll-mt-24 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
<header class="border-b border-slate-100 px-5 py-4 sm:px-6"><h2 class="text-lg font-black text-slate-950">Datos del transportista</h2><p class="mt-1 text-sm text-slate-500">Para empresas que publican rutas y venden pasajes.</p></header>
@if($errors->any())<div class="mx-5 mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 sm:mx-6"><strong>Revisa la información ingresada.</strong><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('company.registration.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 px-5 py-5 sm:px-6">
@csrf
<fieldset>
<legend class="mb-3 text-sm font-black text-slate-900">Empresa</legend>
<div class="grid gap-3 sm:grid-cols-2">
<x-company-field name="ruc" label="RUC (11 dígitos)" placeholder="20XXXXXXXXX" required />
<x-company-field name="legal_name" label="Razón social" placeholder="Transportes del Oriente S.A.C." required />
<x-company-field name="commercial_name" label="Nombre comercial" placeholder="Amazonía Express" />
<x-company-field name="base_city" label="Ciudad base" placeholder="Iquitos" required />
<x-company-field name="address" label="Dirección fiscal" placeholder="Dirección principal" required />
</div>
<div class="mt-3">
<span class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Tipo de operación</span>
<div class="grid grid-cols-3 gap-2">
@foreach(['fluvial' => 'Fluvial', 'aereo' => 'Aéreo', 'mixto' => 'Ambos'] as $value => $label)
<label class="flex h-11 cursor-pointer items-center justify-center rounded-xl border text-sm font-bold has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-900 {{ old('modality', 'fluvial') === $value ? 'border-emerald-700 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-700' }}">
<input type="radio" name="modality" value="{{ $value }}" class="sr-only" @checked(old('modality', 'fluvial') === $value) required>{{ $label }}
</label>
@endforeach
</div>
</div>
</fieldset>
<fieldset class="border-t border-slate-100 pt-5">
<legend class="mb-3 text-sm font-black text-slate-900">Responsable</legend>
<div class="grid gap-3 sm:grid-cols-2">
<x-company-field name="contact_name" label="Nombres y apellidos" placeholder="Responsable legal" required />
<x-company-field name="email" label="Correo corporativo" placeholder="administracion@empresa.pe" type="email" required />
<x-company-field name="phone" label="Teléfono" placeholder="900 000 000" required />
<x-company-field name="whatsapp" label="WhatsApp" placeholder="900 000 000" />
</div>
<details class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2" @if(old('website')) open @endif>
<summary class="cursor-pointer text-sm font-semibold text-emerald-800">Agregar sitio web</summary>
<div class="pt-2"><x-company-field name="website" label="Sitio web" placeholder="https://" type="url" /></div>
</details>
</fieldset>
<fieldset class="border-t border-slate-100 pt-5">
<legend class="text-sm font-black text-slate-900">Documentos</legend>
<p class="mb-3 mt-1 text-xs text-slate-500">PDF, JPG o PNG. Máximo 5 MB por archivo.</p>
<div class="grid gap-2">
@foreach([['ruc_document', 'Ficha RUC'], ['representative_document', 'Documento del representante'], ['operating_permit', 'Permiso de operación']] as [$name, $label])
<label class="flex flex-col gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 sm:flex-row sm:items-center sm:justify-between"><span>{{ $label }}</span><input type="file" name="{{ $name }}" accept=".pdf,.jpg,.jpeg,.png" required class="block text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-100 file:px-3 file:py-1.5 file:font-bold file:text-emerald-900"></label>
@endforeach
</div>
</fieldset>
<div class="border-t border-slate-100 pt-5">
<label class="flex items-start gap-3 text-sm text-slate-600"><input type="checkbox" name="terms" value="1" required class="mt-0.5 h-[18px] w-[18px] shrink-0"><span>Declaro que la información es correcta y acepto los <a class="font-bold text-emerald-700 underline" href="{{ route('pages.show', 'terminos') }}" onclick="const legalDialog=document.getElementById('legal-terminos');if(legalDialog){event.preventDefault();legalDialog.showModal()}">términos y condiciones</a> y la <a class="font-bold text-emerald-700 underline" href="{{ route('pages.show', 'privacidad') }}" onclick="const legalDialog=document.getElementById('legal-privacidad');if(legalDialog){event.preventDefault();legalDialog.showModal()}">política de privacidad</a>.</span></label>
<button class="mt-4 flex h-12 w-full items-center justify-center rounded-2xl bg-amber-500 text-sm font-black text-slate-950 transition hover:bg-amber-400">Enviar solicitud</button>
<p class="mt-3 text-center text-xs text-slate-500">La contraseña se crea cuando la solicitud queda aprobada.</p>
</div>
</form>
</section>
</div>
</div>
</x-public-layout>
