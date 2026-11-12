<form method="POST" action="{{ $action }}" class="grid gap-3 border-t border-slate-100 p-5 sm:grid-cols-2">
    @csrf
    <p class="text-sm font-black text-slate-900 sm:col-span-2">{{ __('company_portal.fleet.maintenance', [], 'es') }}</p>
    <label class="text-xs font-bold text-slate-700">Desde<input type="date" name="starts_on" required value="{{ old('starts_on') }}" class="mt-1 h-10 w-full rounded-xl border-slate-200 text-sm"></label>
    <label class="text-xs font-bold text-slate-700">Hasta<input type="date" name="ends_on" required value="{{ old('ends_on') }}" class="mt-1 h-10 w-full rounded-xl border-slate-200 text-sm"></label>
    <label class="text-xs font-bold text-slate-700">Tipo
        <select name="kind" required class="mt-1 h-10 w-full rounded-xl border-slate-200 text-sm">
            <option value="maintenance">{{ __('company_portal.fleet.maintenance', [], 'es') }}</option>
            <option value="repair">{{ __('company_portal.fleet.repair', [], 'es') }}</option>
        </select>
    </label>
    <label class="text-xs font-bold text-slate-700">Nota<input name="notes" value="{{ old('notes') }}" class="mt-1 h-10 w-full rounded-xl border-slate-200 text-sm"></label>
    @if((int) session('maintenance_unit_id') === (int) $unit->id && session('maintenance_conflicts'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 sm:col-span-2">
            <strong>{{ __('company_portal.fleet.affected', [], 'es') }}</strong>
            @foreach(session('maintenance_conflicts') as $conflict)
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <p class="font-bold sm:col-span-2">{{ $conflict['label'] }}</p>
                    <label>{{ __('company_portal.fleet.reschedule', [], 'es') }}
                        <input type="datetime-local" name="resolutions[{{ $conflict['id'] }}][departure_at]" class="mt-1 h-10 w-full rounded-xl border-slate-200 text-sm">
                    </label>
                    <label>{{ __('company_portal.fleet.change_unit', [], 'es') }}
                        <input type="number" name="resolutions[{{ $conflict['id'] }}][replacement_id]" class="mt-1 h-10 w-full rounded-xl border-slate-200 text-sm" placeholder="ID">
                    </label>
                    <label class="sm:col-span-2">{{ __('company_portal.fleet.reason', [], 'es') }}
                        <input name="resolutions[{{ $conflict['id'] }}][reason]" class="mt-1 h-10 w-full rounded-xl border-slate-200 text-sm">
                    </label>
                    <select name="resolutions[{{ $conflict['id'] }}][action]" class="h-10 rounded-xl border-slate-200 text-sm">
                        <option value="reschedule">{{ __('company_portal.fleet.reschedule', [], 'es') }}</option>
                        <option value="change_unit">{{ __('company_portal.fleet.change_unit', [], 'es') }}</option>
                    </select>
                </div>
            @endforeach
        </div>
    @endif
    <button class="h-10 rounded-xl bg-[#062c21] px-4 text-sm font-bold text-white sm:col-span-2">Guardar mantenimiento</button>
</form>
