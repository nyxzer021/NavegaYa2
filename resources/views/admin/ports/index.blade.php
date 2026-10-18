<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-[#12372d]">Puertos de embarque</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl py-3">
        @if (session('success'))
            <div class="mb-5 rounded-xl bg-emerald-50 px-5 py-4 font-semibold text-emerald-800">✓ {{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="mb-5 rounded-xl bg-rose-50 px-5 py-4 font-semibold text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="mb-4 rounded-2xl bg-gradient-to-r from-[#10352f] to-[#1d6457] px-6 py-5 text-white">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-200">Catálogo operativo</p>
                <h1 class="mt-1 text-2xl font-bold">Puertos y aeródromos</h1>
                <p class="mt-1 text-sm text-emerald-50">Catálogo maestro de terminales que luego se seleccionan al crear los tramos.</p>
            </div>
        </div>

        <details id="nuevo-puerto" class="mb-4 overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm" @if(request('nuevo') || $errors->any()) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between bg-emerald-50 px-5 py-3 text-sm font-black text-emerald-900">
                <span>＋ Nuevo puerto, terminal o aeródromo</span><span class="text-xs font-semibold text-emerald-700">Registrar sin salir del catálogo</span>
            </summary>
            <form method="POST" action="{{ route('admin.ports.store') }}" class="border-t border-emerald-100 p-5">
                @csrf
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-xs font-bold text-slate-700">Departamento<input value="{{ $loreto->name }}" readonly class="mt-1 h-10 w-full rounded-lg border-slate-200 bg-slate-100 text-xs"><input type="hidden" name="department_id" value="{{ $loreto->id }}"></label>
                    <label class="text-xs font-bold text-slate-700">Provincia<select id="newProvince" name="province_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs"><option value="">Selecciona provincia</option>@foreach($initialProvinces as $province)<option value="{{$province->id}}" @selected(old('province_id')==$province->id)>{{$province->name}}</option>@endforeach</select></label>
                    <label class="text-xs font-bold text-slate-700">Distrito<select id="newDistrict" name="district_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs"><option value="">Selecciona primero la provincia</option></select></label>
                    <label class="text-xs font-bold text-slate-700">Nombre del terminal<input name="name" value="{{old('name')}}" required class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs" placeholder="Ej. Puerto La Boca"></label>
                    <label class="text-xs font-bold text-slate-700">Modalidad<select name="modality" required class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs"><option value="fluvial" @selected(old('modality','fluvial')==='fluvial')>🚤 Fluvial</option><option value="aereo" @selected(old('modality')==='aereo')>✈️ Aéreo</option></select></label>
                    <label class="text-xs font-bold text-slate-700">Tipo de instalación<select name="port_type" required class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs">@foreach(['terminal'=>'Terminal fluvial','embarcadero'=>'Embarcadero','muelle'=>'Muelle','muelle_flotante'=>'Muelle flotante','privado'=>'Puerto privado','aerodromo'=>'Aeródromo / pista','otro'=>'Otro'] as $value=>$label)<option value="{{$value}}" @selected(old('port_type','embarcadero')===$value)>{{$label}}</option>@endforeach</select></label>
                    <label class="text-xs font-bold text-slate-700">Río, pista o referencia<input name="river" value="{{old('river')}}" required class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs" placeholder="Ej. Amazonas"></label>
                    <label class="text-xs font-bold text-slate-700">Teléfono<input name="contact_phone" value="{{old('contact_phone')}}" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs" placeholder="999 999 999"></label>
                    <label class="text-xs font-bold text-slate-700 md:col-span-2">Dirección o referencia<input name="address" value="{{old('address')}}" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs"></label>
                    <label class="text-xs font-bold text-slate-700">Latitud (opcional)<input type="number" step="0.0000001" name="latitude" value="{{old('latitude')}}" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs"></label>
                    <label class="text-xs font-bold text-slate-700">Longitud (opcional)<input type="number" step="0.0000001" name="longitude" value="{{old('longitude')}}" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-xs"></label>
                </div>
                @if($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700">{{$errors->first()}}</div>@endif
                <div class="mt-4 flex justify-end"><button class="rounded-lg bg-emerald-800 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-900">Guardar terminal</button></div>
            </form>
        </details>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Terminal</th><th class="px-4 py-4">Tipo</th><th class="px-4 py-4">Ciudad / provincia</th><th class="px-4 py-4">Río o pista</th><th class="px-6 py-4">Estado</th><th class="px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($ports as $port)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-bold text-slate-800">{{ $port->name }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $port->modality==='aereo'?'bg-violet-50 text-violet-700':'bg-sky-50 text-sky-700' }}">{{ $port->modality==='aereo'?'✈ Aéreo':'⚓ Fluvial' }}</span></td>
                                <td class="px-4 py-4 text-slate-700">{{ $port->city }}<br><span class="text-[10px] text-slate-400">{{ $port->province->name ?? 'Loreto' }} · {{ $port->region }}</span></td>
                                <td class="px-4 py-4 text-slate-700">{{ $port->river ?: 'No registrado' }}</td>
                                <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $port->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $port->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('admin.ports.edit', $port) }}" class="font-bold text-[#146e60]">Editar</a>
                                    <form method="POST" action="{{ route('admin.ports.destroy', $port) }}" class="ml-3 inline" onsubmit="return confirm('¿Eliminar este puerto? Esta acción no se puede deshacer.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-bold text-rose-700">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Aún no hay puertos registrados. Crea el primero para comenzar a construir rutas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($ports->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">{{ $ports->links() }}</div>
            @endif
        </div>
    </div>
    <script>
        (() => {
            const tree = @json($locationTree);
            const province = document.getElementById('newProvince');
            const district = document.getElementById('newDistrict');
            const selectedDistrict = @json((string) old('district_id'));
            const loadDistricts = () => {
                const row = tree.find(item => String(item.id) === String(province.value));
                const districts = row?.districts ?? [];
                district.innerHTML = '<option value="">Selecciona distrito</option>' + districts.map(item => `<option value="${item.id}" ${String(item.id) === selectedDistrict ? 'selected' : ''}>${item.name}</option>`).join('');
                district.disabled = districts.length === 0;
            };
            province.addEventListener('change', loadDistricts);
            if (province.value) loadDistricts();
        })();
    </script>
</x-app-layout>
