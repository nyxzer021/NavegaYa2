<x-marketplace-layout title="Mensajes comerciales · NavegaYA">
    <div class="grid min-h-[calc(100vh-64px)] lg:grid-cols-[270px_minmax(0,1fr)]">
        @include('marketplace._portal-sidebar', ['activePortalSection' => 'messages'])
        <main class="min-w-0 bg-slate-50 p-4 md:p-7">@include('business-messages._content')</main>
    </div>
</x-marketplace-layout>
