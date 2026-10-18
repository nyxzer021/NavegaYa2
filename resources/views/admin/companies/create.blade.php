<x-app-layout>
<x-slot name="header"><h1 class="ny-page-title">Registrar empresa transportista</h1></x-slot>
<div class="mx-auto max-w-6xl">
 <a href="{{ route('admin.companies.index', ['tab' => 'rendimiento']) }}" class="text-xs font-bold text-emerald-700">← Volver a empresas afiliadas</a>
 <form method="POST" action="{{ route('admin.companies.store') }}" class="mt-4 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">@csrf
  <header class="flex flex-col justify-between gap-4 bg-[#062c21] p-7 text-white md:flex-row md:items-center">
   <div><span class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-300">Alta directa del superadministrador</span><h1 class="mt-2 text-2xl font-black">Nueva empresa de transporte</h1><p class="mt-1 text-xs text-emerald-100/70">Registra al operador y crea su acceso administrativo en una sola operación.</p></div>
   <span class="w-fit rounded-2xl border border-emerald-400/20 bg-white/10 px-4 py-3 text-xs font-bold">Activación inmediata</span>
  </header>
  <div class="grid gap-7 p-7 lg:grid-cols-[1.5fr_1fr]">
   <section>
    <div class="border-b border-slate-100 pb-3"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">1. Datos de la empresa</p><h2 class="text-lg font-black text-slate-950">Identidad y operación</h2></div>
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
     <label class="text-xs font-bold text-slate-600">Razón social<input name="company_name" required value="{{ old('company_name') }}" placeholder="Transportes Selva S.A.C." class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600">Nombre comercial<input name="commercial_name" value="{{ old('commercial_name') }}" placeholder="Selva Express" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600">RUC<input name="ruc" required maxlength="11" inputmode="numeric" value="{{ old('ruc') }}" placeholder="20XXXXXXXXX" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600">Ciudad base<input name="base_city" required value="{{ old('base_city', 'Iquitos') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600">Modalidad<select name="modality" required class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"><option value="fluvial" @selected(old('modality')==='fluvial')>🚤 Fluvial</option><option value="aereo" @selected(old('modality')==='aereo')>✈️ Aérea</option><option value="mixto" @selected(old('modality')==='mixto')>🚤✈️ Mixta</option></select></label>
     <label class="text-xs font-bold text-slate-600">Comisión NavegaYA (%)<input type="number" step=".01" min="0" max="100" name="commission_rate" required value="{{ old('commission_rate', 8) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600">Teléfono<input name="phone" required value="{{ old('phone') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600">WhatsApp<input name="whatsapp" value="{{ old('whatsapp') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600 sm:col-span-2">Dirección<input name="address" value="{{ old('address') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
     <label class="text-xs font-bold text-slate-600 sm:col-span-2">Sitio web (opcional)<input type="url" name="website" value="{{ old('website') }}" placeholder="https://empresa.pe" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
    </div>
   </section>
   <section class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5">
    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">2. Cuenta de acceso</p><h2 class="text-lg font-black text-slate-950">Administrador de la empresa</h2><p class="mt-1 text-xs text-slate-500">Estas credenciales permitirán gestionar flota, asientos, rutas e itinerarios de esta empresa.</p>
    <div class="mt-5 grid gap-4">
     <label class="text-xs font-bold text-slate-600">Nombre completo<input name="admin_name" required value="{{ old('admin_name') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-white"></label>
     <label class="text-xs font-bold text-slate-600">Correo de acceso<input type="email" name="admin_email" required value="{{ old('admin_email') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-white"></label>
     <label class="text-xs font-bold text-slate-600">Contraseña inicial<input type="password" name="admin_password" required minlength="8" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-white"></label>
     <label class="text-xs font-bold text-slate-600">Confirmar contraseña<input type="password" name="admin_password_confirmation" required minlength="8" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-white"></label>
    </div>
    <div class="mt-5 rounded-xl bg-white p-4 text-[11px] leading-5 text-slate-600"><strong class="block text-slate-900">Resultado del registro</strong>La empresa quedará activa y vinculada exclusivamente a esta cuenta administradora.</div>
   </section>
   @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-xs font-semibold text-rose-700 lg:col-span-2">{{ $errors->first() }}</div>@endif
   <div class="flex justify-end gap-3 border-t border-slate-100 pt-5 lg:col-span-2"><a href="{{ route('admin.companies.index', ['tab' => 'rendimiento']) }}" class="rounded-xl border border-slate-200 px-6 py-3 text-xs font-bold">Cancelar</a><button class="rounded-xl bg-amber-400 px-7 py-3 text-xs font-black text-slate-950 hover:bg-amber-300">Crear empresa y acceso</button></div>
  </div>
 </form>
</div>
</x-app-layout>
