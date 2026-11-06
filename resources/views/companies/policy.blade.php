<x-public-layout :title="'Política de viaje · '.($organization->commercial_name ?: $organization->legal_name)">
<main class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-black">Política de viaje</h1>
    <p class="mt-2 text-sm text-slate-500">Versión {{ $version->version }}</p>
    <article class="prose mt-6 max-w-none text-sm leading-7">{!! \App\Support\PolicyHtml::clean($version->body_html) !!}</article>
</main>
</x-public-layout>
