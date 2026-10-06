<x-app-layout>
    <x-slot name="header"><h2 class="ny-page-title">{{ isset($port) ? 'Editar puerto' : 'Registrar puerto' }}</h2></x-slot>
    @php
        $isEdit = isset($port);
        $field = fn (string $key, mixed $default = '') => old($key, $isEdit ? $port->{$key} : $default);
        $provinceUrl = route('admin.locations.provinces', ['department' => '__ID__']);
        $districtUrl = route('admin.locations.districts', ['province' => '__ID__']);
    @endphp
    <style>
        .port-form-wrap{max-width:760px;margin:0 auto}.port-card{margin-top:14px;padding:26px;border:1px solid #dbe8e4;border-radius:20px;background:#fff;box-shadow:0 12px 32px rgba(16,53,47,.06)}.port-card h1{margin:0;color:#12372d;font-size:25px}.port-intro{margin:7px 0 0;color:#64748b;font-size:14px}.port-section{margin-top:23px;padding-top:20px;border-top:1px solid #edf3f0}.port-section h2{margin:0;color:#176d60;font-size:15px}.port-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:15px}.port-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.port-grid label{display:block;color:#334155;font-size:12px;font-weight:800}.port-grid input,.port-grid select{box-sizing:border-box;width:100%;margin-top:6px;padding:10px 11px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#1e293b}.port-grid select:disabled{background:#f5f7f6;color:#94a3b8}.port-help{margin-top:12px;padding:11px 13px;border-radius:10px;background:#eff9f5;color:#356056;font-size:12px}.port-save{margin-top:23px;border:0;border-radius:10px;background:#12372d;padding:11px 17px;color:#fff;font-weight:800;cursor:pointer}.port-cancel{margin-left:8px;color:#176d60;font-size:13px;font-weight:800;text-decoration:none}@media(max-width:650px){.port-grid,.port-grid.two{grid-template-columns:1fr}.port-card{padding:20px}}
    </style>
    <div class="port-form-wrap">
        <a href="{{ route('admin.ports.index') }}" class="port-cancel" style="margin-left:0">← Volver a puertos</a>
        <form method="POST" action="{{ $isEdit ? route('admin.ports.update', $port) : route('admin.ports.store') }}" class="port-card">
            @csrf
            @if ($isEdit)
                @method('PATCH')
            @endif
            <h1>{{ $isEdit ? 'Editar puerto de embarque' : 'Nuevo puerto de embarque' }}</h1>
            <p class="port-intro">Selecciona la ubicación oficial y luego identifica el punto exacto de embarque.</p>
            <section class="port-section" style="margin-top:18px;padding-top:0;border-top:0">
                <h2>Ubicación administrativa</h2>
                <div class="port-grid">
                    <label>Departamento<select id="department" name="department_id" required><option value="">Selecciona</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected($field('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></label>
                    <label>Provincia<select id="province" name="province_id" required @disabled(! $field('department_id'))><option value="">Primero departamento</option>@foreach ($initialProvinces as $province)<option value="{{ $province->id }}" @selected($field('province_id') == $province->id)>{{ $province->name }}</option>@endforeach</select></label>
                    <label>Distrito<select id="district" name="district_id" required @disabled(! $field('province_id'))><option value="">Primero provincia</option>@foreach ($initialDistricts as $district)<option value="{{ $district->id }}" @selected($field('district_id') == $district->id)>{{ $district->name }}</option>@endforeach</select></label>
                </div>
                <div class="port-help">Un mismo puerto podrá ser salida o llegada. La dirección de ida o regreso se define al crear la ruta, no aquí.</div>
            </section>
            <section class="port-section">
                <h2>Identificación del embarcadero</h2>
                <div class="port-grid two">
                    <label>Nombre del puerto o embarcadero<input name="name" required value="{{ $field('name') }}" placeholder="Ej. Puerto La Boca"></label>
                    <label>Río<input name="river" value="{{ $field('river') }}" placeholder="Ej. Amazonas"></label>
                    <label style="grid-column:1/-1">Referencia o dirección<input name="address" value="{{ $field('address') }}" placeholder="Ej. frente al mercado, muelle principal"></label>
                    <label>Latitud (opcional)<input type="number" step="0.0000001" name="latitude" value="{{ $field('latitude') }}"></label>
                    <label>Longitud (opcional)<input type="number" step="0.0000001" name="longitude" value="{{ $field('longitude') }}"></label>
                </div>
            </section>
            @if ($errors->any())
                <div style="margin-top:18px;padding:12px;border-radius:10px;background:#fff1f2;color:#be123c;font-size:13px"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <button class="port-save">{{ $isEdit ? 'Guardar cambios' : 'Guardar puerto' }}</button><a class="port-cancel" href="{{ route('admin.ports.index') }}">Cancelar</a>
        </form>
    </div>
    <script>
        const department=document.getElementById('department'),province=document.getElementById('province'),district=document.getElementById('district');
        const provinceUrl=@json($provinceUrl),districtUrl=@json($districtUrl);
        function reset(select,label){select.innerHTML=`<option value="">${label}</option>`;select.disabled=true;}
        async function fill(select,url,label){reset(select,'Cargando...');try{const rows=await fetch(url,{headers:{Accept:'application/json'}}).then(r=>r.json());select.innerHTML=`<option value="">${label}</option>`+rows.map(row=>`<option value="${row.id}">${row.name}</option>`).join('');select.disabled=false;}catch{reset(select,'No se pudo cargar');}}
        department.addEventListener('change',()=>{reset(district,'Primero provincia');if(!department.value){reset(province,'Primero departamento');return;}fill(province,provinceUrl.replace('__ID__',department.value),'Selecciona provincia');});
        province.addEventListener('change',()=>{if(!province.value){reset(district,'Primero provincia');return;}fill(district,districtUrl.replace('__ID__',province.value),'Selecciona distrito');});
    </script>
</x-app-layout>
