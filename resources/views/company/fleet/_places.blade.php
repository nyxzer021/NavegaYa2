<section class="mt-6 border-t border-slate-100 pt-4">
    <h3 class="text-lg font-black text-slate-950">Hamacas y camarotes</h3>
    <p class="mt-1 text-sm text-slate-500">Una hamaca es una plaza. Un camarote vendido completo cuenta todas sus camas para la capacidad y los chalecos.</p>
    @if($errors->any())<div class="mt-3 text-sm text-rose-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($places->isNotEmpty())
        <ul class="mt-3 space-y-1 text-sm text-slate-700">
            @foreach($places as $place)
                <li>{{ $place->code }} · {{ $place->space_type === 'hammock' ? 'Hamaca' : 'Camarote de '.$place->bed_count.' '.($place->bed_count === 1 ? 'cama' : 'camas') }}</li>
            @endforeach
        </ul>
    @endif
    @if(! $planLocked)
        <form method="POST" action="{{ route($seatRoutePrefix.'.vessels.hammocks.store', $vessel) }}" class="mt-4 grid gap-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700">Cantidad de hamacas
                <input type="number" name="quantity" min="1" max="500" value="{{ old('quantity', 1) }}" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold">
            </label>
            <button class="h-11 rounded-xl border border-slate-200 text-sm font-black text-slate-800">Crear hamacas H-01…</button>
        </form>
        <form method="POST" action="{{ route($seatRoutePrefix.'.vessels.cabins.store', $vessel) }}" class="mt-3 grid gap-2">
            @csrf
            <label class="text-sm font-semibold text-slate-700">Código del camarote
                <input name="cabin_code" value="{{ old('cabin_code') }}" required maxlength="40" placeholder="C-01" class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold">
            </label>
            <label class="text-sm font-semibold text-slate-700">Camas
                <input type="number" name="bed_count" min="1" max="100" value="{{ old('bed_count', 4) }}" required class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold">
            </label>
            <button class="h-11 rounded-xl bg-[#062c21] text-sm font-black text-white">Agregar camarote</button>
        </form>
    @endif
</section>
