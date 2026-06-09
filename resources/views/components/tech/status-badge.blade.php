@props(['variant' => 'neutral', 'style' => 'default'])

@php
    $isPortal = $style === 'portal';
    $variants = $isPortal
        ? [
            'neutral' => 'border border-slate-200 bg-white text-slate-600',
            'ok' => 'border border-emerald-200 bg-emerald-50 text-emerald-700',
            'warn' => 'border border-amber-200 bg-amber-50 text-amber-700',
            'danger' => 'border border-rose-200 bg-rose-50 text-rose-700',
            'dark' => 'border border-white/10 bg-white/10 text-slate-100',
        ]
        : [
            'neutral' => 'bg-slate-100 text-slate-700',
            'ok' => 'bg-emerald-50 text-emerald-700',
            'warn' => 'bg-amber-50 text-amber-800',
            'danger' => 'bg-rose-50 text-rose-700',
        ];
    $base = $isPortal
        ? 'inline-flex w-fit items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider'
        : 'inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-extrabold capitalize';
@endphp

<span {{ $attributes->merge(['class' => $base.' '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
