@php
    $canBoard = $row['departure_at']?->isToday() && in_array($row['status'], ['scheduled', 'boarding', 'delayed', 'rescheduled'], true);
@endphp
<div class="flex flex-wrap justify-end gap-2">
    @if($canBoard && ($row['is_air'] || ($row['can_board'] ?? false)))
        <a href="{{ $row['is_air'] ? route('company.counter.index', ['air_departure' => $row['model']->id]) : route('company.boarding.show', $row['model']) }}" class="inline-flex h-10 items-center rounded-xl bg-[#062c21] px-3 text-sm font-bold text-white">Embarque</a>
    @elseif($canBoard && ! $row['is_air'])
        <span class="inline-flex h-10 items-center text-sm font-bold text-amber-800">Asigna un capitán con licencia vigente</span>
    @elseif(! $row['is_air'])
        <a href="{{ route('company.departures.manifest', $row['model']) }}" class="inline-flex h-10 items-center rounded-xl border border-[#d5e6df] bg-white px-3 text-sm font-bold text-slate-700">Manifiesto</a>
    @endif
    @unless($isCounter || $row['is_air'])
        <details class="relative">
            <summary class="inline-flex h-10 cursor-pointer list-none items-center rounded-xl border border-[#d5e6df] bg-white px-3 text-sm font-bold text-slate-700">Más</summary>
            <div class="absolute right-0 z-20 mt-1 w-40 rounded-xl border border-[#d5e6df] bg-white p-1 text-left shadow-lg">
                <a href="{{ route('company.departures.edit', $row['model']) }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-800">Editar</a>
                @if($canBoard)
                    <a href="{{ route('company.departures.manifest', $row['model']) }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-800">Manifiesto</a>
                @endif
                <button type="button" onclick="document.getElementById('contingency-{{ $row['model']->id }}').showModal()" class="block w-full rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-800">Gestionar</button>
            </div>
        </details>
    @endunless
</div>
