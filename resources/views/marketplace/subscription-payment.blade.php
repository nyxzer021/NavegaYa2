<x-marketplace-layout title="Pagar suscripción · NavegaYA">
<main class="min-h-[calc(100vh-64px)] bg-slate-50 px-4 py-10">
    <section class="mx-auto max-w-3xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
        <header class="bg-gradient-to-r from-emerald-950 to-emerald-800 p-7 text-white">
            <p class="text-[10px] font-black uppercase tracking-[.2em] text-amber-300">Pago seguro</p>
            <h1 class="mt-2 text-3xl font-black">Activa tu plan {{ $subscription->plan?->name }}</h1>
            <p class="mt-2 text-sm text-emerald-100/70">Cuando Culqi confirme el pago, NavegaYA habilitará el plan inmediatamente.</p>
        </header>
        <div class="p-7">
            <div class="grid gap-3 rounded-2xl bg-slate-50 p-5 sm:grid-cols-2">
                <div><span class="text-[10px] font-black uppercase text-slate-400">Empresa</span><strong class="mt-1 block">{{ $organization->commercial_name ?: $organization->legal_name }}</strong></div>
                <div><span class="text-[10px] font-black uppercase text-slate-400">Total mensual</span><strong class="mt-1 block text-2xl">S/ {{ number_format((float) $subscription->monthly_price, 2) }}</strong></div>
            </div>

            @if ($subscription->status === 'active' && $subscription->ends_on?->isFuture())
                <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm font-bold text-emerald-800">Tu plan ya está activo hasta el {{ $subscription->ends_on->format('d/m/Y') }}.</div>
            @elseif ($sandboxEnabled)
                <form method="POST" action="{{ route('marketplace.subscription.sandbox-confirm') }}" class="mt-6">@csrf
                    <p class="text-sm font-black">Método de prueba</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">@foreach(['yape'=>'Yape','plin'=>'Plin','card'=>'Tarjeta'] as $value=>$label)<label><input class="peer sr-only" type="radio" name="method" value="{{ $value }}" @checked($loop->first)><span class="block cursor-pointer rounded-xl border-2 p-4 text-center text-xs font-black peer-checked:border-emerald-700 peer-checked:bg-emerald-50">{{ $label }}</span></label>@endforeach</div>
                    <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Modo de prueba: no se realizará ningún cobro.</p>
                    <button class="mt-5 h-12 w-full rounded-xl bg-amber-400 text-sm font-black">Confirmar pago de prueba</button>
                </form>
            @elseif ($culqiEnabled)
                <div class="mt-6"><div id="culqiError" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-700"></div><button id="culqiPayButton" class="mt-3 h-12 w-full rounded-xl bg-amber-400 text-sm font-black">Pagar con tarjeta o Yape →</button><button id="pagoEfectivoButton" class="mt-3 h-12 w-full rounded-xl border border-emerald-800 text-sm font-black text-emerald-800">Generar código PagoEfectivo</button><div id="pagoEfectivoResult" class="mt-3 hidden rounded-xl bg-emerald-50 p-4 text-center"><span class="text-xs text-emerald-700">Código CIP</span><strong id="paymentCode" class="block text-2xl"></strong><p class="mt-1 text-xs text-slate-500">El plan se activará automáticamente cuando se confirme el pago.</p></div></div>
            @else
                <p class="mt-6 rounded-xl bg-sky-50 p-4 text-sm text-sky-800">El cobro en línea todavía no está habilitado.</p>
            @endif
            <a href="{{ route('marketplace.dashboard', ['tab'=>'plan']) }}" class="mt-5 inline-flex text-xs font-black text-emerald-800">← Volver a Plan y facturación</a>
        </div>
    </section>
</main>
@if ($subscription->status !== 'active' && $culqiEnabled && ! $sandboxEnabled)
<script src="https://js.culqi.com/checkout-js"></script><script>
document.addEventListener('DOMContentLoaded',()=>{const button=document.getElementById('culqiPayButton'),cashButton=document.getElementById('pagoEfectivoButton'),errorBox=document.getElementById('culqiError');const showError=message=>{errorBox.textContent=message||'No pudimos procesar el pago.';errorBox.classList.remove('hidden');button.disabled=false};const checkout=new CulqiCheckout(@json(config('services.culqi.public_key')),{settings:{title:'NavegaYA',currency:@json($subscription->currency),amount:{{(int)round((float)$subscription->monthly_price*100)}},client:{email:@json($organization->email)},options:{lang:'es',modal:true,paymentMethods:{tarjeta:true,yape:true,billetera:false,bancaMovil:false,agente:false,cuotealo:false}}});checkout.culqi=async()=>{if(!checkout.token)return showError(checkout.error?.user_message);checkout.close();button.disabled=true;try{const response=await fetch(@json(route('marketplace.subscription.culqi.charge')),{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({source_id:checkout.token.id})});const result=await response.json();if(!response.ok)throw new Error(result.message||'El pago fue rechazado.');location.assign(result.redirect)}catch(error){showError(error.message)}};button.addEventListener('click',()=>checkout.open());cashButton.addEventListener('click',async()=>{cashButton.disabled=true;try{const response=await fetch(@json(route('marketplace.subscription.culqi.order')),{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:'{}'});const result=await response.json();if(!response.ok)throw new Error(result.message);document.getElementById('paymentCode').textContent=result.payment_code||result.order_id;document.getElementById('pagoEfectivoResult').classList.remove('hidden')}catch(error){showError(error.message);cashButton.disabled=false}})});
</script>
@endif
</x-marketplace-layout>
