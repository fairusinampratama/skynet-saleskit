@props(['variant' => 'neutral'])

@php
    $variants = [
        'neutral' => 'bg-slate-100 text-slate-700',
        'ok' => 'bg-emerald-50 text-emerald-700',
        'warn' => 'bg-amber-50 text-amber-800',
        'danger' => 'bg-rose-50 text-rose-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-extrabold capitalize '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
