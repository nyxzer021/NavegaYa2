@php
    try {
        $legalPolicies = \App\Models\PublicPage::query()
            ->whereIn('slug', ['terminos', 'privacidad', 'cookies', 'seguridad'])
            ->where('is_published', true)
            ->get(['slug', 'title', 'summary', 'content']);
    } catch (\Throwable $exception) {
        $legalPolicies = collect();
    }
@endphp

<style>
    .ny-legal-dialog{width:min(760px,calc(100vw - 24px));max-height:min(82vh,760px);margin:auto;padding:0;border:1px solid #dbe5e1;border-radius:18px;background:#fff;color:#334155;box-shadow:0 28px 80px rgba(2,24,18,.30);overflow:hidden}
    .ny-legal-dialog::backdrop{background:rgba(2,10,24,.68);backdrop-filter:blur(4px)}
    .ny-legal-dialog[open]{display:flex;flex-direction:column}
    .ny-legal-scroll{overflow-y:auto;overscroll-behavior:contain}
    @media(max-width:640px){.ny-legal-dialog{width:calc(100vw - 16px);max-height:90dvh;border-radius:14px}.ny-legal-summary{display:none}}
</style>

@foreach($legalPolicies as $policy)
    <dialog id="legal-{{$policy->slug}}" class="ny-legal-dialog" onclick="if(event.target===this)this.close()" aria-labelledby="legal-title-{{$policy->slug}}">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-5 sm:px-8">
            <div class="min-w-0"><p class="text-[9px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Información legal de NavegaYA</p><h2 id="legal-title-{{$policy->slug}}" class="mt-1 text-lg font-extrabold leading-tight text-slate-950 sm:text-xl">{{$policy->title}}</h2>@if($policy->summary)<p class="ny-legal-summary mt-1 text-xs leading-5 text-slate-500">{{$policy->summary}}</p>@endif</div>
            <form method="dialog"><button class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-slate-200 bg-slate-50 text-xl text-slate-600 transition hover:bg-slate-100" aria-label="Cerrar">×</button></form>
        </header>
        <div class="ny-legal-scroll min-h-0 flex-1 px-5 py-5 sm:px-8 sm:py-6"><div class="whitespace-pre-line text-[12px] leading-6 text-slate-600 sm:text-[13px]">{{$policy->content}}</div></div>
        <footer class="flex shrink-0 justify-end border-t border-slate-200 bg-slate-50 px-5 py-3 sm:px-8"><form method="dialog"><button class="rounded-lg bg-[#062c21] px-5 py-2 text-xs font-bold text-white transition hover:bg-[#0d5b49]">Cerrar</button></form></footer>
    </dialog>
@endforeach
