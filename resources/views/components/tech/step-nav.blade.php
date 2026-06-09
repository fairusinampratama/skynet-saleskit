@props(['style' => 'default'])

@php
    $classes = $style === 'portal'
        ? 'sticky top-[65px] z-10 -mx-3 overflow-x-auto border-y border-slate-200/70 bg-white/90 px-3 py-2 shadow-sm backdrop-blur md:top-[69px] md:mx-0 md:rounded-2xl md:border md:px-2'
        : 'sticky top-[65px] z-10 -mx-3 overflow-x-auto border-y border-slate-200 bg-white/95 px-3 py-1.5 backdrop-blur md:top-[69px] md:mx-0 md:rounded-lg md:border md:px-2';
@endphp

<nav {{ $attributes->merge(['class' => $classes]) }}>
    <div class="flex w-max min-w-full items-center gap-1.5">
        {{ $slot }}
    </div>
</nav>
