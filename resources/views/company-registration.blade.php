<x-public-layout title="Afiliación de empresas · NavegaYA">
<div class="ny-form-page">
<div class="mx-auto max-w-2xl">
@if(session('status'))<div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
<h1 class="ny-title text-2xl font-bold tracking-tight text-slate-950">Afiliar tu empresa</h1>
<p class="mt-1 text-sm text-slate-600">Solo para empresas que publican rutas y venden pasajes. La contraseña se crea al aprobar la solicitud.</p>

<section id="formulario-transportista" class="ny-form-card mt-3 scroll-mt-24">
@if($errors->any())<div class="mx-4 mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 sm:mx-5"><strong>Revisa la información ingresada.</strong><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('company.registration.store') }}" method="POST" enctype="multipart/form-data" class="space-y-1 px-4 py-4 sm:px-5">
@csrf
<fieldset class="ny-form-section">
<p class="ny-form-kicker">Empresa</p>
<div class="grid gap-3 sm:grid-cols-2">
<x-company-field name="ruc" label="RUC (11 dígitos)" placeholder="20XXXXXXXXX" required />
<x-company-field name="legal_name" label="Razón social" placeholder="Transportes del Oriente S.A.C." required />
<x-company-field name="commercial_name" label="Nombre comercial" placeholder="Amazonía Express" />
<x-company-field name="base_city" label="Ciudad base" placeholder="Iquitos" required />
<x-company-field name="address" label="Dirección fiscal" placeholder="Dirección principal" class="sm:col-span-2" required />
</div>
<div class="mt-3" data-registration-modalities>
<span class="mb-1 block text-sm font-semibold text-slate-700">Modalidades</span>
<p class="mb-2 text-sm text-slate-500">Marca las que vas a operar. Cada una queda solicitada hasta que NavegaYA la apruebe.</p>
<div class="grid gap-2">
@foreach(['fluvial' => 'Fluvial', 'aereo' => 'Aéreo', 'aereo_fluvial' => 'Aéreo fluvial'] as $value => $label)
<label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="modalities[]" value="{{ $value }}" @checked(in_array($value, old('modalities', []), true))> {{ $label }}</label>
@endforeach
</div>
<div class="mt-3 grid gap-3" data-mod="fluvial">
<p class="text-sm font-semibold text-slate-700">Permiso fluvial</p>
<label class="text-sm">Alcance<select name="fluvial_permit_scope" class="mt-1 w-full rounded-xl border-slate-200"><option value="regional">Regional</option><option value="national">Nacional</option></select></label>
<x-company-field name="fluvial_permit_number" label="Número de permiso" />
<x-company-field name="fluvial_permit_expires_on" label="Vencimiento" type="date" />
<label class="ny-file"><span>Permiso de operación</span><input type="file" name="fluvial_permit" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" data-ny-file><strong data-ny-file-label>Elegir archivo</strong></label>
</div>
@foreach(['aereo' => 'Aéreo', 'aereo_fluvial' => 'Aéreo fluvial'] as $value => $label)
<div class="mt-3 grid gap-3" data-mod="{{ $value }}">
<p class="text-sm font-semibold text-slate-700">Permiso {{ $label }}</p>
<x-company-field :name="$value.'_permit_number'" label="Número MTC/DGAC" />
<x-company-field :name="$value.'_permit_expires_on'" label="Vencimiento del permiso" type="date" />
<label class="ny-file"><span>Permiso MTC/DGAC</span><input type="file" name="{{ $value }}_permit" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" data-ny-file><strong data-ny-file-label>Elegir archivo</strong></label>
<x-company-field :name="$value.'_aoc_number'" label="Número de AOC" />
<x-company-field :name="$value.'_aoc_expires_on'" label="Vencimiento del AOC" type="date" />
<label class="ny-file"><span>AOC</span><input type="file" name="{{ $value }}_aoc" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" data-ny-file><strong data-ny-file-label>Elegir archivo</strong></label>
</div>
@endforeach
</div>
</fieldset>
<fieldset class="ny-form-section">
<p class="ny-form-kicker">Responsable</p>
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
<p class="ny-form-kicker">Documentos <span class="font-medium normal-case tracking-normal text-slate-500">· PDF, JPG o PNG, máximo 5 MB</span></p>
<div class="grid gap-2">
@foreach([['ruc_document', 'Ficha RUC'], ['representative_document', 'Documento del representante']] as [$name, $label])
<label class="ny-file"><span>{{ $label }}</span><input type="file" name="{{ $name }}" accept=".pdf,.jpg,.jpeg,.png" required class="sr-only" data-ny-file><strong data-ny-file-label>Elegir archivo</strong></label>
@endforeach
</div>
</fieldset>
<div class="mt-3">
<label class="flex items-start gap-3 text-sm text-slate-600"><input type="checkbox" name="terms" value="1" required class="mt-0.5 h-[18px] w-[18px] shrink-0"><span>Declaro que la información es correcta y acepto los <a class="font-bold text-emerald-700 underline" href="{{ route('pages.show', 'terminos') }}" onclick="const legalDialog=document.getElementById('legal-terminos');if(legalDialog){event.preventDefault();legalDialog.showModal()}">términos y condiciones</a> y la <a class="font-bold text-emerald-700 underline" href="{{ route('pages.show', 'privacidad') }}" onclick="const legalDialog=document.getElementById('legal-privacidad');if(legalDialog){event.preventDefault();legalDialog.showModal()}">política de privacidad</a>.</span></label>
<button class="ny-send mt-3">Enviar solicitud</button>
</div>
</form>
</section>
</div>
</div>
<script>
document.querySelectorAll('[data-ny-file]').forEach(input=>input.addEventListener('change',()=>{const name=input.files&&input.files[0]?input.files[0].name:'Elegir archivo';input.parentElement.querySelector('[data-ny-file-label]').textContent=name}));
(function(){const root=document.querySelector('[data-registration-modalities]');if(!root)return;function paint(){root.querySelectorAll('[data-mod]').forEach(block=>{const box=root.querySelector('input[value="'+block.getAttribute('data-mod')+'"]');block.hidden=!(box&&box.checked)})}root.addEventListener('change',paint);paint()})();
</script>
</x-public-layout>
