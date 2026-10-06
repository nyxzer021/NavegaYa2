<x-public-layout title="Afiliación de empresas · NavegaYA">
<div class="min-h-screen bg-slate-50 px-4 py-10 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl">
@if(session('status'))<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{session('status')}}</div>@endif
<div class="grid items-start gap-10 lg:grid-cols-3 lg:gap-12">
<section class="space-y-8 pt-2">
<div><span class="mb-4 inline-flex rounded-full border border-emerald-200 bg-emerald-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800">💼 Red de operadores oficiales</span><h1 class="text-3xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-4xl">Digitaliza la venta de pasajes de tu flota en Loreto</h1><p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base">Únete a la plataforma de transporte fluvial y aéreo de la Amazonía. Vende las 24 horas y organiza cada salida desde un solo panel.</p></div>
@php($benefits=[['💳','Ventas 24/7 sin colas','Tus pasajeros consultan horarios y compran desde cualquier lugar.'],['📋','Manifiestos de pasajeros','Organiza los datos de cada viajero para el control de salida.'],['📴','Control de abordaje flexible','Valida por DNI, código de boleto o QR desde el módulo de embarque.'],['🛡️','Reportes claros','Consulta ventas, reservas y ocupación de cada salida.']])
<div class="space-y-4">@foreach($benefits as [$icon,$heading,$copy])<article class="flex items-start gap-3.5 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-lg">{{$icon}}</div><div><h2 class="text-sm font-bold text-slate-900">{{$heading}}</h2><p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{$copy}}</p></div></article>@endforeach</div>
<div class="flex items-center gap-3 rounded-2xl bg-emerald-950 p-4 text-white"><span class="text-2xl">⚓</span><p class="text-xs"><strong class="block text-emerald-300">Validación formal y legal</strong><span class="text-emerald-100/70">Incorporamos organizaciones con RUC activo y documentación vigente.</span></p></div>
</section>
<section class="lg:col-span-2"><div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xl sm:p-9">
<header class="mb-6 border-b border-slate-100 pb-5"><span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Formulario de afiliación</span><h2 class="mt-1 text-xl font-extrabold text-slate-900 sm:text-2xl">Registra tu empresa de transporte</h2><p class="mt-1 text-xs text-slate-500">Un asesor validará los datos legales antes de activar el panel administrativo.</p></header>
@if($errors->any())<div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800"><strong class="block">Corrige los siguientes campos:</strong><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
<form action="{{route('company.register.store')}}" method="POST" class="space-y-6">@csrf
<fieldset class="space-y-3.5"><legend class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">1. Información de la empresa</legend>
<div class="grid gap-3.5 sm:grid-cols-2">
<label class="rounded-2xl border border-slate-200 bg-slate-50 p-2.5 focus-within:ring-2 focus-within:ring-emerald-600"><span class="block text-[10px] font-bold uppercase text-slate-400">Tipo de organización</span><select name="type" required class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-bold text-slate-800 focus:ring-0"><option value="transport_company" @selected(old('type')==='transport_company')>🚤 Empresa de transporte</option><option value="agency" @selected(old('type')==='agency')>🏢 Agencia de ventas</option></select></label>
<label class="rounded-2xl border border-slate-200 bg-slate-50 p-2.5 focus-within:ring-2 focus-within:ring-emerald-600"><span class="block text-[10px] font-bold uppercase text-slate-400">RUC (11 dígitos)</span><input name="ruc" required inputmode="numeric" maxlength="11" value="{{old('ruc')}}" placeholder="20XXXXXXXXX" class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-bold focus:ring-0"></label></div>
<div class="grid gap-3.5 sm:grid-cols-2"><x-company-field name="legal_name" label="Razón social" placeholder="Transportes del Oriente S.A.C." required/><x-company-field name="commercial_name" label="Nombre comercial" placeholder="Amazonía Express"/></div>
<div class="grid gap-3.5 sm:grid-cols-2">
<label class="rounded-2xl border border-slate-200 bg-slate-50 p-2.5 focus-within:ring-2 focus-within:ring-emerald-600"><span class="block text-[10px] font-bold uppercase text-slate-400">Modalidad</span><select name="modality" required class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-bold text-slate-800 focus:ring-0"><option value="fluvial" @selected(old('modality')==='fluvial')>🚤 Fluvial</option><option value="aereo" @selected(old('modality')==='aereo')>✈️ Aérea</option><option value="mixto" @selected(old('modality')==='mixto')>🚤 + ✈️ Mixta</option></select></label>
<x-company-field name="base_city" label="Ciudad base" placeholder="Iquitos" required/>
</div>
<x-company-field name="address" label="Dirección fiscal / base de operaciones" placeholder="Dirección, puerto o aeropuerto principal" required/>
</fieldset>
<fieldset class="space-y-3.5 border-t border-slate-100 pt-5"><legend class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">2. Representante o administrador</legend>
<div class="grid gap-3.5 sm:grid-cols-2"><x-company-field name="contact_name" label="Nombre del responsable" placeholder="Nombres y apellidos" required/><x-company-field name="phone" label="Teléfono" placeholder="900 000 000" required/></div>
<div class="grid gap-3.5 sm:grid-cols-2"><x-company-field name="email" label="Correo corporativo" placeholder="administracion@empresa.pe" type="email" required/><x-company-field name="whatsapp" label="WhatsApp" placeholder="900 000 000"/></div>
<div class="grid gap-3.5 sm:grid-cols-2"><x-company-field name="admin_password" label="Contraseña del administrador" placeholder="Mínimo 8 caracteres" type="password" required/><x-company-field name="admin_password_confirmation" label="Confirmar contraseña" placeholder="Repite la contraseña" type="password" required/></div>
<x-company-field name="website" label="Sitio web (opcional)" placeholder="https://" type="url"/>
</fieldset>
<div class="pt-1"><button class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-amber-500 text-sm font-extrabold text-slate-950 shadow-md transition hover:bg-amber-600 active:scale-[.99]">🚀 Enviar solicitud de afiliación</button><p class="mt-3 text-center text-[11px] text-slate-400">Recibirás un correo para confirmar el contacto registrado.</p></div>
</form></div></section>
</div></div></div>
</x-public-layout>
