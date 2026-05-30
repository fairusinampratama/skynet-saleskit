<nav {{ $attributes->merge(['class' => 'sticky top-[65px] z-10 -mx-3 overflow-x-auto border-y border-slate-200 bg-white/95 px-3 py-1.5 backdrop-blur md:top-[69px] md:mx-0 md:rounded-lg md:border md:px-2']) }}>
    <div class="flex w-max min-w-full items-center gap-1.5">
        {{ $slot }}
    </div>
</nav>
