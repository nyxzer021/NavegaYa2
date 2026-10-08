<x-app-layout>
@php($editing=(bool) $masterRoute)
<x-slot name="header"><h2 class="ny-page-title">{{ $editing ? 'Editar Tramo Maestro' : 'Nuevo Tramo Maestro' }}</h2></x-slot>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.18.3/dist/leaflet-geoman.css">
<div class="mx-auto max-w-5xl px-4 py-4 sm:px-6">
 <a href="{{ route('admin.master-routes.index') }}" class="text-xs font-bold text-emerald-800">← Volver al catálogo</a>
 <form method="POST" action="{{ $editing?route('admin.master-routes.update',$masterRoute):route('admin.master-routes.store') }}" class="mt-4 overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm">@csrf @if($editing)@method('PUT')@endif
  <div class="bg-[#062c21] px-6 py-6 text-white sm:px-8"><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Gobernanza geográfica</span><h1 class="mt-1 text-2xl font-black">{{ $editing?'Editar conexión oficial':'Registrar conexión oficial' }}</h1><p class="mt-1 text-xs text-emerald-100/70">El origen y destino deben ser terminales distintos y pertenecer a sus ciudades declaradas.</p></div>
  <div class="grid gap-5 p-6 sm:grid-cols-2 sm:p-8">
   <label class="text-xs font-bold text-slate-700">Código oficial<input name="code" required maxlength="30" value="{{ old('code',$masterRoute->code??'') }}" placeholder="TRM-IQT-NAU" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm uppercase"></label>
   <label class="text-xs font-bold text-slate-700">Modalidad<select id="routeModality" name="modality" required class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="fluvial" @selected(old('modality',$masterRoute->modality??'fluvial')==='fluvial')>🚤 Fluvial</option><option value="aereo" @selected(old('modality',$masterRoute->modality??'')==='aereo')>✈️ Aéreo</option></select></label>
   <label class="text-xs font-bold text-slate-700">Ciudad de origen<input name="origin_city" required value="{{ old('origin_city',$masterRoute->origin_city??'') }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Terminal oficial de origen<select id="originPort" name="origin_port_id" required class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Seleccionar terminal</option>@foreach($ports as $port)<option value="{{ $port->id }}" data-latitude="{{ $port->latitude }}" data-longitude="{{ $port->longitude }}" @selected(old('origin_port_id',$masterRoute->origin_port_id??null)==$port->id)>{{ $port->city }} · {{ $port->name }}</option>@endforeach</select></label>
   <label class="text-xs font-bold text-slate-700">Ciudad de destino<input name="destination_city" required value="{{ old('destination_city',$masterRoute->destination_city??'') }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Terminal oficial de destino<select id="destinationPort" name="destination_port_id" required class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Seleccionar terminal</option>@foreach($ports as $port)<option value="{{ $port->id }}" data-latitude="{{ $port->latitude }}" data-longitude="{{ $port->longitude }}" @selected(old('destination_port_id',$masterRoute->destination_port_id??null)==$port->id)>{{ $port->city }} · {{ $port->name }}</option>@endforeach</select></label>
   <label class="text-xs font-bold text-slate-700">Cuenca hidrográfica<input name="river_basin" value="{{ old('river_basin',$masterRoute->river_basin??'') }}" placeholder="Río Amazonas / Marañón" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Corredor regional<input name="corridor" value="{{ old('corridor',$masterRoute->corridor??'') }}" placeholder="Corredor Nororiente" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Duración referencial<input name="estimated_duration_text" value="{{ old('estimated_duration_text',$masterRoute->estimated_duration_text??'') }}" placeholder="1 h 45 min" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
   <label class="text-xs font-bold text-slate-700">Estado hidrológico / operativo<select name="status" required class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="active" @selected(old('status',$masterRoute->status??'active')==='active')>Operativo</option><option value="suspended_river_level" @selected(old('status',$masterRoute->status??'')==='suspended_river_level')>Precaución / zarpes suspendidos</option><option value="maintenance" @selected(old('status',$masterRoute->status??'')==='maintenance')>Mantenimiento</option></select></label>
   <section class="overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50/40 sm:col-span-2">
    <div class="flex flex-col gap-3 border-b border-emerald-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
     <div><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Trayectoria geográfica</p><h3 class="text-base font-black text-slate-900">Dibuja el recorrido oficial</h3><p id="routeMapHelp" class="mt-1 text-[10px] text-slate-500">Selecciona terminales con coordenadas y dibuja la línea siguiendo el río.</p></div>
     <div class="flex flex-wrap gap-2"><button id="fitRouteMap" type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black text-slate-700">Centrar terminales</button><button id="clearRoutePath" type="button" class="rounded-lg bg-rose-50 px-3 py-2 text-[10px] font-black text-rose-700">Limpiar recorrido</button></div>
    </div>
    <div id="masterRouteMap" class="h-[430px] w-full bg-slate-100"></div>
    <div class="grid gap-2 border-t border-emerald-200 bg-white px-5 py-3 text-[9px] text-slate-500 sm:grid-cols-3"><span>1. Selecciona origen y destino</span><span>2. Usa la herramienta de línea</span><span>3. Guarda el tramo maestro</span></div>
    <input id="pathGeoJson" type="hidden" name="path_geojson" value="{{ json_encode(old('path_geojson', $masterRoute?->path_geojson), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
   </section>
   @if($errors->any())<div class="sm:col-span-2 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700"><ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
   <div class="flex justify-end sm:col-span-2"><button class="h-11 rounded-xl bg-amber-500 px-6 text-xs font-black text-slate-950 hover:bg-amber-600">{{ $editing?'Guardar cambios':'Crear tramo maestro' }}</button></div>
  </div>
 </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.18.3/dist/leaflet-geoman.min.js"></script>
