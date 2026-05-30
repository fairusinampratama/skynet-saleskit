@props(['compact' => false])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white '.($compact ? 'p-4' : 'p-4 md:p-5')]) }}>
    {{ $slot }}
</section>
