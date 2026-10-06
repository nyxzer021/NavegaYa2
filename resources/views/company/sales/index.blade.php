@extends('layouts.company')
@section('title', 'Ventas y reportes')
@section('page-title', 'Ventas & Reportes')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl bg-gradient-to-r from-[#062c21] via-emerald-900 to-emerald-700 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-[.18em] text-emerald-300">Inteligencia comercial</span>
                <h2 class="mt-2 text-2xl font-black sm:text-3xl">Ventas e ingresos de {{ $organization->commercial_name ?: $organization->legal_name }}</h2>
                <p class="mt-2 text-xs text-emerald-100/75">Datos conciliados desde pagos confirmados y boletos emitidos.</p>
            </div>
            <form method="GET" class="grid gap-2 rounded-2xl border border-white/15 bg-white/10 p-3 sm:grid-cols-[1fr_1fr_auto]">
                <label><span class="block text-[9px] font-bold uppercase text-emerald-200">Desde</span><input type="date" name="from" value="{{ $from->toDateString() }}" class="mt-1 h-9 w-full rounded-lg border-white/20 bg-white text-xs font-semibold text-slate-800"></label>
                <label><span class="block text-[9px] font-bold uppercase text-emerald-200">Hasta</span><input type="date" name="to" value="{{ $to->toDateString() }}" class="mt-1 h-9 w-full rounded-lg border-white/20 bg-white text-xs font-semibold text-slate-800"></label>
                <button class="self-end rounded-lg bg-amber-500 px-5 py-2.5 text-xs font-black text-slate-950 hover:bg-amber-600">Filtrar</button>
            </form>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Recaudación total', 'S/ '.number_format($totalRevenue, 2), '💰', 'text-emerald-700'],
            ['Boletos emitidos', number_format($totalTickets), '🎫', 'text-slate-900'],
            ['Pasarela web', 'S/ '.number_format($webSales, 2), '🌐', 'text-sky-700'],
            ['Ventas counter', 'S/ '.number_format($counterSales, 2), '🧾', 'text-amber-700'],
        ] as [$label, $value, $icon, $color])
            <article class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</span><span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-50">{{ $icon }}</span></div>
                <strong class="mt-4 block text-2xl font-black {{ $color }}">{{ $value }}</strong>
                <span class="mt-1 block text-[10px] text-slate-400">{{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}</span>
            </article>
        @endforeach
    </section>

    <section class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Rendimiento diario</span><h3 class="text-lg font-black text-slate-900">Evolución de ingresos (S/)</h3></div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-800">{{ $dailySales->count() }} día(s) con ventas</span>
        </div>
        <div class="mt-5 h-[300px]"><canvas id="dailySalesChart" aria-label="Gráfico de ingresos diarios"></canvas></div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center">
            <div><span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Movimientos conciliados</span><h3 class="text-lg font-black text-slate-900">Detalle de boletos vendidos</h3></div>
            <a href="{{ route('company.sales.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-700 px-4 text-xs font-bold text-emerald-800 hover:bg-emerald-50">↓ Exportar Excel / CSV</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-slate-50 text-[9px] font-bold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3">N° boleto</th><th class="px-4 py-3">Fecha / hora</th><th class="px-4 py-3">Pasajero y DNI</th><th class="px-4 py-3">Tramo / salida</th><th class="px-4 py-3">Asiento</th><th class="px-4 py-3">Canal</th><th class="px-4 py-3 text-right">Monto</th><th class="px-5 py-3">Estado</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($tickets as $ticket)
                    @php($row = AppHttpControllersCompanyCompanySalesController::present($ticket))
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-black text-slate-900">{{ $ticket->code }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $ticket->created_at->format('d/m/Y') }}<span class="block text-[10px] text-slate-400">{{ $ticket->created_at->format('H:i') }} h</span></td>
                        <td class="px-4 py-4"><strong class="block text-slate-800">{{ $row['seat']->passenger_name }}</strong><span class="text-[10px] text-slate-400">{{ strtoupper($row['seat']->document_type ?? 'DNI') }} {{ $row['seat']->document_number }}</span></td>
                        <td class="px-4 py-4"><strong class="block text-slate-800">{{ $row['origin'] }} ➔ {{ $row['destination'] }}</strong><span class="text-[10px] text-slate-400">{{ $row['departure']?->departure_at?->format('d/m/Y · H:i') ?? '—' }}</span></td>
                        <td class="px-4 py-4 font-black text-slate-800">{{ $row['seat']->seat?->code ?? $row['seat']->aircraftSeat?->code ?? '—' }}</td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[9px] font-bold {{ $row['reservation']->sales_channel === 'counter' ? 'bg-amber-50 text-amber-800' : 'bg-sky-50 text-sky-800' }}">{{ $row['reservation']->sales_channel === 'counter' ? 'Counter' : 'Web' }}</span></td>
                        <td class="px-4 py-4 text-right font-black text-slate-900">S/ {{ number_format($row['amount'], 2) }}</td>
                        <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[9px] font-bold {{ in_array($ticket->status, ['confirmed','boarded'], true) ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-700' }}">{{ in_array($ticket->status, ['confirmed','boarded'], true) ? 'Pagado' : ucfirst($ticket->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-14 text-center text-sm text-slate-400">No existen ventas confirmadas en el periodo seleccionado.</td></tr>
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
            labels: @json($dailySales->keys()->map(fn ($date) => CarbonCarbon::parse($date)->format('d/m'))->values()),
            datasets: [{ label: 'Ingresos S/', data: @json($dailySales->values()->map(fn ($value) => (float) $value)->values()), backgroundColor: '#059669', borderColor: '#062c21', borderWidth: 1, borderRadius: 8, maxBarThickness: 42 }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: item => ' S/ ' + Number(item.raw).toFixed(2) } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: value => 'S/ ' + value } } } },
    });
});
</script>
@endsection
