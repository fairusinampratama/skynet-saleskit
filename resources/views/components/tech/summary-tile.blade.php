@props(['label', 'value', 'valueId' => null, 'style' => 'default'])

@php
    $classes = $style === 'portal'
        ? 'rounded-2xl border border-white/70 bg-white/85 p-3 shadow-sm'
        : 'rounded-lg border border-slate-200 bg-slate-50 p-3';
    $valueClass = $style === 'portal'
        ? 'block font-mono text-lg font-black text-slate-950'
        : 'block text-base font-extrabold text-slate-950';
    $labelClass = $style === 'portal'
        ? 'mt-0.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400'
        : 'text-sm text-slate-500';
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    <strong @if ($valueId) id="{{ $valueId }}" @endif class="{{ $valueClass }}">{{ $value }}</strong>
    <span class="{{ $labelClass }}">{{ $label }}</span>
</div>
