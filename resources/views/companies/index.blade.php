<x-public-layout current="companies" :title="__('companies.title').' · NavegaYA'">
    <section class="mx-auto max-w-6xl px-4 pb-12 pt-6 sm:px-6">
        <x-amazon-hero-bg theme="companies" class="text-center" overlay="from-[var(--ny-primary-deep)]/90 to-[var(--ny-primary)]/60"><div class="px-4 py-10 text-[var(--ny-text-on-dark)]">
            <h1 class="font-[family-name:var(--ny-title-font)] text-3xl font-bold">{{ __('companies.title') }}</h1>
            <p class="mt-2 text-sm text-[var(--ny-text-on-dark-muted)]">{{ __('companies.intro') }}</p>
        </div></x-amazon-hero-bg>

        <form action="{{ route('companies.index') }}" class="my-5 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <label class="ny-field">
                {{ __('companies.port') }}
                <select name="port" class="min-w-0">
                    <option value="">{{ __('companies.all_ports') }}</option>
                    @foreach ($ports as $option)
                        <option value="{{ $option->id }}" @selected($port == $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ny-field">
                {{ __('companies.mode') }}
                <select name="mode">
                    <option value="">{{ __('companies.all_modes') }}</option>
                    <option value="river" @selected($mode === 'river')>{{ __('companies.river') }}</option>
                    <option value="air" @selected($mode === 'air')>{{ __('companies.air') }}</option>
                </select>
            </label>
            <button class="ny-button" type="submit">{{ __('companies.filter') }}</button>
        </form>

        <div class="grid gap-3 md:grid-cols-2">
            @forelse ($companies as $card)
                @php
                    $company = $card['company'];
                    $name = $company->commercial_name ?: $company->legal_name;
                    $initials = collect(preg_split('/\s+/u', trim($name)))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                    $whatsapp = $company->publicWhatsapp();
                @endphp
                <article data-company-card class="flex min-w-0 flex-col gap-3 rounded-[var(--ny-radius)] border border-[var(--ny-border)] bg-[var(--ny-surface)] p-4 shadow-[var(--ny-shadow-sm)]">
                    <div class="flex items-center gap-3">
                        @if ($company->logo_path && ($company->profile_content_status ?? 'published') === 'published')
                            <img src="{{ $company->logo_path }}" alt="" class="size-12 shrink-0 rounded-full object-cover">
                        @else
                            <span aria-hidden="true" class="grid size-12 shrink-0 place-items-center rounded-full bg-[var(--ny-primary-hover)] text-base font-bold text-[var(--ny-text-on-dark)]">{{ $initials }}</span>
                        @endif
                        <div class="min-w-0">
                            <h2 class="break-words text-lg font-bold">{{ $name }}</h2>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-[var(--ny-text-muted)]">
                                @if ($card['river'] || $card['air'])
                                    <span>{{ __('companies.'.($card['river'] && $card['air'] ? 'mixed' : ($card['air'] ? 'air' : 'river'))) }}</span>
                                @endif
                                @if ($company->verified_at)
                                    <span class="rounded-full bg-[var(--ny-success-bg)] px-2 py-0.5 font-bold text-[var(--ny-success)]">{{ __('companies.verified') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($card['routes'] || $card['weekly'] || $card['price'] !== null)
                        <ul class="flex flex-wrap gap-2 text-xs font-bold">
                            @if ($card['routes'])
                                <li class="rounded-full bg-[var(--ny-surface-muted)] px-2 py-1">{{ trans_choice('companies.routes', $card['routes']) }}</li>
                            @endif
                            @if ($card['weekly'])
                                <li class="rounded-full bg-[var(--ny-surface-muted)] px-2 py-1">{{ trans_choice('companies.weekly', $card['weekly']) }}</li>
                            @endif
                            @if ($card['price'] !== null)
                                <li class="rounded-full bg-[var(--ny-success-bg)] px-2 py-1 text-[var(--ny-success)]">{{ __('companies.price', ['price' => number_format($card['price'], 2)]) }}</li>
                            @endif
                        </ul>
                    @endif
                    <div class="mt-auto flex flex-wrap items-center gap-2">
                        <a class="ny-button text-xs" href="{{ route('companies.show', $company) }}">{{ __('companies.open') }}</a>
                        @if ($whatsapp)
                            <a class="ny-trip-icon size-[42px]" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="{{ __('companies.whatsapp', ['name' => $name]) }}">
                                <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3a8.7 8.7 0 0 0-7.5 13L3.2 21l5-1.3A8.7 8.7 0 1 0 12 3zm4.8 11.7c-.2.6-1.1 1-1.6 1.1-.4 0-.9.2-2.9-.6-2.4-1-3.9-3.4-4-3.6-.1-.2-1-1.2-1-2.3s.6-1.6.8-1.8.4-.3.6-.3h.5c.2 0 .5.7.8 1.8.1.2.1.4-.1.6l-.6.7c-.2.2 0 .4.1.6a6.6 6.6 0 0 0 2.8 2.4c.3.1.5.1.6-.1l.9-1c.1-.2.3-.2.5-.1l1.8.9c.2.1.3.1.3.3.1.1 0 .5-.1 1z"/></svg>
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="ny-empty md:col-span-2">{{ __('companies.empty') }}</p>
            @endforelse
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-[var(--ny-radius)] border border-[var(--ny-border)] bg-[var(--ny-surface-muted)] p-4">
            <h2 class="text-base font-bold">{{ __('companies.join') }}</h2>
            <a class="ny-button text-xs" href="{{ route('company.registration') }}">{{ __('companies.apply') }}</a>
        </div>
    </section>
</x-public-layout>
