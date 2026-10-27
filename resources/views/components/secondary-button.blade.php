<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-xs font-extrabold uppercase tracking-wider text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-700/15 active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
