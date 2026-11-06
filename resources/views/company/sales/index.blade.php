@extends('layouts.company')
@section('title', 'Historial de ventas')
@section('page-title', 'Historial de ventas')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <h2 class="text-2xl font-black text-slate-950">Boletos vendidos de {{ $organization->commercial_name ?: $organization->legal_name }}</h2>
                <p class="mt-2 text-sm text-slate-500">Lista de pasajes confirmados. La comisión del convenio está en su propia pantalla.</p>
            </div>
            <form method="GET" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                <label><span class="block text-xs font-semibold text-slate-500">Desde</span><input type="date" name="from" value="{{ $from->toDateString() }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-slate-800"></label>
                <label><span class="block text-xs font-semibold text-slate-500">Hasta</span><input type="date" name="to" value="{{ $to->toDateString() }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-slate-800"></label>
                <button class="self-end rounded-lg bg-[#062c21] px-5 py-2.5 text-sm font-bold text-white">Filtrar</button>
            </form>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Total pagado por viajeros', 'S/ '.number_format($totalRevenue, 2)],
            ['Boletos emitidos', number_format($totalTickets)],
            ['Pasarela web', 'S/ '.number_format($webSales, 2)],
            ['Ventas counter', 'S/ '.number_format($counterSales, 2)],
        ] as [$label, $value])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-sm font-semibold text-slate-500">{{ $label }}</span>
                <strong class="mt-2 block text-2xl font-black text-slate-950">{{ $value }}</strong>
                <span class="mt-1 block text-sm text-slate-500">{{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}</span>
            </article>
        @endforeach
    </section>

    <section class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div><h3 class="text-lg font-black text-slate-950">Evolución de ingresos (S/)</h3></div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">{{ \App\Support\SpanishCount::phrase($dailySales->count(), 'día con ventas', 'días con ventas') }}</span>
        </div>
        <div class="mt-5 h-[300px]"><canvas id="dailySalesChart" aria-label="Gráfico de ingresos diarios"></canvas></div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center">
            <div><h3 class="text-lg font-black text-slate-950">Detalle de boletos vendidos</h3></div>
            <a href="{{ route('company.sales.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-800 px-4 text-sm font-bold text-emerald-900 hover:bg-emerald-50">Exportar CSV</a>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($tickets as $ticket)
                @php($row = \App\Http\Controllers\Company\CompanySalesController::present($ticket))
                <article class="space-y-1 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <strong class="text-slate-950">{{ $row['seat']->passenger_name }}</strong>
                        <span class="font-black text-slate-950">S/ {{ number_format($row['amount'], 2) }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $row['origin'] }} → {{ $row['destination'] }} · asiento {{ $row['seat']->seat?->code ?? $row['seat']->aircraftSeat?->code ?? '—' }}</p>
                    <p class="text-sm text-slate-500">{{ $ticket->code }} · {{ $ticket->created_at->format('d/m/Y H:i') }} · {{ $row['reservation']->sales_channel === 'counter' ? 'Muelle' : 'En línea' }}</p>
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ in_array($ticket->status, ['confirmed','boarded'], true) ? 'Pagado' : ucfirst($ticket->status) }}</span>
                </article>
            @empty
                <div class="px-5 py-14 text-center">
                    <strong class="block text-sm text-slate-700">Aún no hay ventas en este periodo</strong>
                    <span class="mt-1 block text-sm text-slate-500">Las compras web y las ventas realizadas en muelle aparecerán aquí cuando el pago quede confirmado.</span>
                    <a href="{{ route('company.pos.index') }}" class="mt-4 inline-flex rounded-xl bg-amber-400 px-4 py-2.5 text-sm font-black text-emerald-950">Registrar venta en muelle</a>
                </div>
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr><th class="px-5 py-3">N° boleto</th><th class="px-4 py-3">Fecha / hora</th><th class="px-4 py-3">Pasajero y DNI</th><th class="px-4 py-3">Tramo / salida</th><th class="px-4 py-3">Asiento</th><th class="px-4 py-3">Canal</th><th class="px-4 py-3 text-right">Monto</th><th class="px-5 py-3">Estado</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($tickets as $ticket)
                    @php($row = \App\Http\Controllers\Company\CompanySalesController::present($ticket))
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-black text-slate-950">{{ $ticket->code }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $ticket->created_at->format('d/m/Y') }}<span class="block text-xs text-slate-500">{{ $ticket->created_at->format('H:i') }} h</span></td>
                        <td class="px-4 py-4"><strong class="block text-slate-800">{{ $row['seat']->passenger_name }}</strong><span class="text-xs text-slate-500">{{ strtoupper($row['seat']->document_type ?? 'DNI') }} {{ $row['seat']->document_number }}</span></td>
                        <td class="px-4 py-4"><strong class="block text-slate-800">{{ $row['origin'] }} → {{ $row['destination'] }}</strong><span class="text-xs text-slate-500">{{ $row['departure']?->departure_at?->format('d/m/Y · H:i') ?? '—' }}</span></td>
                        <td class="px-4 py-4 font-black text-slate-800">{{ $row['seat']->seat?->code ?? $row['seat']->aircraftSeat?->code ?? '—' }}</td>
                        <td class="px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $row['reservation']->sales_channel === 'counter' ? 'Muelle' : 'En línea' }}</span></td>
                        <td class="px-4 py-4 text-right font-black text-slate-950">S/ {{ number_format($row['amount'], 2) }}</td>
                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ in_array($ticket->status, ['confirmed','boarded'], true) ? 'Pagado' : ucfirst($ticket->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-14 text-center"><strong class="block text-sm text-slate-700">Aún no hay ventas en este periodo</strong><span class="mt-1 block text-sm text-slate-500">Las compras web y las ventas realizadas en muelle aparecerán aquí cuando el pago quede confirmado.</span><a href="{{ route('company.pos.index') }}" class="mt-4 inline-flex rounded-xl bg-amber-400 px-4 py-2.5 text-sm font-black text-emerald-950">Registrar venta en muelle</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $tickets->links() }}</div>@endif
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const context = document.getElementById('dailySalesChart');
    if (!context || typeof Chart === 'undefined') return;
    new Chart(context, {
        type: 'bar',
        data: {
            labels: @json($dailySales->keys()->map(fn ($date) => \Carbon\Carbon::parse($date)->format('d/m'))->values()),
            datasets: [{ label: 'Ingresos S/', data: @json($dailySales->values()->map(fn ($value) => (float) $value)->values()), backgroundColor: '#059669', borderColor: '#062c21', borderWidth: 1, borderRadius: 8, maxBarThickness: 42 }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: item => ' S/ ' + Number(item.raw).toFixed(2) } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: value => 'S/ ' + value } } } },
    });
});
</script>
@endsection