<script>
(() => {
    const initializeRouteEditor = () => {
        const element = document.getElementById('masterRouteMap');
        if (!element || element.dataset.initialized || typeof L === 'undefined') return;
        element.dataset.initialized = 'true';

        const modality = document.getElementById('routeModality');
        const origin = document.getElementById('originPort');
        const destination = document.getElementById('destinationPort');
        const pathInput = document.getElementById('pathGeoJson');
        const help = document.getElementById('routeMapHelp');
        const map = L.map(element, {maxBoundsViscosity: .65}).setView([-4.7, -73.8], 6);
        const loretoBounds = L.latLngBounds([[-8.8, -77.2], [-0.8, -68.3]]);
        map.setMaxBounds(loretoBounds.pad(.2));
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 18, attribution: '© OpenStreetMap'}).addTo(map);
        map.pm.addControls({position: 'topleft', drawMarker: false, drawCircle: false, drawCircleMarker: false, drawRectangle: false, drawPolygon: false, drawText: false, cutPolygon: false, rotateMode: false});

        let routeLayer = null;
        let previewLayer = null;
        let originMarker = null;
        let destinationMarker = null;

        const coordinates = select => {
            const option = select.options[select.selectedIndex];
            const latitude = Number(option?.dataset.latitude);
            const longitude = Number(option?.dataset.longitude);
            return Number.isFinite(latitude) && Number.isFinite(longitude) && option?.dataset.latitude !== '' ? [latitude, longitude] : null;
        };
        const serialize = layer => {
            if (!layer) { pathInput.value = ''; return; }
            const latLngs = layer.getLatLngs();
            pathInput.value = JSON.stringify({type: 'LineString', coordinates: latLngs.map(point => [point.lng, point.lat])});
        };
        const routeStyle = () => ({color: modality.value === 'aereo' ? '#0284c7' : '#059669', weight: 5, opacity: .95, dashArray: modality.value === 'aereo' ? '10 8' : null});
        const replaceRoute = latLngs => {
            if (routeLayer) map.removeLayer(routeLayer);
            routeLayer = L.polyline(latLngs, routeStyle()).addTo(map);
            routeLayer.pm.enable({allowSelfIntersection: false});
            routeLayer.on('pm:edit', () => serialize(routeLayer));
            serialize(routeLayer);
        };
        const fitTerminals = () => {
            const points = [coordinates(origin), coordinates(destination)].filter(Boolean);
            if (points.length === 2) map.fitBounds(points, {padding: [55, 55], maxZoom: 9});
            else map.fitBounds(loretoBounds, {padding: [15, 15]});
        };
        const refreshTerminals = () => {
            const originCoordinates = coordinates(origin);
            const destinationCoordinates = coordinates(destination);
            if (originMarker) map.removeLayer(originMarker);
            if (destinationMarker) map.removeLayer(destinationMarker);
            if (previewLayer) map.removeLayer(previewLayer);
            originMarker = originCoordinates ? L.marker(originCoordinates, {pmIgnore: true}).addTo(map).bindTooltip('Origen: '+origin.options[origin.selectedIndex].text, {permanent: false}) : null;
            destinationMarker = destinationCoordinates ? L.marker(destinationCoordinates, {pmIgnore: true}).addTo(map).bindTooltip('Destino: '+destination.options[destination.selectedIndex].text, {permanent: false}) : null;
            if (originCoordinates && destinationCoordinates && !routeLayer) previewLayer = L.polyline([originCoordinates, destinationCoordinates], {color: '#94a3b8', weight: 3, dashArray: '7 7'}).addTo(map);
            if (modality.value === 'aereo' && originCoordinates && destinationCoordinates) replaceRoute([originCoordinates, destinationCoordinates]);
            help.textContent = modality.value === 'aereo' ? 'La conexión aérea se genera automáticamente entre los terminales.' : 'Usa la herramienta de línea para seguir el recorrido del río.';
            fitTerminals();
        };

        try {
            const initial = JSON.parse(pathInput.value || 'null');
            if (initial?.type === 'LineString' && Array.isArray(initial.coordinates)) replaceRoute(initial.coordinates.map(point => [point[1], point[0]]));
        } catch (_) { pathInput.value = ''; }

        map.on('pm:create', event => {
            if (!(event.layer instanceof L.Polyline) || event.layer instanceof L.Polygon) { map.removeLayer(event.layer); return; }
            const latLngs = event.layer.getLatLngs();
            map.removeLayer(event.layer);
            replaceRoute(latLngs);
        });
        map.on('pm:remove', event => { if (event.layer === routeLayer) { routeLayer = null; serialize(null); refreshTerminals(); } });
        [origin, destination, modality].forEach(field => field.addEventListener('change', refreshTerminals));
        document.getElementById('fitRouteMap').addEventListener('click', fitTerminals);
        document.getElementById('clearRoutePath').addEventListener('click', () => { if (routeLayer) map.removeLayer(routeLayer); routeLayer = null; serialize(null); refreshTerminals(); });
        refreshTerminals();
        setTimeout(() => map.invalidateSize(), 100);
    };
    document.addEventListener('DOMContentLoaded', initializeRouteEditor, {once: true});
    document.addEventListener('turbo:load', initializeRouteEditor);
    initializeRouteEditor();
})();
</script>
</x-app-layout>
