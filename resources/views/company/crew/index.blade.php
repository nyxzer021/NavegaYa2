@extends('layouts.company')
@section('title', 'Tripulación')
@section('page-title', 'Tripulación')
@section('content')
@php
    $labels = \App\Support\CrewRoster::ROLE_LABELS;
    $today = now()->timezone('America/Lima')->toDateString();
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <p class="max-w-3xl text-sm text-slate-500">Registra a quien zarpa. Una licencia vencida se puede guardar, pero no se asigna a una salida futura. La misma persona no puede ir en dos salidas que se crucen. Publicar y vender no exige capitán; abrir el embarque, sí.</p>
    </section>
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>@endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Nueva persona</h3>
        <form method="POST" action="{{ route('company.crew.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700">Nombre<input required name="name" value="{{ old('name') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">DNI<input required name="document_number" value="{{ old('document_number') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Teléfono<input name="phone" value="{{ old('phone') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Cargo
                <select name="role" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $labels[$role] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Licencia<input name="license_number" value="{{ old('license_number') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <label class="text-sm font-semibold text-slate-700">Vencimiento<input type="date" name="license_expires_on" value="{{ old('license_expires_on') }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm"></label>
            <div class="sm:col-span-2"><button class="inline-flex h-10 items-center rounded-lg bg-amber-400 px-4 text-sm font-black text-emerald-950">Guardar persona</button></div>
        </form>
        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="py-2">Nombre</th><th>Cargo</th>@if($seesContact)<th>DNI</th><th>Teléfono</th>@endif<th>Licencia</th><th></th></tr></thead>
                <tbody>
                @forelse($members as $member)
                    @php
                        $expires = $member->license_expires_on?->toDateString();
                        $expired = $expires !== null && $expires < $today;
                        $soon = $expires !== null && ! $expired && $expires <= now()->timezone('America/Lima')->addDays(30)->toDateString();
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="py-3 font-semibold">{{ $member->name }}</td>
                        <td>{{ $labels[$member->role] ?? $member->role }}</td>
                        @if($seesContact)
                            <td>{{ $member->document_number }}</td>
                            <td>{{ $member->phone ?: '—' }}</td>
                        @endif
                        <td>
                            {{ $member->license_number ?: 'Sin número' }}
                            @if($expired)<span class="ml-1 font-bold text-rose-700">Licencia vencida</span>
                            @elseif($soon)<span class="ml-1 font-bold text-amber-700">Vence pronto</span>
                            @elseif($expires)<span class="ml-1 text-slate-500">hasta {{ $member->license_expires_on->format('d/m/Y') }}</span>
                            @else<span class="ml-1 text-slate-500">sin fecha</span>@endif
                        </td>
                        <td class="text-right"><form method="POST" action="{{ route('company.crew.destroy', $member) }}">@csrf @method('DELETE')<button class="text-sm font-bold text-rose-700">Retirar</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-slate-500">Todavía no hay tripulación.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-lg font-black text-slate-950">Asignar a una salida</h3>
        <form method="POST" action="{{ route('company.crew.assign') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Salida
                <select name="departure_id" required class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="">Selecciona una salida</option>
                    @foreach($departures as $departure)
                        <option value="{{ $departure->id }}">{{ $departure->departure_at->copy()->timezone('America/Lima')->format('d/m/Y H:i') }} · {{ $departure->transportRoute->originPort?->city }} → {{ $departure->transportRoute->destinationPort?->city }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Capitán
                <select name="captain_id" class="mt-1 h-10 w-full rounded-lg border-slate-200 px-3 text-sm">
                    <option value="">Sin capitán</option>
                    @foreach($members->where('role', 'captain') as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">Resto de la tripulación
                <select name="crew_ids[]" multiple class="mt-1 min-h-24 w-full rounded-lg border-slate-200 px-3 py-2 text-sm">
                    @foreach($members->where('role', '!=', 'captain') as $member)
                        <option value="{{ $member->id }}">{{ $member->name }} · {{ $labels[$member->role] ?? $member->role }}</option>
                    @endforeach
                </select>
            </label>
            <div class="sm:col-span-2"><button class="inline-flex h-10 items-center rounded-lg bg-amber-400 px-4 text-sm font-black text-emerald-950">Asignar</button></div>
        </form>
    </section>
</div>
@endsection
