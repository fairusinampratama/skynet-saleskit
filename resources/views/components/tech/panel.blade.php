@props(['compact' => false, 'style' => 'default'])

@php
    $classes = $style === 'portal'
        ? 'tech-glow-card glass-panel rounded-2xl border border-white/70 bg-white/85 shadow-[0_18px_45px_rgb(15_23_42_/_0.08)] '.($compact ? 'p-4' : 'p-4 md:p-5')
        : 'rounded-lg border border-slate-200 bg-white '.($compact ? 'p-4' : 'p-4 md:p-5');
@endphp

<section {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</section>
