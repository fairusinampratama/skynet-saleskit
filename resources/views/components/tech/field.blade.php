@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'style' => 'default',
])

@php
    $labelClass = $style === 'portal'
        ? 'grid gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-500'
        : 'grid gap-1.5 text-sm font-semibold text-slate-700';
    $inputClass = $style === 'portal'
        ? 'w-full rounded-xl border border-slate-200 bg-white/95 px-3.5 py-3 text-sm font-bold text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-amber-400 focus:ring-4 focus:ring-amber-500/10'
        : 'w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-slate-950 focus:border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-600/25';
@endphp

<label class="{{ $labelClass }}">
    <span>{{ $label }}</span>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        {{ $attributes->merge(['class' => $inputClass]) }}
    >
</label>
