<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-rose-600 px-5 py-2.5 text-xs font-extrabold uppercase tracking-wider text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-rose-500 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-rose-500/20 active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
