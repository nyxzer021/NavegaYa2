<x-public-layout title="Afiliación de empresas · NavegaYA">
<div class="ny-form-page">
<div class="mx-auto max-w-3xl">
@if(session('status'))<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
<p class="text-[11px] font-bold uppercase tracking-[.18em] text-emerald-700">Operadores de Loreto</p>
<h1 class="ny-title mt-2 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Afiliar tu empresa</h1>
<p class="mt-2 max-w-xl text-sm leading-6 text-slate-600">Solo para empresas que publican rutas y venden pasajes. Revisamos los documentos y, cuando quede aprobado, creas la contraseña del panel.</p>
<ol class="ny-steps">
<li class="is-now"><b>1</b> Solicitud</li>
<li><b>2</b> Correo</li>
<li><b>3</b> Revisión</li>
<li><b>4</b> Contraseña</li>
</ol>

<section id="formulario-transportista" class="ny-form-card mt-6 scroll-mt-24">
<header class="px-5 py-5 sm:px-7"><h2 class="text-lg font-black text-slate-950">Datos del transportista</h2><p class="mt-1 text-sm text-slate-500">Completa el expediente. La revisión empieza cuando envías la solicitud.</p></header>
@if($errors->any())<div class="mx-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 sm:mx-7"><strong>Revisa la información ingresada.</strong><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('company.registration.store') }}" method="POST" enctype="multipart/form-data" class="space-y-1 px-5 pb-6 sm:px-7">
@csrf
<fieldset class="ny-form-section">
<p class="ny-form-kicker"><i>1</i> Empresa</p>
<div class="grid gap-3 sm:grid-cols-2">
<x-company-field name="ruc" label="RUC (11 dígitos)" placeholder="20XXXXXXXXX" required />
<x-company-field name="legal_name" label="Razón social" placeholder="Transportes del Oriente S.A.C." required />
<x-company-field name="commercial_name" label="Nombre comercial" placeholder="Amazonía Express" />
<x-company-field name="base_city" label="Ciudad base" placeholder="Iquitos" required />
<x-company-field name="address" label="Dirección fiscal" placeholder="Dirección principal" class="sm:col-span-2" required />
</div>
<div class="mt-3">
<span class="mb-2 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Tipo de operación</span>
<div class="grid grid-cols-3 gap-2">
@foreach(['fluvial' => 'Fluvial', 'aereo' => 'Aéreo', 'mixto' => 'Ambos'] as $value => $label)
<label class="ny-choice">
<input type="radio" name="modality" value="{{ $value }}" class="sr-only" @checked(old('modality', 'fluvial') === $value) required>{{ $label }}
</label>
@endforeach
</div>
</div>
</fieldset>
<fieldset class="ny-form-section">
<p class="ny-form-kicker"><i>2</i> Responsable</p>
<div class="grid gap-3 sm:grid-cols-2">
<x-company-field name="contact_name" label="Nombres y apellidos" placeholder="Responsable legal" required />
<x-company-field name="email" label="Correo corporativo" placeholder="administracion@empresa.pe" type="email" required />
<x-company-field name="phone" label="Teléfono" placeholder="900 000 000" required />
<x-company-field name="whatsapp" label="WhatsApp" placeholder="900 000 000" />
</div>
<details class="mt-3 rounded-2xl border border-emerald-100 bg-white px-3 py-2" @if(old('website')) open @endif>
<summary class="cursor-pointer text-sm font-semibold text-emerald-800">Agregar sitio web</summary>
<div class="pt-2"><x-company-field name="website" label="Sitio web" placeholder="https://" type="url" /></div>
</details>
</fieldset>
<fieldset class="ny-form-section">
<p class="ny-form-kicker"><i>3</i> Documentos</p>
<p class="mb-3 -mt-1 text-xs text-slate-500">PDF, JPG o PNG. Máximo 5 MB por archivo.</p>
<div class="grid gap-2">
@foreach([['ruc_document', 'Ficha RUC'], ['representative_document', 'Documento del representante'], ['operating_permit', 'Permiso de operación']] as [$name, $label])
<label class="ny-file"><span>{{ $label }}</span><input type="file" name="{{ $name }}" accept=".pdf,.jpg,.jpeg,.png" required class="sr-only" data-ny-file><strong data-ny-file-label>Elegir archivo</strong></label>
@endforeach
</div>
</fieldset>
<div class="mt-5 rounded-2xl border border-amber-100 bg-amber-50/70 p-4">
<label class="flex items-start gap-3 text-sm text-slate-600"><input type="checkbox" name="terms" value="1" required class="mt-0.5 h-[18px] w-[18px] shrink-0"><span>Declaro que la información es correcta y acepto los <a class="font-bold text-emerald-700 underline" href="{{ route('pages.show', 'terminos') }}" onclick="const legalDialog=document.getElementById('legal-terminos');if(legalDialog){event.preventDefault();legalDialog.showModal()}">términos y condiciones</a> y la <a class="font-bold text-emerald-700 underline" href="{{ route('pages.show', 'privacidad') }}" onclick="const legalDialog=document.getElementById('legal-privacidad');if(legalDialog){event.preventDefault();legalDialog.showModal()}">política de privacidad</a>.</span></label>
<button class="ny-send mt-4">Enviar solicitud</button>
<p class="mt-3 text-center text-xs text-slate-500">La contraseña se crea cuando la solicitud queda aprobada.</p>
</div>
</form>
</section>
</div>
</div>
<script>document.querySelectorAll('[data-ny-file]').forEach(input=>input.addEventListener('change',()=>{const name=input.files&&input.files[0]?input.files[0].name:'Elegir archivo';input.parentElement.querySelector('[data-ny-file-label]').textContent=name}))</script>
</x-public-layout>
