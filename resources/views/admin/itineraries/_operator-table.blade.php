<div class="overflow-x-auto">
    <table class="w-full min-w-[1040px] text-left text-xs">
        <thead class="border-y border-slate-200 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">{{ $operatorHeading }}</th>
                <th class="px-4 py-3">{{ $routesHeading }}</th>
                <th class="px-4 py-3 text-center">{{ $departuresHeading }}</th>
                <th class="px-4 py-3">Asientos en web</th>
                <th class="px-4 py-3 text-right">Recaudación hoy</th>
                <th class="px-4 py-3 text-right">Comisión NavegaYA (8%)</th>
                <th class="px-4 py-3 text-center">Estado & control web</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($sectionOperators as $operator)
            @php($occupancy = $operator->capacity > 0 ? min(100, round(($operator->sold_seats / $operator->capacity) * 100)) : 0)
            <tr class="align-middle hover:bg-slate-50/70">
                <td class="px-4 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $isAirSection ? 'bg-sky-50 text-sky-800' : 'bg-emerald-50 text-emerald-800' }} font-black">{{ mb_strtoupper(mb_substr($operator->name, 0, 1)) }}</span>
                        <div><strong class="block max-w-56 truncate text-sm text-slate-900">{{ $operator->name }}</strong><span class="block max-w-56 truncate text-[10px] text-slate-500">{{ $operator->legal_name }}</span><span class="text-[10px] text-slate-400">RUC {{ $operator->ruc ?: '—' }}</span></div>
                    </div>
                </td>
                <td class="px-4 py-4"><span class="block max-w-56 text-slate-700">{{ $operator->destinations }}</span></td>
                <td class="px-4 py-4 text-center"><strong class="text-lg text-slate-900">{{ $operator->departures_count }}</strong><span class="block text-[10px] text-slate-400">{{ $operator->published_departures_count }} publicadas</span></td>
                <td class="px-4 py-4"><div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-800">{{ $operator->sold_seats }}/{{ $operator->capacity }}</span><span class="text-[10px] text-slate-400">{{ $occupancy }}%</span></div><div class="mt-1.5 h-1.5 w-32 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $isAirSection ? 'bg-sky-500' : 'bg-emerald-600' }}" style="width:{{ $occupancy }}%"></div></div><span class="mt-1 block text-[10px] text-slate-400">{{ max(0, $operator->capacity - $operator->sold_seats) }} disponibles</span></td>
                <td class="px-4 py-4 text-right font-black text-slate-900">S/ {{ number_format($operator->gmv, 2) }}</td>
                <td class="px-4 py-4 text-right"><strong class="block text-emerald-800">S/ {{ number_format($operator->commission, 2) }}</strong><span class="text-[10px] text-slate-400">{{ number_format($operator->commission_rate, 2) }}%</span></td>
                <td class="px-4 py-4 text-center">
                    <form method="POST" action="{{ route('admin.itineraries.operator-sales', $operator->id) }}">@csrf @method('PATCH')
                        @if($operator->is_paused)
                            <button class="inline-flex h-8 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-[10px] font-bold text-emerald-800 transition hover:bg-emerald-100">🟢 Reactivar ventas</button>
                        @else
                            <button class="inline-flex h-8 items-center rounded-lg border border-amber-200 bg-amber-50 px-3 text-[10px] font-bold text-amber-900 transition hover:bg-amber-100">⏸️ Pausar ventas</button>
                        @endif
                    </form>
                    <span class="mt-1.5 block text-[9px] font-semibold {{ $operator->is_paused ? 'text-rose-600' : 'text-emerald-600' }}">{{ $operator->is_paused ? 'Ventas pausadas' : '● En venta' }}</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">{{ $emptyMessage }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
