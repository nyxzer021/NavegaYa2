@extends('layouts.admin')
@section('header')<h1 class="ny-page-title">{{ __('admin_portal.menu.fleet', [], 'es') }}</h1>@endsection
@section('content')
@php
    $labels = ['draft' => 'Plano incompleto', 'pending_review' => 'Pendiente de revisión', 'ready' => 'Habilitada', 'changes_requested' => 'Observada', 'rejected' => 'Rechazada', 'maintenance' => 'Mantenimiento', 'inactive' => 'Inhabilitada', 'suspended' => 'Suspendida'];
@endphp
<div class="space-y-6">
 <section class="rounded-3xl bg-[#062c21] p-7 text-white"><span class="text-[10px] font-black uppercase tracking-widest text-emerald-300">Cumplimiento DICAPI / DGAC</span><h1 class="mt-2 text-2xl font-black">{{ __('admin_portal.menu.fleet', [], 'es') }}</h1><p class="mt-1 text-xs text-emerald-100/70">{{ __('admin_portal.fleet.enabled_capacity', [], 'es') }}: {{ $enabledSeatCapacity }} {{ __('admin_portal.fleet.seats', [], 'es') }}</p></section>
 @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
 <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
  <header class="flex items-center justify-between border-b border-slate-100 p-5"><div><h2 class="font-black text-slate-900">Embarcaciones fluviales</h2><p class="text-xs text-slate-500">Revisión compartida con el panel de cada empresa.</p></div><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">{{ \App\Support\SpanishCount::phrase($vessels->where('status','pending_review')->count(), 'pendiente', 'pendientes') }}</span></header>
  <div class="divide-y divide-slate-100">@forelse($vessels as $vessel)
   @php
    $insuranceBadge = \App\Support\DocumentDeadline::badge($vessel->insurance_expires_at);
    $inspectionBadge = \App\Support\DocumentDeadline::badge($vessel->inspection_expires_at);
    $planMissing = \App\Support\FleetPlan::missingText($vessel);
    $vestShortfall = \App\Support\FleetPlan::vestShortfallLabel($vessel);
    $insuranceText = \App\Support\DocumentDeadline::pair($vessel->insurance_policy, $vessel->insurance_expires_at);
   @endphp
   <article class="grid gap-4 p-5 lg:grid-cols-[1fr_1.3fr] {{ $vessel->status==='pending_review'?'bg-amber-50/30':'' }}">
    <div>
     <div class="flex flex-wrap items-center gap-2"><h3 class="font-black text-slate-900">{{ $vessel->name }}</h3><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $vessel->status==='pending_review'?'bg-amber-100 text-amber-800':($vessel->status==='ready'?'bg-emerald-100 text-emerald-800':'bg-slate-100 text-slate-600') }}">{{ $labels[$vessel->status] ?? \App\Support\PanelLabel::status($vessel->status) }}</span></div>
     <p class="mt-1 text-xs text-slate-600">{{ $vessel->organization->commercial_name ?? $vessel->organization->legal_name ?? '—' }} · Matrícula {{ $vessel->registration_number }}</p>
     <div class="mt-3 grid grid-cols-3 gap-2 text-[11px]"><span class="rounded-lg bg-slate-50 p-2"><b class="block">Capacidad</b>{{ $vessel->seat_capacity }}</span><span class="rounded-lg bg-slate-50 p-2"><b class="block">Plano</b>{{ \App\Support\FleetPlan::drawn($vessel) }}/{{ $vessel->seat_capacity }}</span><span class="rounded-lg bg-slate-50 p-2"><b class="block">Solicitud</b>{{ $vessel->submitted_for_review_at?->format('d/m/Y H:i')??'No enviada' }}</span></div>
     <ul class="mt-3 space-y-1 text-xs text-slate-700">
      <li class="flex flex-wrap items-center gap-2"><b>{{ __('admin_portal.fleet.vests', [], 'es') }}</b> · {{ $vessel->life_vest_count ?? 0 }} chalecos · {{ $vessel->seat_capacity }} {{ __('admin_portal.fleet.seats', [], 'es') }} @if($vestShortfall)<span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">{{ $vestShortfall }}</span>@endif</li>
      <li><b>{{ __('admin_portal.fleet.dicapi', [], 'es') }}</b> · {{ $vessel->dicapi_certificate_number ?: __('admin_portal.fleet.no_number', [], 'es') }}</li>
      <li class="flex flex-wrap items-center gap-2"><b>{{ __('admin_portal.fleet.insurance', [], 'es') }}</b> · {{ $insuranceText }} @if($insuranceBadge)<span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $insuranceBadge['tone'] === 'rose' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $insuranceBadge['text'] }}</span>@endif</li>
      <li class="flex flex-wrap items-center gap-2"><b>{{ __('admin_portal.fleet.inspection', [], 'es') }}</b> · {{ $vessel->inspection_expires_at?->format('d/m/Y') ?? __('admin_portal.fleet.no_date', [], 'es') }} @if($inspectionBadge)<span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $inspectionBadge['tone'] === 'rose' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $inspectionBadge['text'] }}</span>@endif</li>
     </ul>
     @if($planMissing)<p class="mt-3 rounded-lg bg-amber-50 p-2 text-xs font-bold text-amber-950">{{ $planMissing }}</p>@endif
     @if($vessel->review_notes)<p class="mt-3 rounded-lg bg-rose-50 p-2 text-xs text-rose-700"><b>Última observación:</b> {{ $vessel->review_notes }}</p>@endif
    </div>
    <div>@if($vessel->status==='pending_review')<form method="POST" action="{{ route('admin.fleet-compliance.verify',$vessel) }}" class="space-y-3">@csrf @method('PATCH')<label class="block text-xs font-bold text-slate-700">Resultado<select name="decision" required class="mt-1 w-full rounded-xl border-slate-200 text-sm"><option value="approve">Aprobar y habilitar</option><option value="observe">Observar y devolver para corrección</option><option value="reject">Rechazar solicitud</option></select></label><label class="block text-xs font-bold text-slate-700">{{ __('admin_portal.fleet.reason', [], 'es') }}<textarea name="review_notes" rows="3" required class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea></label><button class="w-full rounded-xl bg-amber-500 px-4 py-3 text-xs font-black text-slate-950">Registrar revisión</button></form>@else<div class="space-y-3"><div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-500">{{ $vessel->status==='draft'?'La empresa todavía no envió esta unidad a revisión.':'No hay una solicitud pendiente para esta unidad.' }}</div>@if($vessel->status==='ready')<form method="POST" action="{{ route('admin.fleet-compliance.vessels.suspend',$vessel) }}">@csrf @method('PATCH')<label class="mt-2 block text-xs font-bold text-slate-700">{{ __('admin_portal.fleet.reason', [], 'es') }}<textarea name="reason" required rows="2" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea></label><button class="mt-2 w-full rounded-xl border border-rose-200 px-4 py-2 text-xs font-black text-rose-700">{{ __('admin_portal.fleet.suspend', [], 'es') }}</button></form>@endif</div>@endif</div>
   </article>@empty<div class="p-10 text-center text-slate-400">No hay embarcaciones registradas.</div>@endforelse</div><div class="border-t p-4">{{ $vessels->links() }}</div>
 </section>
 <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Aeronaves</h2><div class="mt-4 grid gap-3 md:grid-cols-2">@forelse($aircraft as $unit)
  @php
   $airBadge = \App\Support\DocumentDeadline::badge($unit->airworthiness_expires_at);
   $policyBadge = \App\Support\DocumentDeadline::badge($unit->aviation_policy_expires_at);
   $planMissing = \App\Support\FleetPlan::missingText($unit);
   $policyText = \App\Support\DocumentDeadline::pair($unit->aviation_policy, $unit->aviation_policy_expires_at);
  @endphp
  <article class="rounded-xl border p-4 text-sm"><b>{{ $unit->name }}</b><p class="text-xs text-slate-500">{{ $unit->organization->commercial_name ?? $unit->organization->legal_name }} · {{ $unit->registration_number }} · {{ $unit->seat_capacity }} asientos · {{ __('company_portal.fleet.'.($unit->aircraft_kind ?: 'light_aircraft'), [], 'es') }}</p>
   <ul class="mt-2 space-y-1 text-xs text-slate-700">
    <li><b>{{ __('admin_portal.fleet.dgac', [], 'es') }}</b> · {{ $unit->dgac_certificate_number ?: __('admin_portal.fleet.no_number', [], 'es') }}</li>
    <li class="flex flex-wrap items-center gap-2"><b>{{ __('admin_portal.fleet.airworthiness', [], 'es') }}</b> · {{ $unit->airworthiness_expires_at?->format('d/m/Y') ?? __('admin_portal.fleet.no_date', [], 'es') }} @if($airBadge)<span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $airBadge['tone'] === 'rose' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $airBadge['text'] }}</span>@endif</li>
    <li class="flex flex-wrap items-center gap-2"><b>{{ __('admin_portal.fleet.policy', [], 'es') }}</b> · {{ $policyText }} @if($policyBadge)<span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $policyBadge['tone'] === 'rose' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $policyBadge['text'] }}</span>@endif</li>
   </ul>
   @if($planMissing)<p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs font-bold text-amber-950">{{ $planMissing }}</p>@endif
   @if($unit->status==='pending_review')<form method="POST" action="{{ route('admin.fleet-compliance.aircraft.verify',$unit) }}" class="mt-3 space-y-2">@csrf @method('PATCH')<select name="decision" required class="w-full rounded-xl border-slate-200 text-sm"><option value="approve">Aprobar y habilitar</option><option value="observe">Observar</option><option value="reject">Rechazar</option></select><textarea name="review_notes" required rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="{{ __('admin_portal.fleet.reason', [], 'es') }}"></textarea><button class="w-full rounded-xl bg-amber-500 px-3 py-2 text-xs font-black">Registrar revisión</button></form>@elseif($unit->status==='ready')<form method="POST" action="{{ route('admin.fleet-compliance.aircraft.suspend',$unit) }}" class="mt-3 space-y-2">@csrf @method('PATCH')<textarea name="reason" required rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="{{ __('admin_portal.fleet.reason', [], 'es') }}"></textarea><button class="w-full rounded-xl border border-rose-200 px-3 py-2 text-xs font-black text-rose-700">{{ __('admin_portal.fleet.suspend', [], 'es') }}</button></form>@endif</article>
 @empty<p class="text-slate-400">No hay aeronaves registradas.</p>@endforelse</div></section>
</div>
@endsection
