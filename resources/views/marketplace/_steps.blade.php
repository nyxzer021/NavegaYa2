@php
    $steps = [
        [
            'label' => 'Activa tu plan',
            'hint' => 'Tu página y tus anuncios se ven solo con el plan activo.',
            'done' => $subscription?->status === 'active',
            'tab' => 'plan',
        ],
        [
            'label' => 'Completa tu página',
            'hint' => 'Descripción, teléfono de contacto y horario de atención.',
            'done' => filled($listing->description) && filled($listing->contact_phone) && filled($listing->opening_schedule),
            'tab' => 'profile',
        ],
        [
            'label' => 'Sube tus fotos',
            'hint' => 'Al menos una foto y una portada que presente tu negocio.',
            'done' => $listing->images->isNotEmpty(),
            'tab' => 'gallery',
        ],
        [
            'label' => 'Envía tu página a revisión',
            'hint' => 'NavegaYA la revisa antes de publicarla.',
            'done' => in_array($listing->status, ['pending_review', 'published'], true),
            'tab' => 'profile',
        ],
        [
            'label' => 'Página publicada',
            'hint' => 'Los viajeros ya te encuentran en la Guía Loreto.',
            'done' => $listing->status === 'published',
            'tab' => null,
        ],
    ];
    $doneSteps = collect($steps)->where('done', true)->count();
    $nextStep = collect($steps)->firstWhere('done', false);
@endphp
@if($nextStep && $listing->status !== 'published')
<section class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="mk-steps-title" data-steps="{{ $doneSteps }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="mk-steps-title" class="text-lg font-black text-slate-950">Pasos para publicar tu negocio</h2>
            <p class="text-sm text-slate-500">{{ $doneSteps }} de {{ count($steps) }} listos. Sigue con: <strong class="text-slate-800">{{ $nextStep['label'] }}</strong>.</p>
        </div>
        <div class="h-2 w-40 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
            <div class="h-full rounded-full bg-emerald-600" style="width: {{ round($doneSteps / count($steps) * 100) }}%"></div>
        </div>
    </div>
    <ol class="mt-5 grid gap-3 md:grid-cols-5">
        @foreach($steps as $index => $step)
            @php($isNext = $step['label'] === $nextStep['label'])
            <li class="rounded-2xl border p-4 {{ $step['done'] ? 'border-emerald-200 bg-emerald-50' : ($isNext ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white') }}">
                <span class="mk-kpi-icon" style="--c: {{ $step['done'] ? 'var(--ny-tone-leaf)' : ($isNext ? 'var(--ny-tone-sun)' : '#8a9a94') }}">
                    @if($step['done'])<x-ui.icon name="check" />@else<span class="text-sm font-black">{{ $index + 1 }}</span>@endif
                </span>
                <strong class="mt-3 block text-sm text-slate-900">{{ $step['label'] }}</strong>
                <span class="mt-1 block text-xs leading-5 text-slate-600">{{ $step['hint'] }}</span>
                @if(! $step['done'] && $step['tab'])
                    <a href="{{ route('marketplace.dashboard', ['tab' => $step['tab']]) }}" class="mt-3 inline-flex min-h-9 items-center rounded-xl bg-emerald-800 px-3 text-xs font-black text-white hover:bg-emerald-900">{{ $isNext ? 'Hacerlo ahora' : 'Ir' }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</section>
@endif
