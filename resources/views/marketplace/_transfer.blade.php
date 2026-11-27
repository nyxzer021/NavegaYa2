@php
    $pendingRequest = $paymentRequests->firstWhere('status', 'pending');
    $lastRejected = $paymentRequests->first(fn ($item) => $item->status === 'rejected');
    $monthlyPrice = (float) ($subscription?->monthly_price ?? 0);
    $requestLabels = ['pending' => ['En revisión', 'var(--ny-tone-sun)'], 'approved' => ['Aprobado', 'var(--ny-tone-leaf)'], 'rejected' => ['Rechazado', 'var(--ny-tone-coral)']];
@endphp
<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="mk-transfer-title">
    <div class="flex flex-wrap items-start gap-4">
        <span class="mk-kpi-icon" style="--c: var(--ny-tone-violet)"><x-ui.icon name="wallet" /></span>
        <div class="min-w-0 flex-1">
            <h2 id="mk-transfer-title" class="text-xl font-black text-slate-950">Pagar por transferencia, Yape o Plin</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Paga desde tu banco o billetera y sube el comprobante. NavegaYA lo revisa y activa tu plan.</p>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm leading-6 text-violet-950">
        <strong class="block">Datos para el pago</strong>
        @if(filled($paymentInstructions))
            <p class="mt-1 whitespace-pre-line">{{ $paymentInstructions }}</p>
        @else
            <p class="mt-1">NavegaYA te enviará los datos de pago por <a href="{{ route('messages.index') }}" class="font-black underline">Mensajes</a>. Escríbenos si aún no los tienes.</p>
        @endif
    </div>

    @if($pendingRequest)
        <div class="mt-5 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">
            <strong>Tu comprobante está en revisión.</strong>
            {{ $pendingRequest->methodLabel() }} · operación {{ $pendingRequest->operation_number }} · S/ {{ number_format((float) $pendingRequest->amount, 2) }} por {{ $pendingRequest->months }} {{ $pendingRequest->months === 1 ? 'mes' : 'meses' }}.
            Te avisaremos cuando se active tu plan.
        </div>
    @else
        @if($lastRejected)
            <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="status">
                <strong>NavegaYA rechazó tu último comprobante.</strong> {{ $lastRejected->rejection_reason }}
            </div>
        @endif
        <form method="POST" action="{{ route('marketplace.subscription.transfer') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2" x-data="{ months: {{ (int) old('months', 1) }} }">
            @csrf
            <label class="text-sm font-bold text-slate-800">Cómo pagaste
                <select name="method" required class="mt-1 min-h-11 w-full rounded-xl border-slate-200">
                    @foreach(\App\Models\SubscriptionPaymentRequest::METHODS as $value => $label)
                        <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-bold text-slate-800">Meses que pagas
                <select name="months" x-model.number="months" required class="mt-1 min-h-11 w-full rounded-xl border-slate-200">
                    @foreach([1, 3, 6, 12] as $option)
                        <option value="{{ $option }}">{{ $option }} {{ $option === 1 ? 'mes' : 'meses' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-bold text-slate-800">Número de operación
                <input name="operation_number" value="{{ old('operation_number') }}" required maxlength="80" class="mt-1 min-h-11 w-full rounded-xl border-slate-200">
            </label>
            <label class="text-sm font-bold text-slate-800">Fecha del pago
                <input type="date" name="paid_on" value="{{ old('paid_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="mt-1 min-h-11 w-full rounded-xl border-slate-200">
            </label>
            <label class="text-sm font-bold text-slate-800 md:col-span-2">Comprobante (foto o PDF, hasta 5 MB)
                <input type="file" name="voucher" accept="image/jpeg,image/png,image/webp,application/pdf" required class="mt-1 block w-full text-sm">
            </label>
            <div class="flex flex-wrap items-center justify-between gap-3 md:col-span-2">
                <p class="text-sm text-slate-700">Monto a pagar: <strong class="text-lg text-slate-950">S/ <span x-text="({{ $monthlyPrice }} * months).toFixed(2)">{{ number_format($monthlyPrice, 2) }}</span></strong></p>
                <button type="submit" class="min-h-11 rounded-xl bg-emerald-800 px-5 text-sm font-black text-white hover:bg-emerald-900">Enviar comprobante</button>
            </div>
        </form>
    @endif

    @if($paymentRequests->isNotEmpty())
        <div class="mt-6">
            <h3 class="text-sm font-black text-slate-900">Tus comprobantes</h3>
            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                @foreach($paymentRequests as $item)
                    @php([$label, $tone] = $requestLabels[$item->status] ?? [$item->status, '#8a9a94'])
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span class="text-slate-700">{{ $item->created_at->timezone(\App\Support\DepartureSchedule::ZONE)->format('d/m/Y') }} · {{ $item->methodLabel() }} · S/ {{ number_format((float) $item->amount, 2) }}</span>
                        <span class="rounded-full px-3 py-1 text-xs font-black" style="background: color-mix(in srgb, {{ $tone }} 18%, white); color: {{ $tone }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
