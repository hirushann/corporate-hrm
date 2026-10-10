@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-zinc-300 bg-white text-sm shadow-xs placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-zinc-50 disabled:text-zinc-500']) }}>
