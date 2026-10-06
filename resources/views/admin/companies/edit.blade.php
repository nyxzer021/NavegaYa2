<x-app-layout>
<x-slot name="header"><h1 class="ny-page-title">Editar empresa</h1></x-slot>
@php($admin=$organization->users->first(fn($user)=>$user->roles->contains('code','company_admin')))
<div class="mx-auto max-w-4xl">
 <a href="{{ route('admin.companies.index') }}" class="text-xs font-bold text-emerald-700">← Volver a empresas</a>
 <form method="POST" action="{{ route('admin.companies.update',$organization) }}" class="mt-4 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">@csrf @method('PUT')
  <header class="bg-[#062c21] p-7 text-white"><span class="text-[10px] font-bold uppercase tracking-widest text-emerald-300">Gobernanza de operadores</span><h1 class="mt-2 text-2xl font-black">{{ $organization->commercial_name ?: $organization->legal_name }}</h1><p class="mt-1 text-xs text-emerald-100/70">Actualiza datos comerciales y la comisión pactada.</p></header>
  <div class="grid gap-4 p-7 sm:grid-cols-2">
   <label class="text-xs font-bold text-slate-600">Razón social<input name="legal_name" required value="{{ old('legal_name',$organization->legal_name) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Nombre comercial<input name="commercial_name" value="{{ old('commercial_name',$organization->commercial_name) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Teléfono<input name="phone" required value="{{ old('phone',$organization->phone) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Ciudad base<input name="base_city" required value="{{ old('base_city',$organization->base_city ?: 'Iquitos') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Modalidad<select name="modality" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50">@foreach(['fluvial'=>'Fluvial','aereo'=>'Aérea','mixto'=>'Mixta'] as $value=>$label)<option value="{{ $value }}" @selected(old('modality',$organization->modality)===$value)>{{ $label }}</option>@endforeach</select></label>
   <label class="text-xs font-bold text-slate-600">Nivel comercial<select name="commercial_plan" required class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50">@foreach(['initial'=>'Inicial','standard'=>'Estándar','strategic'=>'Estratégico','custom'=>'Personalizado'] as $value=>$label)<option value="{{ $value }}" @selected(old('commercial_plan',$organization->commercial_plan ?? 'standard')===$value)>{{ $label }}</option>@endforeach</select></label>
   <label class="text-xs font-bold text-slate-600">Comisión NavegaYA negociada (%)<input type="number" step=".01" min="0" max="100" name="commission_rate" required value="{{ old('commission_rate',$organization->commission_rate) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Número de acuerdo<input name="agreement_number" value="{{ old('agreement_number',$organization->agreement_number) }}" placeholder="AC-2026-001" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <div class="grid grid-cols-2 gap-3"><label class="text-xs font-bold text-slate-600">Inicio<input type="date" name="commission_starts_on" value="{{ old('commission_starts_on',$organization->commission_starts_on?->toDateString()) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label><label class="text-xs font-bold text-slate-600">Fin<input type="date" name="commission_ends_on" value="{{ old('commission_ends_on',$organization->commission_ends_on?->toDateString()) }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label></div>
   <label class="text-xs font-bold text-slate-600 sm:col-span-2">Notas del acuerdo<textarea name="commission_notes" rows="3" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50">{{ old('commission_notes',$organization->commission_notes) }}</textarea></label>
   <div class="sm:col-span-2 mt-2 border-t border-slate-100 pt-5"><h2 class="font-black text-slate-900">Administrador principal y permisos</h2><p class="text-xs text-slate-400">Mantiene automáticamente el rol company_admin dentro de esta organización.</p></div>
   <label class="text-xs font-bold text-slate-600">Nombre del administrador<input name="admin_name" required value="{{ old('admin_name',$admin->name ?? '') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Correo de acceso<input type="email" name="admin_email" required value="{{ old('admin_email',$admin->email ?? '') }}" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Nueva contraseña (opcional)<input type="password" name="admin_password" minlength="8" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   <label class="text-xs font-bold text-slate-600">Confirmar nueva contraseña<input type="password" name="admin_password_confirmation" minlength="8" class="mt-1 h-11 w-full rounded-xl border-slate-200 bg-slate-50"></label>
   @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-xs text-rose-700 sm:col-span-2">{{ $errors->first() }}</div>@endif
   <div class="flex gap-3 sm:col-span-2"><button class="rounded-xl bg-amber-500 px-6 py-3 text-xs font-black">Guardar cambios</button><a href="{{ route('admin.companies.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-xs font-bold">Cancelar</a></div>
  </div>
 </form>
</div>
</x-app-layout>
