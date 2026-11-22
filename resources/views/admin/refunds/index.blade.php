@extends('layouts.admin')

@section('content')
<div class="space-y-5">
    <section class="rounded-2xl bg-gradient-to-r from-slate-950 to-emerald-950 p-6 text-white"><p class="text-[10px] font-bold uppercase tracking-[.18em] text-amber-300">Finanzas y soporte</p><h1 class="mt-1 text-2xl font-black">Reembolsos e incidencias de pago</h1><p class="mt-1 text-xs text-emerald-100/70">Seguimiento comercial de devoluciones, pagos fallidos y solicitudes del cliente.</p></section>
    <section class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase text-slate-400">Pendientes</p><p class="mt-2 text-2xl font-black text-amber-700">{{ $pendingCount }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase text-slate-400">Devuelto</p><p class="mt-2 text-2xl font-black text-emerald-700">S/ {{ number_format($refundedTotal, 2) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-[10px] font-bold uppercase text-slate-400">Pagos fallidos</p><p class="mt-2 text-2xl font-black text-rose-700">{{ $failedCount }}</p></div>
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Referencia</th><th class="px-4 py-3">Cliente / Reserva</th><th class="px-4 py-3">Proveedor</th><th class="px-4 py-3">Monto</th><th class="px-4 py-3">Estado</th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($refunds as $refund)<tr><td class="px-4 py-3 font-mono font-bold">{{ $refund->provider_reference }}</td><td class="px-4 py-3"><p class="font-bold">{{ $refund->reservation?->contact_name ?? 'Cliente' }}</p><p class="text-[10px] text-slate-500">{{ $refund->reservation?->code ?? 'Sin reserva' }}</p></td><td class="px-4 py-3">{{ strtoupper($refund->provider) }}</td><td class="px-4 py-3 font-black">S/ {{ number_format((float)$refund->amount, 2) }}</td><td class="px-4 py-3"><span class="rounded-full bg-amber-50 px-2 py-1 text-[9px] font-bold text-amber-700">{{ \App\Support\PanelLabel::status($refund->status) }}</span></td></tr>
        @empty<tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">No hay incidencias ni solicitudes de reembolso.</td></tr>@endforelse
    </tbody></table></div>@if($refunds->hasPages())<div class="border-t border-slate-100 p-4">{{ $refunds->links() }}</div>@endif</section>
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-base font-black">{{ __('refunds.admin_title') }}</h2>
        <div class="mt-3 space-y-2">
            @forelse($ticketRefunds as $ticketRefund)
                <article class="grid gap-2 border-b border-slate-100 py-3 text-xs sm:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <p class="text-sm font-bold">{{ $ticketRefund->organization?->commercial_name ?: $ticketRefund->organization?->legal_name }} · {{ $ticketRefund->reservation?->code }}</p>
                        <p class="mt-1 text-slate-600">{{ $ticketRefund->explanation }}</p>
                    </div>
                    <p class="text-right text-sm font-black">S/ {{ number_format((float) $ticketRefund->amount, 2, '.', '') }}<span class="mt-1 block text-xs font-bold text-slate-500">{{ $ticketRefund->status === 'approved' && $ticketRefund->reservation?->sales_channel === 'web' ? __('refunds.status_approved_web') : __('refunds.status_'.$ticketRefund->status) }}</span></p>
                </article>
            @empty
                <p class="py-8 text-center text-xs text-slate-500">{{ __('refunds.admin_empty') }}</p>
            @endforelse
        </div>
        @if($ticketRefunds->hasPages())<div class="border-t border-slate-100 pt-4">{{ $ticketRefunds->links() }}</div>@endif
    </section>
</div>
@endsection
