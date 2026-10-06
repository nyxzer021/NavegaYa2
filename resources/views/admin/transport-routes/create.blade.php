<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">{{ $routeRecord ? 'Editar ruta fluvial' : 'Nueva ruta fluvial' }}</h2></x-slot>
@php
$isEdit = (bool) $routeRecord;
$value = fn ($key, $default = '') => old($key, $isEdit ? $routeRecord->{$key} : $default);
$originCity = old('origin_city', $routeRecord?->originPort?->city ?? $initialOriginPorts->first()?->city);
$destinationCity = old('destination_city', $routeRecord?->destinationPort?->city ?? $initialDestinationPorts->first()?->city);
$portsUrl = route('admin.transport-routes.city-ports', ['city' => '__CITY__']);
@endphp
<style>
.ny-route-form{max-width:980px;margin:0 auto}.ny-card{padding:30px;border:1px solid #dce7e4;border-radius:20px;background:#fff}.ny-card h1{margin:0;color:#12372d}.ny-card>p{color:#64748b}.ny-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:22px}.ny-grid label{display:block;color:#334155;font-size:13px;font-weight:700}.ny-grid input,.ny-grid select,.ny-grid textarea{box-sizing:border-box;width:100%;margin-top:7px;padding:11px;border:1px solid #cbd5e1;border-radius:9px;background:#fff}.ny-grid select:disabled{background:#f4f7f6;color:#94a3b8}.fixed-field{background:#f1f5f4!important;color:#64748b!important;cursor:not-allowed}.ny-grid textarea{min-height:100px}.ny-save{margin-top:24px;border:0;border-radius:10px;background:#12372d;padding:12px 18px;color:#fff;font-weight:800}.ny-back{color:#176d60;text-decoration:none;font-weight:800;font-size:13px}.route-step{grid-column:1/-1;padding:13px 15px;border-radius:12px;background:#eff9f5;color:#356056;font-size:13px}.route-step strong{color:#12372d}@media(max-width:650px){.ny-grid{grid-template-columns:1fr}}
</style>
<div class="ny-route-form">
<a class="ny-back" href="{{ route('admin.transport-routes.index') }}">← Volver a rutas y salidas</a>
<form class="ny-card" method="POST" action="{{ $isEdit ? route('admin.transport-routes.update', $routeRecord) : route('admin.transport-routes.store') }}">
@csrf
@if($isEdit)
@method('PATCH')
@endif
<h1>{{ $isEdit ? 'Editar ruta fluvial' : 'Registrar ruta fluvial' }}</h1>
<p>El pasajero verá ciudades. La operación conservará el puerto exacto de salida y llegada.</p>
<div class="ny-grid">
<label>Empresa de transporte<select name="organization_id" required><option value="">Selecciona la empresa</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($value('organization_id') == $organization->id)>{{ $organization->commercial_name ?: $organization->legal_name }}</option>@endforeach</select></label>
<label>Código de ruta<input class="fixed-field" value="{{ $isEdit ? $routeRecord->code : 'Automático al guardar' }}" readonly><small style="display:block;margin-top:5px;color:#64748b;font-weight:400">El sistema lo genera automáticamente.</small></label>
<div class="route-step"><strong>1.</strong> Selecciona la ciudad y después el puerto real desde el cual sale o llega la embarcación.</div>
<label>Ciudad de origen<select id="originCity" name="origin_city" required><option value="">Selecciona ciudad de origen</option>@foreach($cities as $city)<option value="{{ $city }}" @selected($originCity === $city)>{{ $city }}</option>@endforeach</select></label>
<label>Puerto de salida<select id="originPort" name="origin_port_id" required @disabled(!$originCity)><option value="">{{ $originCity ? 'Selecciona puerto de salida' : 'Primero selecciona ciudad' }}</option>@foreach($initialOriginPorts as $port)<option value="{{ $port->id }}" @selected($value('origin_port_id') == $port->id)>{{ $port->name }}</option>@endforeach</select></label>
<label>Ciudad de destino<select id="destinationCity" name="destination_city" required><option value="">Selecciona ciudad de destino</option>@foreach($cities as $city)<option value="{{ $city }}" @selected($destinationCity === $city)>{{ $city }}</option>@endforeach</select></label>
<label>Puerto de llegada<select id="destinationPort" name="destination_port_id" required @disabled(!$destinationCity)><option value="">{{ $destinationCity ? 'Selecciona puerto de llegada' : 'Primero selecciona ciudad' }}</option>@foreach($initialDestinationPorts as $port)<option value="{{ $port->id }}" @selected($value('destination_port_id') == $port->id)>{{ $port->name }}</option>@endforeach</select></label>
<label>Duración estimada (minutos)<input type="number" min="1" name="estimated_duration_minutes" value="{{ $value('estimated_duration_minutes') }}" placeholder="Ej. 360"></label>
<label>Distancia aproximada (km)<input type="number" step="0.01" min="0.01" name="distance_km" value="{{ $value('distance_km') }}" placeholder="Ej. 140"></label>
<label style="grid-column:1/-1">Descripción<textarea name="description" placeholder="Frecuencia, punto de encuentro o indicaciones operativas">{{ $value('description') }}</textarea></label>
</div>
@if($errors->any())
<div style="margin-top:18px;padding:14px;border-radius:10px;background:#fff1f2;color:#be123c"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<button class="ny-save">{{ $isEdit ? 'Guardar cambios' : 'Guardar ruta' }}</button>
</form>
</div>
<script>
const cityPortsUrl=@json($portsUrl);
async function loadPorts(city, select, placeholder){
  select.innerHTML='<option value="">Cargando puertos…</option>';select.disabled=true;
  if(!city){select.innerHTML=`<option value="">${placeholder}</option>`;return;}
  try{const response=await fetch(cityPortsUrl.replace('__CITY__',encodeURIComponent(city)));const ports=await response.json();select.innerHTML=`<option value="">${ports.length?'Selecciona un puerto':'No hay puertos habilitados'}</option>`+ports.map(port=>`<option value="${port.id}">${port.name}</option>`).join('');select.disabled=!ports.length;}catch(error){select.innerHTML='<option value="">No se pudieron cargar los puertos</option>';}
}
document.getElementById('originCity').addEventListener('change',event=>loadPorts(event.target.value,document.getElementById('originPort'),'Primero selecciona ciudad'));
document.getElementById('destinationCity').addEventListener('change',event=>loadPorts(event.target.value,document.getElementById('destinationPort'),'Primero selecciona ciudad'));
</script>
</x-app-layout>
