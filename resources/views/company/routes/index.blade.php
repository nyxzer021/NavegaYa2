@extends('layouts.company')
@section('title', 'Rutas')
@section('page-title', 'Rutas')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-2xl font-black text-slate-950">Rutas</h2>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">Elige un tramo oficial, indica la duración y, si hace falta, las escalas. El pasaje se vende siempre de origen a destino. El precio y las plazas por tramo intermedio quedan para una etapa posterior.</p>
    </section>

    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Registrar ruta</h3>
        <form method="POST" action="{{ route('company.routes.store') }}" class="mt-4 space-y-4">
            @csrf
            <label class="block text-sm font-semibold text-slate-700">Tramo oficial
                <select name="master_route_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="">Selecciona un tramo aprobado</option>
                    @foreach($officialRoutes as $official)
                        <option value="{{ $official->id }}" @selected(old('master_route_id') == $official->id)>{{ $official->origin_city }} → {{ $official->destination_city }} · {{ $official->formatted_duration }} · {{ $official->modality === 'aereo' ? 'Aéreo' : 'Fluvial' }}</option>
                    @endforeach
                </select>
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Horas<input required type="number" min="0" max="720" name="duration_hours" value="{{ old('duration_hours', 0) }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
                <label class="text-sm font-semibold text-slate-700">Minutos<input required type="number" min="0" max="59" name="duration_minutes" value="{{ old('duration_minutes', 0) }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-700">Escalas</p>
                <p class="mt-1 text-sm text-slate-500">Opcionales. El tiempo es desde la salida y debe quedar antes de la llegada.</p>
                <div class="mt-3 space-y-3">
                    @foreach(range(0, 2) as $index)
                        <div class="grid gap-3 sm:grid-cols-[1fr_8rem_8rem]">
                            <select name="stops[{{ $index }}][port_id]" class="h-10 rounded-lg border-slate-200 px-3 text-sm">
                                <option value="">Sin escala</option>
                                @foreach($ports as $port)
                                    <option value="{{ $port->id }}" @selected(old('stops.'.$index.'.port_id') == $port->id)>{{ $port->name }} · {{ $port->city }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0" max="720" name="stops[{{ $index }}][hours]" value="{{ old('stops.'.$index.'.hours') }}" placeholder="Horas" class="h-10 rounded-lg border-slate-200 px-3 text-sm">
                            <input type="number" min="0" max="59" name="stops[{{ $index }}][minutes]" value="{{ old('stops.'.$index.'.minutes') }}" placeholder="Minutos" class="h-10 rounded-lg border-slate-200 px-3 text-sm">
                        </div>
                    @endforeach
                </div>
            </div>
            <button class="inline-flex h-10 items-center rounded-lg bg-[#062c21] px-4 text-sm font-bold text-white">Registrar ruta</button>
        </form>
    </section>

    <section class="space-y-4">
        @forelse($riverRoutes->concat($airRoutes) as $route)
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-black text-slate-950">{{ $route instanceof \App\Models\AirRoute ? $route->origin_city : $route->originPort->city }} → {{ $route instanceof \App\Models\AirRoute ? $route->destination_city : $route->destinationPort->city }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $route->code }} · {{ \App\Models\MasterRoute::formatDuration((int) $route->estimated_duration_minutes) }} · {{ $route instanceof \App\Models\AirRoute ? 'Aéreo' : 'Fluvial' }}</p>
                <p class="mt-3 text-sm font-semibold text-slate-800">{{ \App\Support\RouteStops::label($route) ?: 'Sin escalas' }}</p>
            </article>
        @empty
            <p class="text-sm text-slate-500">Todavía no hay rutas registradas.</p>
        @endforelse
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Solicitar nuevo tramo</h3>
        <p class="mt-1 text-sm text-slate-500">Si el tramo no está en la lista, envíalo a la bandeja del superadmin.</p>
        <form method="POST" action="{{ route('company.routes.request') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700">Origen
                <select name="origin_port_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="">Selecciona</option>
                    @foreach($ports as $port)
                        <option value="{{ $port->id }}" @selected(old('origin_port_id') == $port->id)>{{ $port->name }} · {{ $port->city }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Destino
                <select name="destination_port_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="">Selecciona</option>
                    @foreach($ports as $port)
                        <option value="{{ $port->id }}" @selected(old('destination_port_id') == $port->id)>{{ $port->name }} · {{ $port->city }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Horas<input required type="number" min="0" max="720" name="duration_hours" value="{{ old('duration_hours', 0) }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Minutos<input required type="number" min="0" max="59" name="duration_minutes" value="{{ old('duration_minutes', 0) }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <div class="sm:col-span-2"><button class="inline-flex h-10 items-center rounded-lg bg-amber-400 px-4 text-sm font-bold text-[#062c21]">Solicitar nuevo tramo</button></div>
        </form>
        <div class="mt-6 space-y-3">
            @foreach($requests as $request)
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <strong class="text-sm text-slate-950">{{ $request->origin_city }} → {{ $request->destination_city }}</strong>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $request->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-700' }}">{{ $request->status === 'pending' ? 'Pendiente' : 'Rechazado' }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $request->formatted_duration }} · {{ $request->modality === 'aereo' ? 'Aéreo' : 'Fluvial' }}</p>
                    @if($request->rejection_reason)<p class="mt-2 text-sm text-rose-700">{{ $request->rejection_reason }}</p>@endif
                </article>
            @endforeach
        </div>
    </section>
</div>
@endsection
