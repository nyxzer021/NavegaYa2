@extends('layouts.company')
@section('title', __('company_portal.menu.branches'))
@section('page-title', __('company_portal.menu.branches'))
@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    @if(session('success'))<x-ui.card><p class="text-sm font-semibold text-emerald-800">{{ session('success') }}</p></x-ui.card>@endif
    @if($errors->any())<x-ui.card><p class="text-sm font-semibold text-rose-800">{{ $errors->first() }}</p></x-ui.card>@endif
    <x-ui.card>
        <h2 class="text-base font-semibold">Sedes registradas</h2>
        <ul class="mt-3 space-y-3">
            @forelse($sites as $site)
                <li class="rounded-lg border border-[#E6ECE9] p-3">
                    <form method="POST" action="{{ route('company.sites.update', $site) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <x-ui.input name="name" label="Nombre" value="{{ $site->name }}" required />
                        <label class="ny-ui-field"><span>Tipo</span>
                            <select name="kind" required>
                                @foreach(['dock' => 'Muelle', 'office' => 'Oficina', 'airfield' => 'Aeródromo'] as $value => $label)
                                    <option value="{{ $value }}" @selected($site->kind === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="ny-ui-field sm:col-span-2"><span>Puerto o aeródromo del catálogo</span>
                            <select name="port_id" required>
                                <option value="">Elige un puerto activo</option>
                                @foreach($ports as $port)
                                    <option value="{{ $port->id }}" @selected((string) old('port_id', $site->port_id) === (string) $port->id)>{{ $port->name }} · {{ $port->city }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-ui.input name="address" label="Dirección" value="{{ $site->address }}" required />
                        <x-ui.input name="city" label="Ciudad" value="{{ $site->city }}" required />
                        <x-ui.input name="hours" label="Horario" value="{{ $site->hours }}" />
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="receives_cargo" value="1" @checked($site->receives_cargo)> Recibe carga</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_on_where_to_buy" value="1" @checked($site->show_on_where_to_buy)> Visible en Dónde comprar</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($site->is_active)> Activa</label>
                        @if($site->hidden_by_admin)<p class="text-sm text-rose-800 sm:col-span-2">NavegaYA ocultó esta sede. {{ $site->hidden_reason }}</p>@endif
                        <div class="flex gap-2 sm:col-span-2">
                            @unless($site->hidden_by_admin)<x-ui.button type="submit" variant="primary">Guardar</x-ui.button>@endunless
                        </div>
                    </form>
                    <form method="POST" action="{{ route('company.sites.destroy', $site) }}" class="mt-2">@csrf @method('DELETE')<x-ui.button type="submit">Eliminar</x-ui.button></form>
                </li>
            @empty
                <li class="text-sm text-[#607069]">Todavía no hay sedes.</li>
            @endforelse
        </ul>
    </x-ui.card>
    <x-ui.card>
        <h2 class="text-base font-semibold">Nueva sede</h2>
        <form method="POST" action="{{ route('company.sites.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
            @csrf
            <x-ui.input name="name" label="Nombre" required />
            <label class="ny-ui-field"><span>Tipo</span>
                <select name="kind" required>
                    <option value="dock">Muelle</option>
                    <option value="office">Oficina</option>
                    <option value="airfield">Aeródromo</option>
                </select>
            </label>
            <label class="ny-ui-field sm:col-span-2"><span>Puerto o aeródromo del catálogo</span>
                <select name="port_id" required>
                    <option value="">Elige un puerto activo</option>
                    @foreach($ports as $port)
                        <option value="{{ $port->id }}" @selected((string) old('port_id') === (string) $port->id)>{{ $port->name }} · {{ $port->city }}</option>
                    @endforeach
                </select>
            </label>
            <x-ui.input name="address" label="Dirección" required />
            <x-ui.input name="city" label="Ciudad" required />
            <x-ui.input name="hours" label="Horario" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="receives_cargo" value="1"> Recibe carga</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_on_where_to_buy" value="1" checked> Visible en Dónde comprar</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Activa</label>
            <div class="sm:col-span-2"><x-ui.button type="submit" variant="primary">Registrar sede</x-ui.button></div>
        </form>
    </x-ui.card>
</div>
@endsection
