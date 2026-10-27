@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-slate-300 bg-white text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700/20']) }}>
